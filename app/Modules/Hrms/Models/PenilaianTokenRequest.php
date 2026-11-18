<?php

namespace App\Modules\Hrms\Models;

use App\Models\User;
use Database\Factories\PenilaianTokenRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan penambahan token penilaian oleh penilai. Token baru bertambah setelah disetujui Admin HRMS.
 */
#[Fillable(['user_id', 'jumlah', 'alasan', 'status', 'reviewed_by', 'reviewed_at', 'catatan_review'])]
#[UseFactory(PenilaianTokenRequestFactory::class)]
class PenilaianTokenRequest extends Model
{
    /** @use HasFactory<PenilaianTokenRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var array<string, array{label: string, tone: string}> */
    public const STATUS = [
        self::STATUS_PENDING => ['label' => 'Menunggu approval HRD', 'tone' => 'amber'],
        self::STATUS_APPROVED => ['label' => 'Disetujui HRD', 'tone' => 'green'],
        self::STATUS_REJECTED => ['label' => 'Ditolak HRD', 'tone' => 'red'],
    ];

    /** Batas jumlah token per pengajuan. */
    public const MAKS_JUMLAH = 500;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return __(self::STATUS[$this->status]['label'] ?? $this->status);
    }

    public function statusTone(): string
    {
        return self::STATUS[$this->status]['tone'] ?? 'amber';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
