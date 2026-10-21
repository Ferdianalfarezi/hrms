<?php

namespace App\Modules\Sms\Models;

use App\Modules\Hrms\Models\Department;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tipe', 'kategori', 'waktu', 'nama_korban', 'department_id', 'deskripsi', 'created_by', 'updated_by'])]
class Kecelakaan extends Model
{
    public const TIPE_WORK = 'work';

    public const TIPE_TRAFFIC = 'traffic';

    public const TIPE = [
        self::TIPE_WORK => ['label' => 'Work Accident', 'tone' => 'orange'],
        self::TIPE_TRAFFIC => ['label' => 'Traffic Accident', 'tone' => 'blue'],
    ];

    /** Pilihan kategori, hanya untuk Traffic Accident. */
    public const KATEGORI_TRAFFIC = [
        'menabrak' => 'Menabrak',
        'ditabrak' => 'Ditabrak',
        'tunggal' => 'Kecelakaan Tunggal',
        'tabrakan' => 'Tabrakan',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waktu' => 'datetime',
        ];
    }

    public function tipeLabel(): string
    {
        return self::TIPE[$this->tipe]['label'] ?? $this->tipe;
    }

    public function kategoriLabel(): ?string
    {
        return $this->kategori ? (self::KATEGORI_TRAFFIC[$this->kategori] ?? $this->kategori) : null;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
