<?php

namespace App\Services;

use App\Domain\Orders\Entities\OrderLine;
use App\Domain\Orders\ValueObjects\DeliveryMethod;
use DomainException;

final class PricingService
{
    public function __construct(private readonly ExchangeRateService $rates) {}

    /** @param list<OrderLine> $lines */
    public function quote(array $lines, array $context, DeliveryMethod $method): array
    {
        $usesFixedPrices = collect($lines)->contains(fn (OrderLine $line) => $line->priceCentsPln !== null);
        if ($usesFixedPrices && $context['currency'] !== 'PLN') {
            throw new DomainException('Stałe ceny PLN wymagają waluty PLN.');
        }
        $needsRate = collect($lines)->contains(fn (OrderLine $line) => $line->priceCentsPln === null);
        $rateInfo = $needsRate ? $this->rates->rate($context['currency']) : ['rate' => 1.0, 'source' => 'fixed_pln'];
        $rate = $rateInfo['rate'];
        $vatMultiplier = 1 + ($context['vat_rate'] / 100);
        $priced = [];
        foreach ($lines as $line) {
            if ($line->priceCentsPln !== null) {
                $grossUnit = $line->priceCentsPln;
                $netUnit = (int) round($grossUnit / $vatMultiplier, 0, PHP_ROUND_HALF_UP);
            } else {
                $netUnit = (int) round($line->unitPriceCents * $rate, 0, PHP_ROUND_HALF_UP);
                $grossUnit = (int) round($netUnit * $vatMultiplier, 0, PHP_ROUND_HALF_UP);
            }
            $priced[] = new OrderLine($line->variantId, $line->productName, $line->variantName, $grossUnit, $line->quantity, $netUnit, ($grossUnit - $netUnit) * $line->quantity);
        }
        $netSubtotal = array_sum(array_map(fn (OrderLine $line) => $line->netUnitPriceCents * $line->quantity, $priced));
        $subtotal = array_sum(array_map(fn (OrderLine $line) => $line->totalCents(), $priced));
        if ($context['currency'] === 'PLN') {
            $threshold = config('commerce.delivery.free_shipping_gross_pln_cents');
            $deliveryGross = $method === DeliveryMethod::Standard && $subtotal >= $threshold
                ? 0 : config('commerce.delivery.'.($method === DeliveryMethod::Express ? 'express_gross_pln_cents' : 'standard_gross_pln_cents'));
            $deliveryNet = (int) round($deliveryGross / $vatMultiplier, 0, PHP_ROUND_HALF_UP);
        } else {
            $threshold = (int) round(config('commerce.delivery.free_shipping_gross_eur_cents') * $rate, 0, PHP_ROUND_HALF_UP);
            $deliveryNet = $method === DeliveryMethod::Standard && $subtotal >= $threshold ? 0 : (int) round(config('commerce.delivery.'.($method === DeliveryMethod::Express ? 'express_net_cents' : 'standard_net_cents')) * $rate, 0, PHP_ROUND_HALF_UP);
            $deliveryGross = (int) round($deliveryNet * $vatMultiplier, 0, PHP_ROUND_HALF_UP);
        }

        return ['lines' => $priced, 'currency' => $context['currency'], 'market' => $context['market'], 'locale' => $context['locale'], 'country' => $context['country'], 'exchange_rate' => $rate, 'rate_source' => $rateInfo['source'], 'vat_rate_basis_points' => (int) round($context['vat_rate'] * 100), 'net_subtotal_cents' => $netSubtotal, 'tax_cents' => $subtotal - $netSubtotal, 'subtotal_cents' => $subtotal, 'delivery_net_cents' => $deliveryNet, 'delivery_tax_cents' => $deliveryGross - $deliveryNet, 'delivery_cents' => $deliveryGross, 'total_cents' => $subtotal + $deliveryGross];
    }

    public function displayPrice(int $netEurCents, array $context, ?int $grossPlnCents = null): array
    {
        if ($grossPlnCents !== null) {
            if ($context['currency'] !== 'PLN') {
                throw new DomainException('Stałe ceny PLN wymagają waluty PLN.');
            }
            $gross = $grossPlnCents;
            $net = (int) round($gross / (1 + $context['vat_rate'] / 100), 0, PHP_ROUND_HALF_UP);
        } else {
            $rateInfo = $this->rates->rate($context['currency']);
            $net = (int) round($netEurCents * $rateInfo['rate'], 0, PHP_ROUND_HALF_UP);
            $gross = (int) round($net * (1 + $context['vat_rate'] / 100), 0, PHP_ROUND_HALF_UP);
        }

        return ['net_cents' => $net, 'gross_cents' => $gross, 'tax_cents' => $gross - $net, 'currency' => $context['currency'], 'vat_rate' => $context['vat_rate']];
    }

    public function legacyPlnGrossMultiplier(array $context): ?float
    {
        try {
            return $this->rates->rate('PLN')['rate'] * (1 + $context['vat_rate'] / 100);
        } catch (DomainException) {
            return null;
        }
    }
}
