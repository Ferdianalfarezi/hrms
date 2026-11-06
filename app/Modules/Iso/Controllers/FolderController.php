<?php

namespace App\Modules\Iso\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Iso\Controllers\Concerns\HandlesIsoAccess;
use App\Modules\Iso\Models\IsoDocument;
use App\Modules\Iso\Models\IsoFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Folder di menu ISO. Subfolder & file di dalam folder selalu ikut departemen folder paling atas-nya.
 */
class FolderController extends Controller
{
    use HandlesIsoAccess;

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('iso-documents');

        $kategori = $this->kategori($request);
        $user = $request->user();
        $data = $request->validate($this->rules(), $this->messages());

        $parent = $this->targetFolder($request, $kategori, $data['parent_id'] ?? null);

        $folder = IsoFolder::create([
            'kategori' => $kategori,
            'parent_id' => $parent?->id,
            'nama' => Str::squish($data['nama']),
            'department_id' => $this->departmentIn($parent, $user, $data['department_id'] ?? null),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route("iso.{$kategori}.index", $this->keptFilters($request))
            ->with('status', "Folder {$folder->nama} berhasil dibuat.");
    }

    /**
     * Ganti nama. Departemen hanya bisa diganti di folder paling atas, dan ikut mengubah semua isinya.
     */
    public function update(Request $request, IsoFolder $folder): RedirectResponse
    {
        $kategori = $this->authorizeItem($request, $folder, manage: true);
        $user = $request->user();
        $data = $request->validate($this->rules(), $this->messages());

        DB::transaction(function () use ($folder, $user, $data) {
            $departmentId = $folder->parent_id === null
                ? $this->departmentFor($user, $data['department_id'] ?? null)
                : $folder->department_id;

            if ($departmentId !== $folder->department_id) {
                $ids = $folder->selfAndDescendantIds();
                IsoFolder::whereIn('id', $ids)->update(['department_id' => $departmentId]);
                IsoDocument::whereIn('folder_id', $ids)->update(['department_id' => $departmentId]);
            }

            $folder->update(['nama' => Str::squish($data['nama']), 'department_id' => $departmentId, 'updated_by' => $user->id]);
        });

        return redirect()->route("iso.{$kategori}.index", $this->keptFilters($request))
            ->with('status', "Folder {$folder->nama} berhasil diperbarui.");
    }

    /**
     * Hapus folder beserta semua subfolder & file di dalamnya (file di disk ikut dihapus).
     */
    public function destroy(Request $request, IsoFolder $folder): RedirectResponse
    {
        $kategori = $this->authorizeItem($request, $folder, manage: true);

        $ids = $folder->selfAndDescendantIds();
        // Admin Departemen tidak melihat dokumen nonaktif, jadi tidak boleh ikut menghapusnya tanpa sadar.
        abort_if(! $request->user()->isModuleAdmin('iso') && IsoDocument::whereIn('folder_id', $ids)->where('is_active', false)->exists(), 403,
            'Folder ini berisi dokumen nonaktif. Hanya Admin ISO yang bisa menghapusnya.');
        $paths = IsoDocument::whereIn('folder_id', $ids)->pluck('path')->all();

        DB::transaction(function () use ($ids) {
            IsoDocument::whereIn('folder_id', $ids)->delete();
            // Urutan id dari atas ke bawah, jadi dihapus terbalik (subfolder dulu) supaya relasi parent tetap valid.
            foreach (array_reverse($ids) as $id) {
                IsoFolder::whereKey($id)->delete();
            }
        });
        Storage::disk('local')->delete($paths);

        $keep = $this->keptFilters($request);
        // Kalau sedang membuka folder yang dihapus, kembali ke folder induknya.
        if (isset($keep['folder']) && in_array((int) $keep['folder'], $ids, true)) {
            $keep['folder'] = (string) $folder->parent_id;
        }

        return redirect()->route("iso.{$kategori}.index", array_filter($keep))
            ->with('status', "Folder {$folder->nama} beserta isinya (".count($paths).' file) berhasil dihapus.');
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'nama.required' => 'Nama folder wajib diisi.',
            'nama.max' => 'Nama folder maksimal 255 karakter.',
            'department_id.exists' => 'Departemen tidak dikenal.',
        ];
    }
}
