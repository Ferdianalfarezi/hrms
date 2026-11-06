<?php

namespace App\Modules\Iso\Models;

use App\Modules\Iso\Models\Concerns\HasDepartmentAccess;
use Database\Factories\IsoFolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Folder di menu ISO, bisa bertingkat. Subfolder & file di dalamnya selalu ikut departemen folder ini.
 */
#[UseFactory(IsoFolderFactory::class)]
#[Fillable(['kategori', 'parent_id', 'nama', 'department_id', 'created_by', 'updated_by'])]
class IsoFolder extends Model
{
    use HasDepartmentAccess;

    /** @use HasFactory<IsoFolderFactory> */
    use HasFactory;

    /**
     * Id folder ini beserta semua subfolder di bawahnya (dipakai saat hapus & ganti departemen).
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];

        while ($level !== []) {
            $level = self::whereIn('parent_id', $level)->pluck('id')->all();
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    /**
     * Folder induk dari paling atas sampai folder ini, untuk breadcrumb.
     *
     * @return Collection<int, IsoFolder>
     */
    public function pathFromRoot(): Collection
    {
        $path = collect([$this]);
        $folder = $this;

        while ($folder->parent_id !== null && ($folder = $folder->parent) !== null) {
            $path->prepend($folder);
        }

        return $path;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(IsoDocument::class, 'folder_id');
    }
}
