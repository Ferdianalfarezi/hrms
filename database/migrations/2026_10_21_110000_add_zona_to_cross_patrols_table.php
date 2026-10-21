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
        // Zona 1 - 9 (terpisah dari plant). Nullable supaya data lama tetap valid; wajib diisi lewat form.
        Schema::table('cross_patrols', function (Blueprint $table) {
            $table->unsignedTinyInteger('zona')->nullable()->after('plant');
            $table->index('zona');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cross_patrols', function (Blueprint $table) {
            $table->dropIndex(['zona']);
            $table->dropColumn('zona');
        });
    }
};
