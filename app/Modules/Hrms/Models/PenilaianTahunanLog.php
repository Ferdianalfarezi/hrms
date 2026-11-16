<?php

namespace App\Modules\Hrms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['penilaian_tahunan_id', 'tahap', 'level', 'aksi', 'user_id', 'catatan'])]
class PenilaianTahunanLog extends Model
{
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
