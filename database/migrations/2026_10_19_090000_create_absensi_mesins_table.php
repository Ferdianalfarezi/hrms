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
        // Data mesin fingerprint yang diupload Admin HRMS: hanya karyawan yang TIDAK absen di mesin pada hari kerja itu.
        // Dicocokkan ke karyawan lewat NIK (= AC-No di mesin) saat komparasi, jadi tidak menyimpan karyawan_id.
        Schema::create('absensi_mesins', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('nik', 20);
            $table->string('nama')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tanggal', 'nik']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi_mesins');
    }
};
