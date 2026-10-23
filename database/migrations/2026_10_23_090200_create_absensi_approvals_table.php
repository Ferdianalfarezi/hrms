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
        // Pengajuan perubahan absensi dari Admin Departemen yang harus disetujui HRD (Admin HRMS).
        Schema::create('absensi_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('karyawan_id')->constrained()->cascadeOnDelete();

            // Data sebelum perubahan (salinan, supaya riwayat tetap terbaca walau absensinya sudah berubah).
            $table->date('tanggal_lama');
            $table->string('kode_lama', 10);
            $table->unsignedSmallInteger('menit_lama')->nullable();
            $table->string('keterangan_lama')->nullable();

            // Data yang diajukan. kode_baru kosong = data dihapus (karyawan dianggap masuk).
            $table->date('tanggal_baru');
            $table->string('kode_baru', 10)->nullable();
            $table->unsignedSmallInteger('menit_baru')->nullable();
            $table->string('keterangan_baru')->nullable();

            $table->text('alasan');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();

            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('catatan_review')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi_approvals');
    }
};
