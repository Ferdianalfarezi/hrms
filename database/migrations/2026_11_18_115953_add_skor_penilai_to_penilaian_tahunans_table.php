<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skor per penilai: setiap tahap (penilai & approver) mengisi skornya sendiri, nilai butir = rata-rata semua penilai.
     * Format: {"<tahap>": {"level": "asst_leader", "skor": {kategori: {index: huruf}}}}.
     * Skor lama (satu set) dipindahkan sebagai skor tahap pertama, jadi nilai penilaian yang sudah ada tidak berubah.
     */
    public function up(): void
    {
        Schema::table('penilaian_tahunans', function (Blueprint $table) {
            $table->json('skor_penilai')->nullable()->after('skor');
        });

        DB::table('penilaian_tahunans')->whereNotNull('skor')->orderBy('id')->each(function (object $penilaian) {
            $tahapan = json_decode((string) $penilaian->tahapan, true) ?: [];
            $level = DB::table('penilaian_tahunan_logs')
                ->where('penilaian_tahunan_id', $penilaian->id)
                ->where('aksi', 'kirim')
                ->orderByDesc('id')
                ->value('level') ?? ($tahapan[0] ?? null);

            DB::table('penilaian_tahunans')->where('id', $penilaian->id)->update([
                'skor_penilai' => json_encode(['0' => ['level' => $level, 'skor' => json_decode((string) $penilaian->skor, true) ?: []]]),
            ]);
        });

        Schema::table('penilaian_tahunans', function (Blueprint $table) {
            $table->dropColumn('skor');
        });
    }

    public function down(): void
    {
        Schema::table('penilaian_tahunans', function (Blueprint $table) {
            $table->json('skor')->nullable()->after('nilai_absen');
        });

        // Kembali ke satu set skor: pakai skor penilai terakhir.
        DB::table('penilaian_tahunans')->whereNotNull('skor_penilai')->orderBy('id')->each(function (object $penilaian) {
            $skorPenilai = json_decode((string) $penilaian->skor_penilai, true) ?: [];
            ksort($skorPenilai);
            $terakhir = end($skorPenilai);

            DB::table('penilaian_tahunans')->where('id', $penilaian->id)->update([
                'skor' => json_encode($terakhir['skor'] ?? []),
            ]);
        });

        Schema::table('penilaian_tahunans', function (Blueprint $table) {
            $table->dropColumn('skor_penilai');
        });
    }
};
