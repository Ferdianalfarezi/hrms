<?php

namespace App\Modules\Iso\Models\Concerns;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Iso\Models\IsoDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hak akses dokumen & folder ISO berdasarkan kolom department_id (kosong = umum).
 * Admin ISO: lihat & kelola semua. Admin Departemen: lihat departemennya + umum, kelola departemennya saja.
 */
trait HasDepartmentAccess
{
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isModuleAdmin('iso')) {
            return;
        }

        $query->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('department_id'))
            ->orWhereIn($this->qualifyColumn('department_id'), $user->managedDepartmentIds('iso')));
    }

    /**
     * Admin ISO: semua. Admin Departemen: data departemennya, dan hanya di menu yang boleh dikelolanya (Forms & SOP).
     */
    public function isManageableBy(User $user): bool
    {
        return $user->isModuleAdmin('iso')
            || (IsoDocument::canManageKategori($user, $this->kategori) && $user->managesDepartment('iso', $this->department_id));
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
