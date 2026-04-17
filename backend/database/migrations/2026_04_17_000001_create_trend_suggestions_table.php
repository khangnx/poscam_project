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
        Schema::create('trend_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->integer('trend_score')->default(0);
            $table->string('source_url')->nullable();
            $table->string('status')->default('active'); // active, added
            $table->unsignedBigInteger('product_id')->nullable();
            $table->text('recommendation_reason')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trend_suggestions');
    }
};
