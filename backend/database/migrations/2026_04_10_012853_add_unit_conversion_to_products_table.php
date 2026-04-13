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
        Schema::table('products', function (Blueprint $table) {
            $table->string('purchase_unit')->nullable()->after('type')->comment('Đơn vị nhập, VD: Thùng, Bao');
            $table->string('usage_unit')->nullable()->after('purchase_unit')->comment('Đơn vị dùng, VD: Lon, Gram, ml');
            $table->decimal('conversion_factor', 10, 4)->default(1)->after('usage_unit')->comment('1 đơn vị nhập = bao nhiêu đơn vị dùng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit', 'usage_unit', 'conversion_factor']);
        });
    }
};
