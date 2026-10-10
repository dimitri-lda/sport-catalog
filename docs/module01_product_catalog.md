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
żądania. Pole `price_cents_pln` zawiera stałą cenę brutto w groszach PLN,
łącznie z VAT. Wariant może nadpisać cenę produktu; brak ceny wariantu
powoduje użycie ceny produktu. Wycena nie dodaje VAT ponownie i nie wymaga
kursu walut dla produktów ze stałą ceną PLN.

Nowe ceny demonstracyjne:

| Produkt | Cena brutto PLN |
| --- | ---: |
| Cloudswift 4 | 849,00 |
| Move Training Mat | 229,00 |
| Pure Aero 98 | 1099,00 |
| Trail Shell Jacket | 649,00 |
| Aero Run Shorts | 199,00 |
| Kettlebell 12 kg | 249,00 |
| Court Backpack | 349,00 |
| Alpine Daypack | 499,00 |

Migracja ustawia te kwoty dla istniejących demonstracyjnych produktów i ich
domyślnych wariantów bez resetowania zapasów; nie trzeba ponownie uruchamiać
seedera. Są to ceny demonstracyjne, a nie bieżące oferty producentów.
Produkty bez `price_cents_pln` zachowują dotychczasową wycenę EUR netto
z przeliczeniem na PLN i dodaniem VAT. Bez kursu ich cena jest niedostępna.
Sortowanie porównuje ceny PLN; produkty bez możliwej wyceny trafiają na koniec.
Historyczne zamówienia zachowują zapisane kwoty i waluty.

Przykład ustawienia ceny 299,00 PLN brutto:

```php
$product->update(['price_cents_pln' => 29900]);
$variant->update(['price_cents_pln' => null]); // cena produktu
```

Dostawa standardowa kosztuje 29,00 PLN brutto (bezpłatna od 299,00 PLN),
a ekspresowa 59,00 PLN brutto. Ustawienia `commerce.delivery.*_gross_pln_cents`
można zmienić przez odpowiednie zmienne `DELIVERY_*_GROSS_PLN_CENTS`.
Adres dostawy nie zmienia waluty zamówienia PLN.

## Weryfikacja

```sh
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
npm run build
```

`ProductCatalogTest` obejmuje hierarchię, paginację, słowniki, galerię,
parametry, dostępność, integrację z koszykiem, migrację i rollback marek,
błędy wyceny oraz powtarzalność seedera. `FixedPlnPricingTest` sprawdza ceny
bez kursów walut, warianty, koszyk,
zamówienia, sortowanie mieszanych cen i migrację cen demonstracyjnych.
Testy działają na SQLite w pamięci; nie należy resetować lokalnej bazy
w celu uruchomienia testów.
