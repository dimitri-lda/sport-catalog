<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FixedPlnPricingTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'test'], ['name' => 'Test']);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Test', 'slug' => 'test-'.uniqid(),
            'description' => 'Opis', 'image_url' => '/image.jpg', 'price_cents_net' => 10000,
            'active' => true, ...$attributes,
        ]);
    }

    public function test_demonstration_catalog_uses_fixed_pln_prices_without_exchange_rate_requests(): void
    {
        Http::fake();
        $this->seed();
        $this->get('/shop?sort=price_asc')->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.slug', 'aero-run-shorts')->where('products.data.0.display_price.gross_cents', 19900)
            ->where('products.data.0.display_price.currency', 'PLN')->missing('products.data.0.pricing_error'));
        $this->get('/shop/cloudswift-4')->assertInertia(fn (Assert $page) => $page
            ->where('product.display_price.gross_cents', 84900)->where('product.variants.0.display_price.gross_cents', 84900));
        $this->get('/shop?sort=price_desc')->assertInertia(fn (Assert $page) => $page->where('products.data.0.slug', 'pure-aero-98'));
        Http::assertNothingSent();
    }

    public function test_variant_price_and_checkout_snapshot_match_without_fx_or_double_tax(): void
    {
        Http::fake();
        $product = $this->product(['price_cents_pln' => 84900]);
        $variant = $product->variants()->create(['name' => '42', 'sku' => 'shoe', 'stock' => 5, 'price_cents_pln' => 79900]);
        $this->get('/shop/'.$product->slug)->assertInertia(fn (Assert $page) => $page->where('product.variants.0.display_price.gross_cents', 79900));
        $this->post('/cart/items/'.$variant->id, ['quantity' => 2])->assertRedirect();
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('cart.items.0.unit_price_cents', 79900)->where('cart.items.0.line_total_cents', 159800)
            ->where('cart.subtotal_cents', 159800)->where('cart.delivery_cents', 0)
            ->where('cart.net_subtotal_cents', 129918)->where('cart.tax_cents', 29882));
        // A foreign shipping address must not silently switch a PLN order to EUR.
        $this->post('/checkout', [
            'email' => 'buyer@example.test', 'phone' => '123456789', 'first_name' => 'Jan', 'last_name' => 'Nowak',
            'line1' => 'Test 1', 'city' => 'Berlin', 'postal_code' => '10115', 'country' => 'DE', 'delivery_method' => 'standard',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::with('items', 'address')->firstOrFail();
        $this->assertSame('PLN', $order->currency);
        $this->assertSame('pl', $order->locale);
        $this->assertSame('DE', $order->address->country);
        $this->assertEquals(159800, $order->total_cents);
        $this->assertEquals(79900, $order->items->first()->unit_price_cents);
        $variant->update(['price_cents_pln' => 89900]);
        $this->assertEquals(79900, $order->items->first()->fresh()->unit_price_cents);
        $this->assertDatabaseCount('cart_items', 0);
        Http::assertNothingSent();
    }

    public function test_product_price_is_used_when_variant_has_no_pln_override_and_shipping_is_fixed(): void
    {
        Http::fake();
        $product = $this->product(['price_cents_pln' => 19900]);
        $variant = $product->variants()->create(['name' => 'Default', 'sku' => 'default', 'stock' => 2]);
        $this->post('/cart/items/'.$variant->id)->assertRedirect();
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page
            ->where('cart.items.0.unit_price_cents', 19900)->where('cart.delivery_cents', 2900)->where('cart.total_cents', 22800));
        Http::assertNothingSent();
    }

    public function test_mixed_prices_are_sorted_by_display_value_and_fixed_prices_do_not_change_with_fx(): void
    {
        ExchangeRate::create(['currency' => 'PLN', 'rate' => 4, 'source' => 'test', 'quoted_at' => now()]);
        $fixed = $this->product(['price_cents_pln' => 30000]);
        $legacy = $this->product();
        $this->get('/shop?sort=price_asc')->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.id', $fixed->id)->where('products.data.1.id', $legacy->id)
            ->where('products.data.1.display_price.gross_cents', 49200));
        ExchangeRate::where('currency', 'PLN')->update(['rate' => 2]);
        $this->get('/shop?sort=price_asc')->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.id', $legacy->id)->where('products.data.1.id', $fixed->id)
            ->where('products.data.1.display_price.gross_cents', 30000));
        ExchangeRate::query()->delete();
        Http::fake(['*' => Http::response([], 503)]);
        $this->get('/shop?sort=price_desc')->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.id', $fixed->id)->missing('products.data.0.pricing_error')
            ->where('products.data.1.id', $legacy->id)->has('products.data.1.pricing_error'));
    }

    public function test_migration_updates_only_known_demo_prices_without_resetting_stock_or_legacy_amounts(): void
    {
        $migration = require database_path('migrations/2026_10_10_000002_add_fixed_pln_catalog_prices.php');
        $migration->down();
        $demo = $this->product(['slug' => 'cloudswift-4']);
        $other = $this->product();
        $variant = $demo->variants()->create(['name' => 'Demo', 'sku' => 'CLOUDSWIFT-4-ONE', 'stock' => 7]);
        $migration->up();
        $this->assertEquals(84900, $demo->fresh()->price_cents_pln);
        $this->assertEquals(84900, $variant->fresh()->price_cents_pln);
        $this->assertEquals(7, $variant->fresh()->stock);
        $this->assertEquals(10000, $demo->fresh()->price_cents_net);
        $this->assertNull($other->fresh()->price_cents_pln);
    }
}
