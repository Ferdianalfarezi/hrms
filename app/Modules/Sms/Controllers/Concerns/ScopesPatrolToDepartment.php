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
use Illuminate\Validation\Rule;
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

        // Admin Departemen bisa memegang beberapa departemen: datanya digabung.
        $departmentIds = $user->managedDepartmentIds('sms');

        return $departmentIds === [] ? $query->whereRaw('1 = 0') : $query->whereIn('department_id', $departmentIds);
    }

    private function authorizeRecord(User $user, Model $patrol): void
    {
        Gate::authorize('sms-patrol');

        abort_unless($user->isModuleAdmin('sms') || $user->managesDepartment('sms', $patrol->department_id), 403,
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
     * Admin SMS memilih departemen bebas. Admin Departemen hanya boleh salah satu departemennya;
     * kalau tidak memilih (mis. hanya punya satu departemen), dipakai departemen pertamanya.
     */
    private function departmentFor(User $user, mixed $chosen): int
    {
        if ($user->isModuleAdmin('sms')) {
            return (int) $chosen;
        }

        $departmentIds = $user->managedDepartmentIds('sms');
        abort_if($departmentIds === [], 403, 'Akun Anda belum dipilihkan departemen. Hubungi Super Admin.');

        if (count($departmentIds) === 1 || $chosen === null || $chosen === '') {
            return $departmentIds[0];
        }

        if (! in_array((int) $chosen, $departmentIds, true)) {
            throw ValidationException::withMessages(['department_id' => 'Pilih salah satu departemen yang Anda kelola.']);
        }

        return (int) $chosen;
    }

    /**
     * Data umum untuk view daftar temuan.
     */
    private function accessViewData(User $user): array
    {
        $isAdmin = $user->isModuleAdmin('sms');
        $lockedDepartments = $user->managedDepartmentList('sms');

        return [
            'isAdmin' => $isAdmin,
            'canEdit' => $isAdmin || $lockedDepartments->isNotEmpty(),
            // Tombol Tambah & Hapus hanya untuk Admin SMS.
            'canManage' => $isAdmin,
            // Departemen milik Admin Departemen (bisa lebih dari satu); kosong untuk Admin SMS.
            'lockedDepartments' => $lockedDepartments,
            'missingDepartment' => $user->isDepartmentAdmin('sms') && $lockedDepartments->isEmpty(),
            // Pilihan filter & form: Admin SMS semua departemen, Admin Departemen departemennya saja.
            'departments' => $isAdmin ? Department::orderBy('name')->get() : $lockedDepartments,
            'canFilterDepartment' => $isAdmin || $lockedDepartments->count() > 1,
        ];
    }

    /**
     * Jumlah temuan Open & Close sesuai filter yang sedang dipakai (semua halaman, bukan hanya halaman ini).
     *
     * @return array{open: int, closed: int}
     */
    private function statusCounts(Builder $query): array
    {
        $counts = (clone $query)->toBase()->reorder()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return ['open' => (int) ($counts['open'] ?? 0), 'closed' => (int) ($counts['closed'] ?? 0)];
    }

    /**
     * Simpan foto baru & tentukan status/closed_at/closed_by saat edit. Mengembalikan [data, file lama yang perlu dihapus].
     */
    private function applyPhotosAndStatus(Request $request, Model $patrol, array $data): array
    {
        $oldFiles = [];

        // Admin Departemen hanya boleh mengganti foto perbaikan, bukan foto temuan.
        $fields = $request->user()->isModuleAdmin('sms') ? ['foto', 'foto_perbaikan'] : ['foto_perbaikan'];

        foreach ($fields as $field) {
            if ($request->hasFile($field)) {
                $oldFiles[] = $patrol->{$field};
                $data[$field] = $request->file($field)->store($this->photoFolder(), 'public');
            }
        }

        // Waktu & user penutup dicatat saat pertama kali jadi Close; dikosongkan lagi kalau dibuka kembali.
        $closing = $data['status'] === 'closed';
        $data['closed_at'] = $closing ? ($patrol->closed_at ?? now()) : null;
        $data['closed_by'] = $closing ? ($patrol->closed_at ? $patrol->closed_by : $request->user()->id) : null;

        return [$data, array_filter($oldFiles)];
    }

    /**
     * Edit oleh Admin Departemen: hanya kotak Perbaikan (foto perbaikan & status) yang diterima;
     * field temuan lainnya tetap seperti yang diisi Admin SMS walaupun ikut dikirim.
     *
     * @return array{status: string}
     */
    private function validatedFixOnly(Request $request, Model $patrol): array
    {
        $data = $request->validate([
            'foto_perbaikan' => $this->photoRules(false),
            'status' => ['required', Rule::in([$patrol::STATUS_OPEN, $patrol::STATUS_CLOSED])],
        ], $this->photoMessages(), ['foto_perbaikan' => 'foto perbaikan', 'status' => 'status']);

        $this->ensureCanClose($request, $patrol, $data);

        return ['status' => $data['status']];
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
