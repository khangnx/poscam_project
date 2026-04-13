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
            $table->unsignedBigInteger('order_id')->nullable()->after('product_id')->index();
            
            // Note: DB::statement is used because enum column changes are not directly supported by Schema::table change() 
            // across all drivers easily, but for this project we'll assume a standard approach.
            // However, to be safe and compatible with Laravel 10+, we can use direct SQL if needed.
            // For now, let's just add the column and we will handle the enum update.
        });

        // Updating enum type to include 'sale'
        // This is safe in MySQL. If using SQLite, this might need a more complex approach.
        if (config('database.default') === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE inventory_logs MODIFY COLUMN type ENUM('import', 'export', 'return', 'sale') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE inventory_logs MODIFY COLUMN type ENUM('import', 'export', 'return') NOT NULL");
        }

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
    }
};
