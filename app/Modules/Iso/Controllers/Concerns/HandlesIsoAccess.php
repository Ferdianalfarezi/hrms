<?php

namespace App\Modules\Iso\Controllers\Concerns;

use App\Models\User;
use App\Modules\Iso\Models\IsoDocument;
use App\Modules\Iso\Models\IsoFolder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Aturan bersama dokumen & folder ISO: kategori dari route, folder tujuan, dan departemen tempat data disimpan.
 */
trait HandlesIsoAccess
{
    /**
     * Kategori diisi dari defaults route (procedures / forms / sop).
     */
    private function kategori(Request $request): string
    {
        $kategori = (string) $request->route('kategori');
        abort_unless(isset(IsoDocument::KATEGORI[$kategori]), 404);

        return $kategori;
    }

    /**
     * Tambah dokumen/folder: Admin ISO semua menu, Admin Departemen hanya Forms & SOP, Guest tidak bisa.
     */
    private function authorizeKategoriManage(Request $request, string $kategori): void
    {
        abort_unless(IsoDocument::canManageKategori($request->user(), $kategori), 403,
            'Anda hanya bisa melihat dokumen di menu '.IsoDocument::KATEGORI[$kategori].'.');
    }

    /**
     * Dokumen/folder harus ada di menu yang dibuka dan terlihat oleh user; ubah/hapus butuh hak kelola.
     */
    private function authorizeItem(Request $request, Model $item, bool $manage): string
    {
        Gate::authorize('iso-documents');

        $kategori = $this->kategori($request);
        $user = $request->user();
        abort_unless($item->kategori === $kategori, 404);
        abort_unless($item::query()->visibleTo($user)->whereKey($item->getKey())->exists(), 403, 'Data ini milik departemen lain.');

        if ($manage) {
            abort_unless($item->isManageableBy($user), 403, 'Data umum hanya bisa diubah oleh Admin ISO.');
        }

        return $kategori;
    }

    /**
     * Folder tujuan (upload, buat subfolder, pindah file). Kosong = di luar folder.
     * Folder harus satu menu, terlihat, dan boleh dikelola user.
     */
    private function targetFolder(Request $request, string $kategori, mixed $folderId): ?IsoFolder
    {
        if ($folderId === null || $folderId === '') {
            return null;
        }

        $folder = IsoFolder::query()->visibleTo($request->user())->where('kategori', $kategori)->find((int) $folderId);
        if (! $folder) {
            throw ValidationException::withMessages(['folder_id' => 'Folder tidak ditemukan.']);
        }

        abort_unless($folder->isManageableBy($request->user()), 403, 'Folder umum hanya bisa diisi oleh Admin ISO.');

        return $folder;
    }

    /**
     * Di dalam folder: departemen selalu ikut folder. Di luar folder: dipilih sesuai hak user.
     */
    private function departmentIn(?IsoFolder $folder, User $user, mixed $chosen): ?int
    {
        return $folder ? $folder->department_id : $this->departmentFor($user, $chosen);
    }

    /**
     * Admin ISO bebas memilih (kosong = umum). Admin Departemen wajib salah satu departemennya;
     * kalau tidak memilih, dipakai departemen pertamanya.
     */
    private function departmentFor(User $user, mixed $chosen): ?int
    {
        if ($user->isModuleAdmin('iso')) {
            return $chosen === null || $chosen === '' ? null : (int) $chosen;
        }

        $departmentIds = $user->managedDepartmentIds('iso');
        abort_if($departmentIds === [], 403, 'Akun Anda belum dipilihkan departemen. Hubungi Super Admin.');

        if ($chosen === null || $chosen === '') {
            return $departmentIds[0];
        }

        if (! in_array((int) $chosen, $departmentIds, true)) {
            throw ValidationException::withMessages(['department_id' => 'Pilih salah satu departemen yang Anda kelola.']);
        }

        return (int) $chosen;
    }

    /**
     * Kembali ke folder & filter yang sama setelah simpan (dikirim form sebagai keep[...]).
     *
     * @return array<string, string>
     */
    private function keptFilters(Request $request): array
    {
        return collect((array) $request->input('keep'))
            ->only(['folder', 'q', 'jenis', 'department', 'status', 'page'])
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->all();
    }
}
