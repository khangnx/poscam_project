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
        Schema::table('shifts', function (Blueprint $table) {
            $table->decimal('total_cash_sales', 15, 2)->default(0)->after('start_cash');
            $table->decimal('total_non_cash_sales', 15, 2)->default(0)->after('total_cash_sales');
            $table->decimal('total_revenue', 15, 2)->default(0)->after('total_non_cash_sales');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['total_cash_sales', 'total_non_cash_sales', 'total_revenue']);
        });
    }
};
