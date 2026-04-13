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
        Schema::table('inventory_logs', function (Blueprint $table) {
            // Drop index on old column if exists
            $table->dropIndex(['product_id']);
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->renameColumn('product_id', 'material_id');
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->index('material_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropIndex(['material_id']);
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->renameColumn('material_id', 'product_id');
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->index('product_id');
        });
    }
};
