<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'product_variants'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->unsignedInteger('price_cents_pln')->nullable());
        }
        // Explicit demonstration prices in PLN including tax, not an FX conversion.
        $prices = [
            'cloudswift-4' => 84900, 'move-training-mat' => 22900,
            'pure-aero-98' => 109900, 'trail-shell-jacket' => 64900,
            'aero-run-shorts' => 19900, 'kettlebell-12kg' => 24900,
            'court-backpack' => 34900, 'alpine-daypack' => 49900,
        ];
        foreach ($prices as $slug => $price) {
            DB::table('products')->where('slug', $slug)->update(['price_cents_pln' => $price]);
            $productId = DB::table('products')->where('slug', $slug)->value('id');
            if ($productId !== null) {
                DB::table('product_variants')->where('product_id', $productId)->where('sku', strtoupper($slug).'-ONE')->update(['price_cents_pln' => $price]);
            }
        }
    }

    public function down(): void
    {
        foreach (['product_variants', 'products'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('price_cents_pln'));
        }
    }
};
