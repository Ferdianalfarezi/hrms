<?php

namespace App\Modules\Iso\Models;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use Database\Factories\IsoDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[UseFactory(IsoDocumentFactory::class)]
#[Fillable(['kategori', 'nama', 'path', 'extension', 'mime', 'size', 'department_id', 'created_by', 'updated_by'])]
class IsoDocument extends Model
{
    /** @use HasFactory<IsoDocumentFactory> */
    use HasFactory;

    /** Menu modul ISO. Ketiganya sementara punya fungsi yang sama. Kunci = segmen URL & nilai kolom kategori. */
    public const KATEGORI = [
        'procedures' => 'Procedures',
        'forms' => 'Forms',
        'sop' => 'SOP',
    ];

    /**
     * Jenis file untuk filter & warna kartu, ditentukan dari ekstensi.
     *
     * @var array<string, array{label: string, tone: string, extensions: list<string>}>
     */
    public const JENIS = [
        'pdf' => ['label' => 'PDF', 'tone' => 'red', 'extensions' => ['pdf']],
        'word' => ['label' => 'Word', 'tone' => 'blue', 'extensions' => ['doc', 'docx', 'docm', 'dot', 'dotx', 'odt', 'rtf']],
        'excel' => ['label' => 'Excel', 'tone' => 'green', 'extensions' => ['xls', 'xlsx', 'xlsm', 'xlsb', 'csv', 'ods']],
        'powerpoint' => ['label' => 'PowerPoint', 'tone' => 'orange', 'extensions' => ['ppt', 'pptx', 'pps', 'ppsx', 'odp']],
        'gambar' => ['label' => 'Gambar', 'tone' => 'purple', 'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']],
    ];

    /** File yang aman dibuka langsung di browser; jenis lain selalu diunduh (mis. HTML/SVG tidak dijalankan di aplikasi). */
    public const PREVIEWABLE = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    /** Batas ukuran satu file (KB) dan jumlah file sekali upload. */
    public const MAX_KB = 51200;

    public const MAX_FILES = 20;

    public static function jenisOf(?string $extension): string
    {
        foreach (self::JENIS as $jenis => $info) {
            if (in_array($extension, $info['extensions'], true)) {
                return $jenis;
            }
        }

        return 'lainnya';
    }

    /**
     * Dokumen yang boleh dilihat: Admin ISO semua; Admin Departemen departemennya + dokumen umum (tanpa departemen).
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isModuleAdmin('iso')) {
            return;
        }

        $query->where(fn (Builder $query) => $query->whereNull('department_id')
            ->orWhereIn('department_id', $user->managedDepartmentIds('iso')));
    }

    /**
     * Ubah / hapus: Admin ISO semua dokumen, Admin Departemen hanya dokumen departemennya (dokumen umum tidak).
     */
    public function isManageableBy(User $user): bool
    {
        return $user->isModuleAdmin('iso') || $user->managesDepartment('iso', $this->department_id);
    }

    public function jenis(): string
    {
        return self::jenisOf($this->extension);
    }

    public function isPreviewable(): bool
    {
        return in_array($this->extension, self::PREVIEWABLE, true);
    }

    public function sizeLabel(): string
    {
        return Number::fileSize($this->size, precision: 1);
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
