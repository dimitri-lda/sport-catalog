import { Link } from '@inertiajs/react';
import StoreLayout from '@/Layouts/StoreLayout';
import ProductCard from '@/Components/ProductCard';
import { useI18n } from '@/lib/i18n';

function CategoryTree({ categories, parentId = null, filters, visited = [] }) {
    return categories.filter(category => category.parent_id === parentId && !visited.includes(category.id)).map(category => <li key={category.id}>
        <Link href={route('store.catalog', { ...filters, category: category.slug })} aria-current={filters.category === category.slug ? 'page' : undefined} className={`block rounded-xl px-3 py-2 text-sm ${filters.category === category.slug ? 'bg-slate-950 font-bold text-white' : 'hover:bg-slate-100'}`}>{category.localized_name ?? category.name} <span className="text-xs opacity-60">({category.products_count})</span></Link>
        <ul className="ml-3 border-l border-slate-200 pl-2"><CategoryTree categories={categories} parentId={category.id} filters={filters} visited={[...visited, category.id]} /></ul>
    </li>);
}

export default function Catalog({ products, categories, filters }) {
    const t = useI18n();
    return <StoreLayout title={t('catalog_title')}><main className="mx-auto max-w-7xl px-5 py-12 lg:px-8">
        <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div><p className="text-xs font-bold uppercase tracking-[.25em] text-blue-600">{t('catalog_eyebrow')}</p><h1 className="mt-3 text-4xl font-black tracking-tight">{t('catalog_title')}</h1><p className="mt-2 text-sm text-slate-500">Liczba produktów: {products.total}</p></div>
            <form action={route('store.catalog')} className="flex flex-wrap gap-2">
                {filters.category && <input type="hidden" name="category" value={filters.category} />}
                <input aria-label={t('catalog_search')} name="q" defaultValue={filters.q ?? ''} placeholder={t('catalog_search')} className="min-w-0 rounded-full border-slate-200 bg-white px-5 py-2 text-sm" />
                <select aria-label="Sortowanie" name="sort" defaultValue={filters.sort ?? ''} className="rounded-full border-slate-200 bg-white px-4 py-2 text-sm"><option value="">{t('catalog_latest')}</option><option value="price_asc">{t('catalog_price_asc')}</option><option value="price_desc">{t('catalog_price_desc')}</option></select>
                <button className="rounded-full bg-slate-950 px-5 py-2 text-sm font-bold text-white">{t('catalog_go')}</button>
            </form>
        </div>
        <div className="mt-10 grid gap-8 lg:grid-cols-[230px_1fr]">
            <nav aria-label="Kategorie produktów" className="self-start rounded-2xl bg-white p-4"><h2 className="mb-3 font-bold">Kategorie</h2><Link href={route('store.catalog', { q: filters.q, sort: filters.sort })} className={`mb-2 block rounded-xl px-3 py-2 text-sm ${!filters.category ? 'bg-slate-950 text-white' : ''}`}>{t('catalog_all')}</Link><ul><CategoryTree categories={categories} filters={filters} /></ul></nav>
            <div>{products.data.length ? <div className="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 xl:grid-cols-4">{products.data.map(product => <ProductCard key={product.id} product={product} />)}</div> : <div className="rounded-2xl bg-white p-10 text-center"><p className="font-bold">Nie znaleziono produktów.</p><Link href={route('store.catalog')} className="mt-4 inline-block text-blue-600">Przejdź do pełnego katalogu</Link></div>}
                {products.links.length > 3 && <nav aria-label="Strony katalogu" className="mt-12 flex flex-wrap justify-center gap-2">{products.links.map((link, index) => link.url ? <Link key={index} href={link.url} aria-current={link.active ? 'page' : undefined} className={`rounded-full px-4 py-2 text-sm ${link.active ? 'bg-slate-950 text-white' : 'bg-white'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={index} className="rounded-full bg-white px-4 py-2 text-sm opacity-30" dangerouslySetInnerHTML={{ __html: link.label }} />)}</nav>}
            </div>
        </div>
    </main></StoreLayout>;
}
