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
        // Rekap kehadiran harian untuk laporan Jepang (file "2026 summary.xlsx"): satu baris per hari kerja.
        // Hadir & rate tidak disimpan, dihitung dari total - cuti - sakit_izin.
        Schema::create('japan_resumes', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('shift', 10)->comment('両直 = dua shift, 一直 / 直 = satu shift (seperti di Excel)');
            $table->unsignedInteger('total')->comment('Jumlah MP yang dijadwalkan masuk');
            $table->unsignedInteger('cuti');
            $table->unsignedInteger('sakit_izin')->comment('Kolom S+I');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('japan_resumes');
    }
};
