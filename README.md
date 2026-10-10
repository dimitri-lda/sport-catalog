# Sport Catalog

A sports equipment storefront built with Laravel 13, Inertia.js, React 18,
Tailwind CSS, and Vite. The current implementation focuses on Module 1,
Product Catalog. The storefront uses Polish and displays prices in PLN.

This guide covers the root Laravel/Inertia application. The `symfony-api/`
directory contains a separate application.

## Features

- Product listings with pagination, search, and price sorting.
- Categories and subcategories, including filtering by a category and its descendants.
- Separate brand and manufacturer dictionaries linked to products.
- Product details with descriptions, image galleries, and technical specifications.
- Availability based on variant stock, with unavailable purchase actions disabled.
- Fixed demonstration prices in PLN, including VAT.

Existing cart, checkout, account, and order features are also available.
Catalog data is currently maintained through Eloquent models and seed data;
the catalog administration interface is planned for Module 12.

## Requirements

- PHP 8.3 or later, with the extensions required by Composer and `pdo_sqlite`.
- Composer 2.
- Node.js 22.13 or later and npm, compatible with the locked frontend dependencies.
- Git.

The local setup below uses SQLite. Docker, PostgreSQL, and Redis are not
required for this setup.

## Installation

### 1. Clone the repository and install dependencies

```sh
git clone https://github.com/dimitri-lda/sport-catalog.git
cd sport-catalog
composer install
npm ci --ignore-scripts
cp .env.example .env
```

### 2. Configure the local environment

Edit these values in `.env` for a local HTTP server:

```dotenv
APP_NAME="Sport Catalog"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_LOCALE=pl
APP_FALLBACK_LOCALE=pl

DB_CONNECTION=sqlite

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=log

VITE_APP_NAME="Sport Catalog"
VITE_HTTPS=false
VITE_HOST=127.0.0.1
VITE_PORT=5173
```

Leave `DB_DATABASE` unset to use `database/database.sqlite`. If you set it,
use an absolute path to the SQLite file.

The supplied `.env.example` defaults to HTTPS on `sport-catalog.local`; the
overrides above are needed for the HTTP setup described here. Log mail is
written to `storage/logs/laravel.log` instead of being sent externally.

### 3. Generate the key and create the database

```sh
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

### 4. Load demonstration data

For a new demonstration database:

```sh
php artisan db:seed
```

The seeder creates eight sports products, category hierarchies, brands,
manufacturers, specifications, gallery images, and variants. Court Backpack
is intentionally out of stock. Images, specifications, and prices are
demonstration data. Remote images require an internet connection.

The demo account is `test@example.com` with password `password`.

Re-running the seeder resets demonstration prices and stock. For an existing
database, apply migrations without reseeding to preserve current stock.

### 5. Build the frontend

```sh
npm run build
```

## Running locally

Start the Laravel server from the repository root:

```sh
php artisan serve --host=127.0.0.1 --port=8000
```

Open <http://127.0.0.1:8000>. The catalog is at
<http://127.0.0.1:8000/shop>, and the cart is at
<http://127.0.0.1:8000/cart>. No login is required to browse the catalog.

The server serves the compiled frontend assets. After changing frontend
code, run `npm run build` again. Alternatively, keep this running in another
terminal and refresh the browser after each rebuild:

```sh
npm run build -- --watch
```

If you use features that dispatch queued jobs, start a worker in another terminal:

```sh
php artisan queue:work
```

The repository also includes a Sail/Caddy stack for local HTTPS on
`sport-catalog.local` and `admin.sport-catalog.local`. It requires its own database,
hostname, and certificate configuration; see
[local certificate instructions](docker/certs/README.md).
The Vite development server is configured for those custom domains.

## Updating an existing installation

After checking out updated code:

```sh
composer install
npm ci --ignore-scripts
php artisan config:clear
php artisan migrate
npm run build
```

Keep your existing `.env` and application key. The catalog migrations preserve
existing products and historical orders. The PLN price migration assigns new
prices to the known demonstration products without resetting their stock.

## Prices

`price_cents_pln` stores a fixed gross price in PLN grosz, including VAT.
A variant can override its product's price. Fixed PLN prices do not depend
on exchange rates, and VAT is not added again at checkout.

Products without a fixed PLN price retain the legacy EUR net calculation.
Their display price requires an available exchange rate.

Default delivery settings are configurable in `.env`:

```dotenv
DELIVERY_STANDARD_GROSS_PLN_CENTS=2900
DELIVERY_EXPRESS_GROSS_PLN_CENTS=5900
DELIVERY_FREE_SHIPPING_GROSS_PLN_CENTS=29900
```

These represent standard delivery at PLN 29, express delivery at PLN 59,
and free standard delivery for orders of at least PLN 299.

## Verification

Run the backend tests against an isolated SQLite database in memory:

```sh
php artisan config:clear
DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test
```

The explicit database settings keep the test suite separate from your local
application data. The suite covers catalog hierarchy, dictionaries, product
details, availability, pricing, cart and checkout integration, and migrations.

Check PHP formatting and compile the frontend:

```sh
vendor/bin/pint --test
npm run build
```

## Documentation

- [Module 1 functional specification in Polish](docs/module01_product_catalog_pl.docx).
- [Module 1 implementation and data management notes in Polish](docs/module01_product_catalog.md).
