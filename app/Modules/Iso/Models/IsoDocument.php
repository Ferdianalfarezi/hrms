<?php

namespace App\Modules\Iso\Models;

use App\Models\User;
use App\Modules\Iso\Models\Concerns\HasDepartmentAccess;
use Database\Factories\IsoDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;

#[UseFactory(IsoDocumentFactory::class)]
#[Fillable(['kategori', 'folder_id', 'nama', 'path', 'extension', 'mime', 'size', 'is_active', 'notified_at', 'notified_by', 'department_id', 'created_by', 'updated_by'])]
class IsoDocument extends Model
{
    use HasDepartmentAccess {
        scopeVisibleTo as scopeVisibleByDepartment;
    }

    /** @use HasFactory<IsoDocumentFactory> */
    use HasFactory;

    /** Menu modul ISO. Ketiganya sementara punya fungsi yang sama. Kunci = segmen URL & nilai kolom kategori. */
    public const KATEGORI = [
        'procedures' => 'Procedures',
        'forms' => 'Forms',
        'sop' => 'SOP',
    ];

    /** Menu yang boleh dikelola (tambah/ubah/hapus) Admin Departemen. Menu lain hanya bisa dilihat. Guest selalu hanya melihat. */
    public const KATEGORI_DEPT_ADMIN = ['forms', 'sop'];

    /**
     * Boleh menambah / mengubah / menghapus isi menu ini: Admin ISO semua menu, Admin Departemen hanya Forms & SOP.
     */
    public static function canManageKategori(User $user, string $kategori): bool
    {
        return $user->isModuleAdmin('iso')
            || ($user->isDepartmentAdmin('iso') && in_array($kategori, self::KATEGORI_DEPT_ADMIN, true));
    }

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Aturan departemen, ditambah: dokumen nonaktif hanya terlihat oleh Admin ISO.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $this->scopeVisibleByDepartment($query, $user);

        if (! $user->isModuleAdmin('iso')) {
            $query->where($this->qualifyColumn('is_active'), true);
        }
    }

    public function jenis(): string
    {
        return self::jenisOf($this->extension);
    }

    public function isPreviewable(): bool
    {
        return in_array($this->extension, self::PREVIEWABLE, true);
    }

    /**
     * Kartu dokumen menampilkan thumbnail kecil untuk PDF & gambar (lihat IsoThumbnail).
     */
    public function hasThumbnail(): bool
    {
        return $this->isPreviewable();
    }

    public function sizeLabel(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(IsoFolder::class);
    }
}
