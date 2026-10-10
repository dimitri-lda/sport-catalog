import { Link, router } from '@inertiajs/react';
import ProductImage from '@/Components/ProductImage';
import { money, useI18n } from '@/lib/i18n';

export default function ProductCard({ product }) {
    const t = useI18n();
    const variant = product.variants?.find(v => v.stock > 0);
    const available = product.available && variant && !product.pricing_error;
    return <article className="group">
        <div className="relative">
            <Link href={route('store.product', product.slug)} className="block overflow-hidden rounded-2xl bg-white">
                <ProductImage src={product.image_url} alt={product.localized_name ?? product.name} className="aspect-square w-full object-cover transition duration-500 group-hover:scale-105" />
                <span className="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-[10px] font-bold uppercase tracking-wider">{product.featured ? t('catalog_featured') : (product.category?.localized_name ?? product.category?.name)}</span>
            </Link>
            <button type="button" disabled={!available} onClick={() => router.post(route('store.cart.add', variant.id), { quantity: 1 }, { preserveScroll: true })} aria-label={`Dodaj do koszyka: ${product.localized_name ?? product.name}`} className="absolute bottom-3 right-3 flex h-10 w-10 items-center justify-center rounded-full bg-slate-950 text-lg text-white transition hover:bg-blue-600 disabled:cursor-not-allowed disabled:bg-slate-300">+</button>
        </div>
        <div className="pt-4">
            {product.brand_record && <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">{product.brand_record.name}</p>}
            <Link href={route('store.product', product.slug)} className="mt-1 block font-bold hover:text-blue-600">{product.localized_name ?? product.name}</Link>
            <p className="mt-2 font-bold">{product.display_price ? money(product.display_price.gross_cents, product.display_price.currency, 'pl-PL') : 'Cena chwilowo niedostępna'}</p>
            <p className={`mt-2 text-sm ${product.available ? 'text-green-700' : 'text-slate-500'}`}>{product.available ? 'Dostępny' : 'Niedostępny'}</p>
        </div>
    </article>;
}
