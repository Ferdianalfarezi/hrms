<?php

namespace App\Modules\Hrms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekap kehadiran harian untuk laporan Jepang (Japan Resume). Data "excel" berasal dari file HRD "2026 summary.xlsx";
 * tanggal setelah data excel terakhir dihitung otomatis dari absensi (lihat Support\JapanResumeBulanan),
 * kecuali admin menyesuaikan angkanya (baris "manual").
 */
#[Fillable(['tanggal', 'shift', 'total', 'cuti', 'sakit_izin', 'sumber', 'updated_by'])]
class JapanResume extends Model
{
    /** Dua shift masuk (pagi + malam). */
    public const SHIFT_DUA = '両直';

    /** Hanya satu shift yang masuk. */
    public const SHIFT_SATU = '一直';

    /** Pilihan shift di form edit (kode lain dari Excel, mis. 両 / 直, tetap bisa dipertahankan). */
    public const SHIFTS = [self::SHIFT_DUA, self::SHIFT_SATU];

    /** Data dari file Excel HRD. */
    public const SUMBER_EXCEL = 'excel';

    /** Angka hari otomatis yang disesuaikan admin. */
    public const SUMBER_MANUAL = 'manual';

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

    /** Admin yang terakhir menyesuaikan angka baris ini. */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
