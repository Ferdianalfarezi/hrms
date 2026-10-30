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
        // Nominal potongan (rupiah) tidak dipakai lagi; menu Potongan Absen cukup menampilkan durasi terlambat.
        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropColumn('potongan_absen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->unsignedInteger('potongan_absen')->default(50000)->after('group');
        });
    }
};
