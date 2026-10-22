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
        // PIC Patrol = orang yang melakukan patroli (beda dengan "pic" yang menangani perbaikan).
        // Nullable supaya data lama tetap valid; wajib diisi lewat form.
        Schema::table('daily_patrols', function (Blueprint $table) {
            $table->string('pic_patrol')->nullable()->after('pic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_patrols', function (Blueprint $table) {
            $table->dropColumn('pic_patrol');
        });
    }
};
