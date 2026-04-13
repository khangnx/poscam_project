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
        // 1. Create materials table
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('purchase_unit')->nullable(); // Thùng, Bao, Can
            $table->string('usage_unit')->nullable();    // g, ml, cái
            $table->decimal('conversion_factor', 15, 4)->default(1); // 1 bao = 5000g
            $table->decimal('cost_price', 15, 2)->default(0); // Giá vốn trung bình (WAC) trên đơn vị SỬ DỤNG
            $table->decimal('stock', 15, 4)->default(0);      // Tồn kho theo đơn vị SỬ DỤNG
            $table->decimal('min_stock', 15, 4)->default(0);
            $table->string('image_path')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });

        // 2. Data Migration: Copy existing materials from products to materials
        $existingMaterials = DB::table('products')->where('type', 'material')->get();
        
        foreach ($existingMaterials as $pm) {
            DB::table('materials')->insert([
                'id'                => $pm->id, // Tạm thời giữ nguyên ID để map sang Recipe dễ dàng hơn sau này
                'tenant_id'         => $pm->tenant_id,
                'supplier_id'      => $pm->supplier_id,
                'category_id'      => $pm->category_id,
                'name'              => $pm->name,
                'sku'               => $pm->sku,
                'purchase_unit'     => $pm->purchase_unit,
                'usage_unit'        => $pm->usage_unit,
                'conversion_factor' => $pm->conversion_factor,
                'cost_price'        => $pm->cost_price,
                'stock'             => $pm->stock,
                'min_stock'         => $pm->min_stock,
                'status'            => $pm->status,
                'created_at'        => $pm->created_at,
                'updated_at'        => $pm->updated_at,
            ]);
        }

        // Lưu ý: Nếu ID bị trùng với ID Sản phẩm (Thành phẩm) thì đoạn trên sẽ lỗi. 
        // Trong Laravel thông thường ID là auto-increment và duy nhất trong bảng. 
        // Vì ta đang lấy từ cùng 1 bảng products thì ID sẽ không bị trùng lặp.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
