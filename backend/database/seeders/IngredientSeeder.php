<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = \App\Models\Tenant::first()->id;

        // 1. Create Ingredients
        $cafe = \App\Models\Product::create([
            'tenant_id' => $tenantId,
            'name' => 'Cà phê Hạt Robusta',
            'sku' => 'CP-001',
            'type' => 'material',
            'selling_price' => 0,
            'cost_price' => 250, // 250đ per gram
            'stock' => 5000, // 5kg
            'min_stock' => 1000,
            'purchase_unit' => 'Bao 5kg',
            'usage_unit' => 'Gram',
            'conversion_factor' => 5000,
            'status' => 'active'
        ]);

        $sua = \App\Models\Product::create([
            'tenant_id' => $tenantId,
            'name' => 'Sữa đặc Ngôi sao phương nam',
            'sku' => 'SUA-001',
            'type' => 'material',
            'selling_price' => 0,
            'cost_price' => 18000, // 18k per lon
            'stock' => 24, // 24 lon
            'min_stock' => 6,
            'purchase_unit' => 'Thùng 24 lon',
            'usage_unit' => 'Lon',
            'conversion_factor' => 24,
            'status' => 'active'
        ]);

        // 2. Create Finished Product
        $cfs = \App\Models\Product::create([
            'tenant_id' => $tenantId,
            'name' => 'Cà phê Sữa Đá',
            'sku' => 'CFS-01',
            'type' => 'product',
            'selling_price' => 35000,
            'cost_price' => 0, // Will be calculated
            'stock' => 0,
            'status' => 'active'
        ]);

        // 3. Create Recipe
        \App\Models\ProductRecipe::create([
            'tenant_id' => $tenantId,
            'product_id' => $cfs->id,
            'material_id' => $cafe->id,
            'quantity' => 20, // 20g cafe
        ]);

        \App\Models\ProductRecipe::create([
            'tenant_id' => $tenantId,
            'product_id' => $cfs->id,
            'material_id' => $sua->id,
            'quantity' => 0.25, // 0.25 lon sữa
        ]);

        // 4. Trigger cost calculation
        $inventoryService = app(\App\Services\InventoryService::class);
        $inventoryService->calculateRecipeCost($cfs->id);
    }
}
