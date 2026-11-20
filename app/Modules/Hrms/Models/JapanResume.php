<?php

namespace App\Modules\Hrms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Rekap kehadiran harian untuk laporan Jepang (Japan Resume). Data tersimpan berasal dari file HRD "2026 summary.xlsx";
 * tanggal setelah data terakhir dihitung otomatis dari absensi (lihat Support\JapanResumeBulanan).
 */
#[Fillable(['tanggal', 'shift', 'total', 'cuti', 'sakit_izin'])]
class JapanResume extends Model
{
    /** Dua shift masuk (pagi + malam). */
    public const SHIFT_DUA = '両直';

    /** Hanya satu shift yang masuk. */
    public const SHIFT_SATU = '一直';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'total' => 'integer',
            'cuti' => 'integer',
            'sakit_izin' => 'integer',
        ];
    }
}
