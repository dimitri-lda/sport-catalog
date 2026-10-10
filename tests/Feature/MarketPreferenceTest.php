<?php

namespace Tests\Feature;

use App\Services\MarketContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_preferences_endpoint_is_removed_from_the_single_market_storefront(): void
    {
        $this->post('/preferences', ['country' => 'BY', 'locale' => 'ru'])->assertNotFound();
    }

    public function test_market_context_uses_polish_currency_and_tax_for_poland(): void
    {
        $context = app(MarketContext::class)->make('PL');

        $this->assertSame('poland', $context['market']);
        $this->assertSame('PLN', $context['currency']);
        $this->assertSame('pl', $context['locale']);
        $this->assertSame(23.0, $context['vat_rate']);
    }
}
