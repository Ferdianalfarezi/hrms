<?php

namespace App\Modules\Sms\Controllers\Concerns;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bersama Daily Patrol & Cross Patrol: Admin SMS melihat & mengelola semua departemen,
 * Admin Departemen hanya departemennya. Temuan baru Open; jadi Closed lewat edit setelah ada foto perbaikan.
 */
trait ScopesPatrolToDepartment
{
    private function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->isModuleAdmin('sms')) {
            return $query;
        }

        $departmentId = $user->managedDepartmentId('sms');

        return $departmentId === null ? $query->whereRaw('1 = 0') : $query->where('department_id', $departmentId);
    }

    private function authorizeRecord(User $user, Model $patrol): void
    {
        Gate::authorize('sms-patrol');

        abort_unless($user->isModuleAdmin('sms') || $patrol->department_id === $user->managedDepartmentId('sms'), 403,
            'Temuan ini milik departemen lain.');
    }

    /**
     * Admin Departemen selalu mengisi untuk departemennya sendiri; Admin SMS memilih departemen.
     */
    private function departmentFor(User $user, mixed $chosen): int
    {
        if ($user->isModuleAdmin('sms')) {
            return (int) $chosen;
        }

        $departmentId = $user->managedDepartmentId('sms');
        abort_if($departmentId === null, 403, 'Akun Anda belum dipilihkan departemen. Hubungi Super Admin.');

        return $departmentId;
    }

    /**
     * Data umum untuk view daftar temuan.
     */
    private function accessViewData(User $user): array
    {
        $isAdmin = $user->isModuleAdmin('sms');
        $managedDepartmentId = $user->managedDepartmentId('sms');

        return [
            'isAdmin' => $isAdmin,
            'canEdit' => $isAdmin || $managedDepartmentId !== null,
            'lockedDepartment' => $managedDepartmentId ? Department::find($managedDepartmentId) : null,
            'missingDepartment' => $user->isDepartmentAdmin('sms') && $managedDepartmentId === null,
            'departments' => $isAdmin ? Department::orderBy('name')->get() : collect(),
        ];
    }

    /**
     * Simpan foto baru & tentukan status/closed_at saat edit. Mengembalikan [data, file lama yang perlu dihapus].
     */
    private function applyPhotosAndStatus(Request $request, Model $patrol, array $data): array
    {
        $oldFiles = [];

        foreach (['foto', 'foto_perbaikan'] as $field) {
            if ($request->hasFile($field)) {
                $oldFiles[] = $patrol->{$field};
                $data[$field] = $request->file($field)->store($this->photoFolder(), 'public');
            }
        }

        $data['closed_at'] = $data['status'] === 'closed' ? ($patrol->closed_at ?? now()) : null;

        return [$data, array_filter($oldFiles)];
    }

    /**
     * Close hanya boleh kalau sudah ada foto perbaikan (baru diupload atau sudah tersimpan sebelumnya).
     */
    private function ensureCanClose(Request $request, ?Model $patrol, array $data): void
    {
        if ($patrol && $data['status'] === 'closed' && ! $request->hasFile('foto_perbaikan') && ! $patrol->foto_perbaikan) {
            throw ValidationException::withMessages(['foto_perbaikan' => 'Upload foto perbaikan dulu sebelum status diubah menjadi Close.']);
        }
    }

    private function deletePhotos(array $paths): void
    {
        Storage::disk('public')->delete(array_values(array_filter($paths)));
    }

    /**
     * Filter & halaman daftar yang dikirim form (keep[...]) supaya kembali ke tampilan yang sama setelah simpan.
     */
    private function keptFilters(Request $request): array
    {
        return collect((array) $request->input('keep'))
            ->only($this->filterKeys())
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->all();
    }

    /**
     * Rule upload foto (dikompres di browser dulu, jadi batas 10 MB hanya jaga-jaga).
     */
    private function photoRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'];
    }

    private function photoMessages(): array
    {
        return [
            'required' => ':Attribute wajib diisi.',
            'foto.required' => 'Foto temuan wajib diupload.',
            'image' => ':Attribute harus berupa gambar.',
            'mimes' => ':Attribute harus berformat JPG, PNG, atau WEBP.',
            'foto.max' => 'Ukuran foto maksimal 10 MB.',
            'foto_perbaikan.max' => 'Ukuran foto perbaikan maksimal 10 MB.',
            'max' => ':Attribute maksimal :max karakter.',
            'in' => ':Attribute tidak dikenal.',
            'date_format' => 'Format :attribute tidak valid.',
            'department_id.required' => 'Pilih departemen.',
        ];
    }

    abstract private function photoFolder(): string;

    /**
     * @return list<string>
     */
    abstract private function filterKeys(): array;
}
