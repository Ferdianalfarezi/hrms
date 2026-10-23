<?php

namespace App\Modules\Hrms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan perubahan / penghapusan absensi oleh Admin Departemen.
 * Absensi baru berubah setelah disetujui Admin HRMS (HRD).
 */
#[Fillable([
    'absensi_id', 'karyawan_id',
    'tanggal_lama', 'kode_lama', 'menit_lama', 'keterangan_lama',
    'tanggal_baru', 'kode_baru', 'menit_baru', 'keterangan_baru',
    'alasan', 'evidence_path', 'evidence_name',
    'status', 'requested_by', 'reviewed_by', 'reviewed_at', 'catatan_review',
])]
class AbsensiApproval extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var array<string, array{label: string, tone: string}> */
    public const STATUS = [
        self::STATUS_PENDING => ['label' => 'Menunggu approval HRD', 'tone' => 'amber'],
        self::STATUS_APPROVED => ['label' => 'Disetujui HRD', 'tone' => 'green'],
        self::STATUS_REJECTED => ['label' => 'Ditolak HRD', 'tone' => 'red'],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lama' => 'date',
            'tanggal_baru' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Pengajuan untuk menghapus data absensi (karyawan dianggap masuk). */
    public function isDeletion(): bool
    {
        return $this->kode_baru === null;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status]['label'] ?? $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUS[$this->status]['tone'] ?? 'blue';
    }

    public function evidenceIsImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp)$/i', (string) $this->evidence_path);
    }

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(Absensi::class);
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
