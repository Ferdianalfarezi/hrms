<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * DT< lama yang belum diisi menitnya dihitung 30 menit, sama seperti aturan input baru.
     */
    public function up(): void
    {
        DB::table('absensis')
            ->where('kode', 'DT<')
            ->where(fn ($query) => $query->whereNull('menit_terlambat')->orWhere('menit_terlambat', 0))
            ->update(['menit_terlambat' => 30]);
    }

    /**
     * Data lama tidak bisa dibedakan dari isian asli 30 menit, jadi tidak dikembalikan.
     */
    public function down(): void
    {
        //
    }
};
