<?php

namespace App\Modules\Sms\Controllers\Concerns;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bersama Daily Patrol, Cross Patrol & Kecelakaan: Admin SMS melihat, menambah, mengedit & menghapus semua departemen;
 * Admin Departemen hanya melihat & mengedit data departemennya. Temuan baru Open; jadi Closed lewat edit setelah ada foto perbaikan.
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
     * Tambah & hapus data hanya untuk Admin SMS; Admin Departemen cukup mengedit data departemennya.
     */
    private function authorizeManage(User $user): void
    {
        Gate::authorize('sms-patrol');

        abort_unless($user->isModuleAdmin('sms'), 403, 'Admin Departemen hanya bisa mengedit data, tidak bisa menambah atau menghapus.');
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
            // Tombol Tambah & Hapus hanya untuk Admin SMS.
            'canManage' => $isAdmin,
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

    /**
     * Pilihan karyawan untuk field PIC / PIC Patrol / Patroler (dropdown multi-pilih dengan pencarian).
     *
     * @return list<array{nama: string, info: string}>
     */
    private function karyawanOptions(): array
    {
        return Karyawan::query()
            ->with('department:id,name')
            ->orderBy('nama')
            ->get(['id', 'nik', 'nama', 'department_id'])
            ->map(fn (Karyawan $karyawan) => [
                'nama' => $karyawan->nama,
                'info' => $karyawan->nik.' · '.($karyawan->department?->name ?? '-'),
            ])
            ->all();
    }

    /**
     * Rule field nama karyawan (bisa lebih dari 1, dipisah koma): tiap nama harus ada di data karyawan.
     * Nama yang sudah tersimpan di temuan (data lama yang diketik manual) tetap boleh dipertahankan saat edit.
     */
    private function karyawanNamesRules(?string $current): array
    {
        return ['required', 'string', 'max:2000', function (string $attribute, mixed $value, Closure $fail) use ($current) {
            $names = $this->splitNames((string) $value);
            $known = Karyawan::whereIn('nama', $names)->pluck('nama')->all();
            $unknown = array_diff($names, $known, $this->splitNames((string) $current));

            if ($unknown !== []) {
                $fail('Karyawan tidak ditemukan: '.implode(', ', $unknown).'.');
            }
        }];
    }

    /**
     * Rapikan isian nama karyawan menjadi "Nama A, Nama B" (tanpa spasi berlebih / nama dobel).
     */
    private function joinNames(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $data[$field] = implode(', ', $this->splitNames($data[$field]));
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function splitNames(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), 'strlen')));
    }

    abstract private function photoFolder(): string;

    /**
     * @return list<string>
     */
    abstract private function filterKeys(): array;
}
