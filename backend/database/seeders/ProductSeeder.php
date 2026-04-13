<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First Tenant (Assuming tenant_id 1 exists)
        $products = [
            [
                'tenant_id' => 1,
                'name' => 'Cà phê đen đá',
                'sku' => 'CF-001',
                'price' => 25000,
                'stock' => 100,
                'category' => 'Cà phê',
                'status' => 'active',
            ],
            [
                'tenant_id' => 1,
                'name' => 'Cà phê sữa đá',
                'sku' => 'CF-002',
                'price' => 29000,
                'stock' => 100,
                'category' => 'Cà phê',
                'status' => 'active',
            ],
            [
                'tenant_id' => 1,
                'name' => 'Sinh tố bơ',
                'sku' => 'ST-001',
                'price' => 45000,
                'stock' => 50,
                'category' => 'Sinh tố',
                'status' => 'active',
            ],
            [
                'tenant_id' => 1,
                'name' => 'Bánh mì thịt',
                'sku' => 'BM-001',
                'price' => 20000,
                'stock' => 30,
                'category' => 'Đồ ăn',
                'status' => 'active',
            ],
        ];

        foreach ($products as $prod) {
            Product::firstOrCreate(['sku' => $prod['sku'], 'tenant_id' => $prod['tenant_id']], $prod);
        }
    }
}
