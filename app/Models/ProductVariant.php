<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'name_translations', 'sku', 'price_cents_pln', 'price_cents_net', 'stock'];

    protected static function booted(): void
    {
        static::saving(function (self $variant) {
            validator($variant->getAttributes(), [
                'price_cents_pln' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            ])->validate();
        });
    }

    public function plnPriceCents(): ?int
    {
        return $this->price_cents_pln ?? $this->product->price_cents_pln;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return ['price_cents_pln' => 'integer', 'name_translations' => 'array'];
    }

    public function getLocalizedNameAttribute(): string
    {
        return $this->name_translations[app()->getLocale()] ?? $this->name;
    }

    protected $appends = ['localized_name'];
}
