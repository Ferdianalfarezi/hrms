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
        // Penilaian akhir tahun: satu karyawan satu penilaian per tahun.
        Schema::create('penilaian_tahunans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');

            // Salinan saat dinilai, supaya riwayat tetap terbaca walau jabatan/departemen karyawan berubah.
            $table->string('jabatan')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('form', 30);

            // Urutan jenjang penilai: [0] = yang mengisi, sisanya approver berurutan.
            $table->json('tahapan');
            $table->unsignedTinyInteger('tahap')->default(0);
            // Jenjang yang sedang ditunggu (mengisi / approve). Null kalau sudah disetujui semua.
            $table->string('posisi_level', 20)->nullable()->index();
            $table->string('status', 20)->default('draft')->index();

            $table->json('absen');
            $table->decimal('nilai_absen', 6, 2)->default(0);
            $table->json('skor')->nullable();
            $table->decimal('nilai_performance', 6, 2)->default(0);
            $table->decimal('nilai_akhir', 6, 2)->default(0);
            $table->string('huruf', 2)->nullable();
            $table->text('catatan')->nullable();
            $table->string('hasil', 30)->nullable();

            $table->foreignId('penilai_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['karyawan_id', 'tahun']);
        });

        // Riwayat keputusan tiap tahap (kirim, setuju, kembalikan untuk revisi).
        Schema::create('penilaian_tahunan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penilaian_tahunan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('tahap');
            $table->string('level', 20);
            $table->string('aksi', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penilaian_tahunan_logs');
        Schema::dropIfExists('penilaian_tahunans');
    }
};
