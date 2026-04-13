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
        Schema::table('cameras', function (Blueprint $table) {
            $table->string('location_note')->nullable()->after('rtsp_url');
            $table->boolean('is_active')->default(true)->after('location_note');
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cameras', function (Blueprint $table) {
            $table->enum('status', ['online', 'offline', 'error'])->default('offline')->after('rtsp_url');
            $table->dropColumn('location_note');
            $table->dropColumn('is_active');
        });
    }
};
