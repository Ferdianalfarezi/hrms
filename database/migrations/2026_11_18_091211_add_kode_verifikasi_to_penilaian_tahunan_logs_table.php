<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Kode verifikasi tanda tangan elektronik (isi QR di kotak Penilai) untuk log kirim / setuju.
     */
    public function up(): void
    {
        Schema::table('penilaian_tahunan_logs', function (Blueprint $table) {
            $table->string('kode_verifikasi', 40)->nullable()->unique()->after('catatan');
        });

        DB::table('penilaian_tahunan_logs')->whereIn('aksi', ['kirim', 'setuju'])->orderBy('id')->each(function (object $log) {
            DB::table('penilaian_tahunan_logs')->where('id', $log->id)->update(['kode_verifikasi' => Str::random(40)]);
        });
    }

    public function down(): void
    {
        Schema::table('penilaian_tahunan_logs', function (Blueprint $table) {
            $table->dropUnique(['kode_verifikasi']);
            $table->dropColumn('kode_verifikasi');
        });
    }
};
