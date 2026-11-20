<?php

namespace App\Modules\Sms\Models;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tanggal', 'finding', 'foto', 'potensi_bahaya', 'department_id', 'plant', 'rekomendasi', 'resiko', 'kategori',
    'target', 'pic', 'pic_ids', 'pic_patrol', 'pic_patrol_ids', 'foto_perbaikan', 'status', 'closed_at', 'closed_by', 'created_by', 'updated_by'])]
class DailyPatrol extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /**
     * Tingkat resiko. Kode S/H/E (Small/Harmful/Extreme) tetap disimpan di database,
     * yang ditampilkan ke user dalam bahasa Indonesia (sama seperti Cross Patrol).
     */
    public const RESIKO = [
        'S' => ['label' => 'Ringan', 'tone' => 'green'],
        'H' => ['label' => 'Sedang', 'tone' => 'orange'],
        'E' => ['label' => 'Berat', 'tone' => 'red'],
    ];

    public function resikoLabel(): string
    {
        return __(self::RESIKO[$this->resiko]['label'] ?? (string) $this->resiko);
    }

    /** Kategori dikelompokkan seperti di dropdown: Stop 6 dan Other. */
    public const KATEGORI = [
        'Stop 6' => [
            'apparatus' => 'Apparatus (Terjepit/Tergores)',
            'big_heavy' => 'Big Heavy (Tertimpa)',
            'car' => 'Car (Tertabrak)',
            'drop' => 'Drop (Terjatuh)',
            'electrical' => 'Electrical (Tersetrum/Konsleting)',
            'fire' => 'Fire (Kebakaran)',
        ],
        'Other' => [
            'other' => 'Other',
            'ergonomi' => 'Ergonomi/Kesehatan',
            '5r' => '5R',
        ],
    ];

    /**
     * @return array<string, string> kode => label, tanpa grup
     */
    public static function kategoriOptions(): array
    {
        return array_merge(...array_values(self::KATEGORI));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'target' => 'date',
            'closed_at' => 'datetime',
            'pic_ids' => 'array',
            'pic_patrol_ids' => 'array',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Masih Open padahal target perbaikan sudah lewat (hari target sendiri belum dihitung lewat).
     */
    public function isOverdue(): bool
    {
        return ! $this->isClosed() && $this->target !== null && $this->target->lt(today());
    }

    /**
     * Temuan Open yang target perbaikannya sudah lewat.
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN)->whereDate('target', '<', today());
    }

    public function kategoriLabel(): string
    {
        return __(self::kategoriOptions()[$this->kategori] ?? $this->kategori);
    }

    /**
     * URL foto memakai asset() supaya ikut alamat yang sedang dibuka (sama seperti foto profil user).
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn () => asset('storage/'.$this->foto));
    }

    protected function fotoPerbaikanUrl(): Attribute
    {
        return Attribute::get(fn () => $this->foto_perbaikan ? asset('storage/'.$this->foto_perbaikan) : null);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** User yang mengubah status menjadi Close. */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
