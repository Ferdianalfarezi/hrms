<?php

namespace App\Modules\Sms\Models;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tanggal', 'plant', 'zona', 'finding', 'foto', 'efek', 'resiko', 'area', 'department_id', 'rekomendasi', 'target', 'pic',
    'foto_perbaikan', 'kategori', 'patroler', 'status', 'closed_at', 'closed_by', 'created_by', 'updated_by'])]
class CrossPatrol extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /**
     * Tingkat resiko bahaya. Kode A/B/C tetap disimpan di database (A paling berat),
     * yang ditampilkan ke user: Ringan, Sedang, Berat (urutan dropdown dari ringan).
     */
    public const RESIKO = [
        'C' => ['label' => 'Ringan', 'tone' => 'green'],
        'B' => ['label' => 'Sedang', 'tone' => 'orange'],
        'A' => ['label' => 'Berat', 'tone' => 'red'],
    ];

    /** Pilihan zona: Zona 1 - 9. */
    public const ZONA = [1, 2, 3, 4, 5, 6, 7, 8, 9];

    /** Kategori sama dengan Daily Patrol (Stop 6 & Other). */
    public const KATEGORI = DailyPatrol::KATEGORI;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'zona' => 'integer',
            'target' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function resikoLabel(): string
    {
        return self::RESIKO[$this->resiko]['label'] ?? (string) $this->resiko;
    }

    public function zonaLabel(): string
    {
        return $this->zona ? 'Zona '.$this->zona : '-';
    }

    public function kategoriLabel(): string
    {
        return DailyPatrol::kategoriOptions()[$this->kategori] ?? $this->kategori;
    }

    /**
     * Label kategori di Excel seperti form aslinya: "Stop 6 (Electrical)", "5R".
     */
    public function kategoriExportLabel(): string
    {
        $label = self::KATEGORI['Stop 6'][$this->kategori] ?? null;

        return $label ? 'Stop 6 ('.trim(strtok($label, '(')).')' : $this->kategoriLabel();
    }

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

    /** User yang mengubah status menjadi Close. */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
