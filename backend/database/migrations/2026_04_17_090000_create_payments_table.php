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
        Schema::create('payments', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('order_id')->constrained()->cascadeOnDelete();
            $blueprint->decimal('amount', 15, 2);
            $blueprint->string('bank_transaction_id')->nullable();
            $blueprint->string('reference_id')->nullable();
            $blueprint->string('status')->default('pending'); // pending, success, failed
            $blueprint->text('description')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
