<?php

namespace App\Modules\Sms\Controllers\Concerns;

use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Sms\Support\KaryawanPicker;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
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
            __('Temuan ini milik departemen lain.'));
    }

    /**
     * Tambah & hapus data hanya untuk Admin SMS; Admin Departemen cukup mengedit data departemennya.
     */
    private function authorizeManage(User $user): void
    {
        Gate::authorize('sms-patrol');

        abort_unless($user->isModuleAdmin('sms'), 403, __('Admin Departemen hanya bisa mengedit data, tidak bisa menambah atau menghapus.'));
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
        abort_if($departmentIds === [], 403, __('Akun Anda belum dipilihkan departemen. Hubungi Super Admin.'));

        if (count($departmentIds) === 1 || $chosen === null || $chosen === '') {
            return $departmentIds[0];
        }

        if (! in_array((int) $chosen, $departmentIds, true)) {
            throw ValidationException::withMessages(['department_id' => __('Pilih salah satu departemen yang Anda kelola.')]);
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
     * "overdue" = bagian dari Open yang target perbaikannya sudah lewat.
     *
     * @return array{open: int, closed: int, overdue: int}
     */
    private function statusCounts(Builder $query): array
    {
        $counts = (clone $query)->toBase()->reorder()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return [
            'open' => (int) ($counts['open'] ?? 0),
            'closed' => (int) ($counts['closed'] ?? 0),
            'overdue' => $this->overdueCount($query),
        ];
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
            throw ValidationException::withMessages(['foto_perbaikan' => __('Upload foto perbaikan dulu sebelum status diubah menjadi Close.')]);
        }
    }

    private function deletePhotos(array $paths): void
    {
        Storage::disk('public')->delete(array_values(array_filter($paths)));
    }

    /**
     * Setelah tambah / edit / hapus: kembali ke daftar dengan filter & halaman yang sedang dipakai
     * (dibaca dari URL daftar sebelum submit). Baris yang baru ditambah / diedit disorot.
     */
    private function redirectToList(string $route, string $message, ?Model $highlight = null): RedirectResponse
    {
        return redirect()->route($route, $this->filtersFromPreviousList($route))
            ->with('status', $message)
            ->with('highlight', $highlight?->id);
    }

    /**
     * Query string halaman daftar sebelumnya (filter, pencarian, halaman). Kosong kalau submit bukan dari halaman daftar itu.
     *
     * @return array<string, string>
     */
    private function filtersFromPreviousList(string $route): array
    {
        $previous = url()->previous();
        if (strtok($previous, '?') !== route($route)) {
            return [];
        }

        parse_str((string) parse_url($previous, PHP_URL_QUERY), $query);

        return array_filter($query, fn ($value) => is_string($value) && $value !== '');
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
            'required' => __(':Attribute wajib diisi.'),
            'foto.required' => __('Foto temuan wajib diupload.'),
            'image' => __(':Attribute harus berupa gambar.'),
            'mimes' => __(':Attribute harus berformat JPG, PNG, atau WEBP.'),
            'foto.max' => __('Ukuran foto maksimal 10 MB.'),
            'foto_perbaikan.max' => __('Ukuran foto perbaikan maksimal 10 MB.'),
            'max' => __(':Attribute maksimal :max karakter.'),
            'in' => __(':Attribute tidak dikenal.'),
            'date_format' => __('Format :attribute tidak valid.'),
            'department_id.required' => __('Pilih departemen.'),
        ];
    }

    /**
     * Rule field pilih karyawan (PIC / PIC Patrol / Patroler, bisa lebih dari 1): nilai JSON [{id, nama}] dari form.
     * Tiap ID harus ada di data karyawan. Nama lama tanpa ID (dulu diketik manual) hanya boleh dipertahankan saat edit.
     */
    private function karyawanRules(?Model $patrol, string $field): array
    {
        return ['required', 'string', 'max:20000', function (string $attribute, mixed $value, Closure $fail) use ($patrol, $field) {
            $items = KaryawanPicker::decode($value);
            if ($items === null) {
                $fail(__('Format :attribute tidak valid.'));

                return;
            }
            if ($items === []) {
                $fail(__(':Attribute wajib diisi.'));

                return;
            }

            $ids = array_filter(array_column($items, 'id'));
            $known = Karyawan::whereIn('id', $ids)->pluck('id')->all();
            $legacy = KaryawanPicker::legacyNames($patrol, $field);
            $unknown = collect($items)
                ->filter(fn (array $item) => $item['id'] !== null ? ! in_array($item['id'], $known, true) : ! in_array($item['nama'], $legacy, true))
                ->pluck('nama')->unique()->all();

            if ($unknown !== []) {
                $fail(__('Karyawan tidak ditemukan: ').implode(', ', $unknown).'.');
            }
        }];
    }

    /**
     * Ubah isian pilih karyawan yang sudah lolos validasi menjadi {field}_ids (ID karyawan)
     * dan {field} (label nama terbaru dari data karyawan, dipisah koma, tanpa dobel).
     */
    private function resolveKaryawan(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $items = KaryawanPicker::decode($data[$field]) ?? [];
            $ids = array_values(array_unique(array_filter(array_column($items, 'id'))));
            $names = Karyawan::whereIn('id', $ids)->pluck('nama', 'id');

            $data[$field.'_ids'] = $ids;
            $data[$field] = implode(', ', array_unique(array_map(
                fn (array $item) => $item['id'] !== null ? $names[$item['id']] : $item['nama'],
                $items,
            )));
        }

        return $data;
    }

    /**
     * Temuan Open yang target perbaikannya sudah lewat, dihitung dari filter yang sedang dipakai.
     */
    private function overdueCount(Builder $query): int
    {
        return (clone $query)->reorder()->overdue()->count();
    }

    /**
     * Filter status: Open, Close, atau "overdue" (Open lewat target).
     */
    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'open', 'closed' => $query->where('status', $status),
            'overdue' => $query->overdue(),
            default => $query,
        };
    }

    abstract private function photoFolder(): string;
}
