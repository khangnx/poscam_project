<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add category_id column
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('tenant_id')->index();
        });

        // 2. Migrate existing data
        $products = DB::table('products')->whereNotNull('category')->get();
        foreach ($products as $product) {
            $categoryName = trim($product->category);
            if (empty($categoryName)) continue;

            // Find or create category for this tenant
            $categoryId = DB::table('categories')
                ->where('tenant_id', $product->tenant_id)
                ->where('name', $categoryName)
                ->value('id');

            if (!$categoryId) {
                $categoryId = DB::table('categories')->insertGetId([
                    'tenant_id' => $product->tenant_id,
                    'name' => $categoryName,
                    'slug' => Illuminate\Support\Str::slug($categoryName),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update product
            DB::table('products')->where('id', $product->id)->update(['category_id' => $categoryId]);
        }

        // 3. Drop old category column
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('category_id');
        });

        // Optional: Move data back (not 100% accurate if multiple categories with same name exist)
        $products = DB::table('products')->whereNotNull('category_id')->get();
        foreach ($products as $product) {
            $categoryName = DB::table('categories')
                ->where('id', $product->category_id)
                ->value('name');
            
            DB::table('products')->where('id', $product->id)->update(['category' => $categoryName]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });
    }
};
