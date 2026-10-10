<?php

namespace App\Services;

use Illuminate\Http\Request;

final class MarketContext
{
    public function forRequest(Request $request): array
    {
        return $this->make('PL', 'pl');
    }

    public function make(string $country, ?string $locale = null): array
    {
        $country = strtoupper($country);
        $markets = config('commerce.markets');
        $euVat = config('commerce.eu_vat');
        if (! isset($markets[$country]) && ! isset($euVat[$country])) {
            $country = config('commerce.default_country');
        }
        $market = $markets[$country] ?? ['code' => 'eu_global', 'currency' => 'EUR', 'locale' => 'en', 'vat' => $euVat[$country]];
        $locale = in_array($locale, ['en', 'ru', 'pl'], true) ? $locale : $market['locale'];

        return ['country' => $country, 'market' => $market['code'], 'currency' => $market['currency'], 'locale' => $locale, 'vat_rate' => $market['vat']];
    }
}
