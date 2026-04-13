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
        // Move data from reason to note if note is empty
        DB::table('inventory_logs')->whereNotNull('reason')->where(function($query) {
            $query->whereNull('note')->orWhere('note', '');
        })->update(['note' => DB::raw('reason')]);

        // Append reason to note if both are filled
        DB::table('inventory_logs')->whereNotNull('reason')->whereNotNull('note')->where('note', '!=', '')->update([
            'note' => DB::raw("CONCAT(note, ' | ', reason)")
        ]);

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('note');
        });
    }
};
