<?php

namespace App\Http\Controllers;

use App\Application\Orders\PlaceOrder;
use App\Http\Requests\Store\PlaceOrderRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\PricingService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function home(Request $request, PricingService $pricing): Response
    {
        return Inertia::render('Store/Home', [
            'categories' => $this->categories()->whereNull('parent_id')->values(),
            'featured' => Product::with(['category', 'variants', 'brandRecord', 'manufacturer'])->where('featured', true)->where('active', true)->take(8)->get()->map(fn (Product $product) => $this->priced($product, $pricing, $request->attributes->get('market'))),
        ]);
    }

    public function catalog(Request $request, PricingService $pricing): Response
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'in:price_asc,price_desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Product::with(['category', 'variants', 'brandRecord', 'manufacturer'])->where('active', true);
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->q.'%')->orWhereHas('brandRecord', fn ($brands) => $brands->where('name', 'like', '%'.$request->q.'%')));
        }
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->firstOrFail();
            $categories = Category::get(['id', 'parent_id']);
            $ids = [$category->id];
            for ($index = 0; $index < count($ids); $index++) {
                foreach ($categories->where('parent_id', $ids[$index]) as $child) {
                    if (! in_array($child->id, $ids)) {
                        $ids[] = $child->id;
                    }
                }
            }
            $query->whereIn('category_id', $ids);
        }
        if (in_array($request->get('sort'), ['price_asc', 'price_desc'])) {
            $multiplier = $query->clone()->whereNull('price_cents_pln')->exists()
                ? $pricing->legacyPlnGrossMultiplier($request->attributes->get('market')) : 1;
            $direction = $request->get('sort') === 'price_asc' ? 'ASC' : 'DESC';
            // Prices which cannot currently be calculated follow known prices.
            $query->orderByRaw('COALESCE(price_cents_pln, price_cents_net * ?) IS NULL ASC', [$multiplier])
                ->orderByRaw('COALESCE(price_cents_pln, price_cents_net * ?) '.$direction, [$multiplier])->orderBy('id');
        } else {
            $query->latest()->orderByDesc('id');
        }

        return Inertia::render('Store/Catalog', [
            'products' => tap($query->paginate(12)->withQueryString(), fn ($products) => $products->getCollection()->transform(fn (Product $product) => $this->priced($product, $pricing, $request->attributes->get('market')))),
            'categories' => $this->categories(),
            'filters' => $request->only(['q', 'category', 'sort']),
        ]);
    }

    public function product(Product $product, Request $request, PricingService $pricing): Response
    {
        abort_unless($product->active, 404);

        $categories = Category::get();
        $breadcrumbs = [];
        $category = $categories->firstWhere('id', $product->category_id);
        $seen = [];
        while ($category && ! in_array($category->id, $seen)) {
            $seen[] = $category->id;
            array_unshift($breadcrumbs, $category);
            $category = $categories->firstWhere('id', $category->parent_id);
        }

        return Inertia::render('Store/Product', [
            'product' => $this->priced($product->load(['category', 'variants', 'images', 'specifications', 'brandRecord', 'manufacturer']), $pricing, $request->attributes->get('market')),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function checkout(Request $request, CartService $carts): Response
    {
        $cart = $carts->forRequest($request)->load('items.variant.product');
        if ($cart->items->isEmpty()) {
            return Inertia::render('Store/Cart', ['cart' => $carts->data($cart, $request->attributes->get('market'))]);
        }

        return Inertia::render('Store/Checkout', [
            'cart' => $carts->data($cart, $request->attributes->get('market')),
            'user' => $request->user()?->only(['name', 'email']),
            'address' => $request->user()?->savedAddress,
        ]);
    }

    public function placeOrder(PlaceOrderRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        try {
            $order = $placeOrder->handle($request->validated(), $request->user()?->id, $request->session()->get('cart_token'), $request->attributes->get('market'));
        } catch (DomainException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }
        $request->session()->put('last_order', $order->number);

        return redirect()->route('store.order', ['order' => $order->number])->with('success', 'Your order has been placed.');
    }

    public function order(Request $request, Order $order): Response
    {
        abort_unless($request->user()?->id === $order->user_id || $request->session()->get('last_order') === $order->number, 404);

        return Inertia::render('Store/Order', ['order' => $order->load(['items', 'address'])]);
    }

    private function categories(): Collection
    {
        $categories = Category::withCount(['products' => fn ($query) => $query->where('active', true)])->orderBy('name')->get();
        $counts = $categories->pluck('products_count', 'id');

        return $categories->map(function (Category $category) use ($categories, $counts) {
            $ids = [$category->id];
            for ($index = 0; $index < count($ids); $index++) {
                foreach ($categories->where('parent_id', $ids[$index]) as $child) {
                    if (! in_array($child->id, $ids)) {
                        $ids[] = $child->id;
                    }
                }
            }
            $category->products_count = $counts->only($ids)->sum();

            return $category;
        });
    }

    private function priced(Product $product, PricingService $pricing, array $market): Product
    {
        try {
            $product->setAttribute('display_price', $pricing->displayPrice($product->price_cents_net, $market, $product->price_cents_pln));
            $product->variants->each(function ($variant) use ($product, $pricing, $market) {
                $variant->setAttribute('display_price', $pricing->displayPrice($variant->price_cents_net ?? $product->price_cents_net, $market, $variant->price_cents_pln ?? $product->price_cents_pln));
            });
        } catch (DomainException $e) {
            $product->setAttribute('pricing_error', $e->getMessage());
        }

        return $product;
    }
}
