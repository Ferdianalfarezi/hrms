<?php

namespace App\Modules\Sms\Models;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tanggal', 'finding', 'foto', 'potensi_bahaya', 'department_id', 'plant', 'rekomendasi', 'resiko', 'kategori',
    'target', 'pic', 'foto_perbaikan', 'status', 'closed_at', 'created_by', 'updated_by'])]
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
        return self::RESIKO[$this->resiko]['label'] ?? (string) $this->resiko;
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
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function kategoriLabel(): string
    {
        return self::kategoriOptions()[$this->kategori] ?? $this->kategori;
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
}
