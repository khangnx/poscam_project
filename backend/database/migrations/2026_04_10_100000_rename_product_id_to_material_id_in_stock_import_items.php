<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_import_items', function (Blueprint $table) {
            // Drop old foreign key referencing products table
            $table->dropForeign(['product_id']);

            // Rename column
            $table->renameColumn('product_id', 'material_id');
        });

        Schema::table('stock_import_items', function (Blueprint $table) {
            // Re-add foreign key referencing materials table
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_import_items', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->renameColumn('material_id', 'product_id');
        });

        Schema::table('stock_import_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }
};
