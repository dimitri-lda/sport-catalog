# Moduł 1 Katalog Produktów

Specyfikacja funkcjonalna znajduje się w `module01_product_catalog_pl.docx`.
Implementacja dotyczy wyłącznie głównej aplikacji Laravel/Inertia.

## Uruchomienie

Po zainstalowaniu zależności i skonfigurowaniu `.env`:

```sh
php artisan migrate
npm run build
```

Na pustej bazie demonstracyjnej można dodatkowo uruchomić:

```sh
php artisan db:seed
```

Seeder aktualizuje osiem demonstracyjnych produktów, ich warianty, powiązania,
parametry i zdjęcia. Nie używać go do aktualizacji rzeczywistych stanów
magazynowych: demonstracyjny zapas jest ustawiany ponownie przy uruchomieniu.
Zdjęcia i parametry seedera są przykładami prezentacji, a nie kartami
technicznymi producentów.

## Zachowanie

- `/shop` prezentuje aktywne produkty i pełne drzewo kategorii.
- Filtr `category` obejmuje kategorię i wszystkich jej potomków.
- Podkategorie oraz paginacja zachowują wyszukiwanie i sortowanie.
- `/shop/{slug}` prezentuje markę, producenta, ścieżkę kategorii, opis,
  galerię i uporządkowane parametry techniczne.
- Produkt bez wariantu z dodatnim zapasem jest niedostępny. Nieaktywne
  produkty zwracają 404. Brak ceny blokuje dodanie z witryny.
- Rekomendacje na stronie produktu zostały usunięte. Pozostałe moduły,
  w tym koszyk, konta i zamówienia, pozostają dostępne.

## Zarządzanie danymi przed modułem 12

Panel administracyjny katalogu zostanie dodany w module 12. Do tego czasu
słowniki i powiązania można utrzymywać przez modele Eloquent, np. w Tinker:

```php
$brand = App\Models\Brand::create(['name' => 'Przykładowa marka', 'slug' => 'przykladowa-marka']);
$maker = App\Models\Manufacturer::create(['name' => 'Przykładowy producent', 'slug' => 'przykladowy-producent']);
$product = App\Models\Product::findOrFail(1);
$product->update(['brand_id' => $brand->id, 'manufacturer_id' => $maker->id]);
$product->specifications()->create(['name' => 'Masa', 'value' => '300', 'unit' => 'g', 'position' => 0]);
$product->images()->create(['url' => '/images/product-side.jpg', 'alt' => 'Widok z boku', 'position' => 1]);
```

Modele odrzucają cykle kategorii, nieprawidłowe powiązania, puste parametry
oraz usunięcie używanych kategorii, marek i producentów. Masowe operacje
Query Builder omijają walidację modeli; korzystać z operacji na instancjach.
Migracja zachowuje dotychczasowe marki i łączy różnice wielkości liter oraz
zewnętrznych spacji. Pole `brand` pozostaje dla zgodności i rollbacku;
źródłem aktualnej nazwy jest relacja `brandRecord`.

## Język i ceny

Witryna używa polskiego języka i PLN niezależnie od zapisanych wcześniej
preferencji rynku. Usunięto przełącznik regionu i języka oraz geolokalizację
żądania. Istniejące ceny pozostają EUR netto w bazie; aktualny serwis cen
przelicza je na PLN i dodaje polski VAT. Bez dostępnego kursu strona pokazuje
brak ceny zamiast oznaczać cenę EUR jako PLN. Zmiana bazowych cen na stałe PLN
wymaga odrębnej migracji i ustalonego sposobu przeliczenia. Historyczne
zamówienia oraz kontrakty serwisu wyceny pozostają zachowane.

## Weryfikacja

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
npm run build
```

`ProductCatalogTest` obejmuje hierarchię, paginację, słowniki, galerię,
parametry, dostępność, integrację z koszykiem, migrację i rollback marek,
błędy wyceny oraz powtarzalność seedera. Testy działają na izolowanej bazie
SQLite w pamięci; nie należy resetować lokalnej bazy w celu uruchomienia testów.
