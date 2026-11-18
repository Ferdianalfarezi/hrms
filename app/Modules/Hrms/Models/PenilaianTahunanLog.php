<?php

namespace App\Modules\Hrms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['penilaian_tahunan_id', 'tahap', 'level', 'aksi', 'user_id', 'catatan'])]
class PenilaianTahunanLog extends Model
{
    /**
     * Log kirim / setuju adalah tanda tangan elektronik: diberi kode verifikasi unik untuk QR di kotak Penilai.
     */
    protected static function booted(): void
    {
        static::creating(function (PenilaianTahunanLog $log) {
            if ($log->isTandaTangan()) {
                $log->kode_verifikasi ??= Str::random(40);
            }
        });
    }

    public function isTandaTangan(): bool
    {
        return in_array($this->aksi, [self::AKSI_KIRIM, self::AKSI_SETUJU], true);
    }

    public function penilaian(): BelongsTo
    {
        return $this->belongsTo(PenilaianTahunan::class, 'penilaian_tahunan_id');
    }

    public const AKSI_KIRIM = 'kirim';

    public const AKSI_SETUJU = 'setuju';

    /** Approver mengubah isian penilaian sebelum menyetujui. */
    public const AKSI_UBAH = 'ubah';

    public const AKSI = [
        self::AKSI_KIRIM => 'Dinilai & dikirim',
        self::AKSI_SETUJU => 'Disetujui',
        self::AKSI_UBAH => 'Nilai diubah',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
