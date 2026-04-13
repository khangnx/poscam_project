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
            $table->integer('old_stock')->after('quantity')->default(0);
            $table->integer('new_stock')->after('old_stock')->default(0);
            $table->string('reason')->nullable()->after('note');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_stock')->default(5)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn(['old_stock', 'new_stock', 'reason']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_stock')->default(0)->change();
        });
    }
};
