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
        // 1. Update product_recipes table
        Schema::table('product_recipes', function (Blueprint $table) {
            // Drop old foreign key if exists
            $foreignKeys = Schema::getForeignKeys('product_recipes');
            $keyNames = array_map(fn($fk) => $fk['name'], $foreignKeys);

            if (in_array('product_recipes_material_id_foreign', $keyNames)) {
                $table->dropForeign(['material_id']);
            }

            // Re-link to materials table
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
        });

        // 2. Update inventory_logs table
        if (Schema::hasColumn('inventory_logs', 'product_id')) {
            Schema::table('inventory_logs', function (Blueprint $table) {
                try {
                    $table->dropForeign(['product_id']);
                } catch (\Exception $e) {}
                
                $table->renameColumn('product_id', 'material_id');
            });

            Schema::table('inventory_logs', function (Blueprint $table) {
                $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
            });
        }

        // 3. Update stock_import_items table
        if (Schema::hasColumn('stock_import_items', 'product_id')) {
            Schema::table('stock_import_items', function (Blueprint $table) {
                try {
                    $table->dropForeign(['product_id']);
                } catch (\Exception $e) {}
                
                $table->renameColumn('product_id', 'material_id');
            });

            Schema::table('stock_import_items', function (Blueprint $table) {
                $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
            });
        }

        // 4. Cleanup products table
        // First, delete the records that moved to materials
        DB::table('products')->where('type', 'material')->delete();

        // Then, remove redundant columns
        $columnsToDrop = [
            'type',
            'stock',
            'min_stock',
            'purchase_unit',
            'usage_unit',
            'conversion_factor',
            'supplier_id'
        ];

        Schema::table('products', function (Blueprint $table) use ($columnsToDrop) {
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('products', $column)) {
                    // Check for supplier_id foreign key specifically
                    if ($column === 'supplier_id') {
                        $fks = Schema::getForeignKeys('products');
                        if (collect($fks)->pluck('name')->contains('products_supplier_id_foreign')) {
                            $table->dropForeign(['supplier_id']);
                        }
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse is complex, but for safety:
        Schema::table('products', function (Blueprint $table) {
            $table->string('type')->default('product');
            $table->decimal('stock', 15, 4)->default(0);
            $table->decimal('min_stock', 15, 4)->default(0);
            $table->string('purchase_unit')->nullable();
            $table->string('usage_unit')->nullable();
            $table->decimal('conversion_factor', 15, 4)->default(1);
            $table->unsignedBigInteger('supplier_id')->nullable();
        });

        // Note: Down migration won't restore deleted data unless we pull it back from materials.
        // Skipping full reverse logic for brevity as it's a one-way architectural shift.
    }
};
