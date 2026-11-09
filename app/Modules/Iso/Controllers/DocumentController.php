<?php

namespace App\Modules\Iso\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Iso\Controllers\Concerns\HandlesIsoAccess;
use App\Modules\Iso\Mail\IsoDocumentUpdated;
use App\Modules\Iso\Models\IsoDocument;
use App\Modules\Iso\Models\IsoFolder;
use App\Modules\Iso\Models\IsoRecipient;
use App\Modules\Iso\Support\IsoThumbnail;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Dokumen ISO (Procedures, Forms, SOP) dalam bentuk kartu & folder seperti Google Drive. Ketiga menu memakai controller ini.
 * Admin ISO mengelola semua dokumen; Admin Departemen mengelola dokumen departemennya dan melihat dokumen umum.
 */
class DocumentController extends Controller
{
    use HandlesIsoAccess;

    private const DISK = 'local';

    /** Batas ukuran file yang boleh dilampirkan ke email notifikasi (10 MB). */
    private const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    /**
     * Isi folder yang dibuka (?folder=ID, kosong = halaman utama menu). Pencarian & filter mencari di folder ini
     * beserta semua subfolder-nya.
     */
    public function index(Request $request): View
    {
        Gate::authorize('iso-documents');

        $kategori = $this->kategori($request);
        $user = $request->user();
        $isAdmin = $user->isModuleAdmin('iso');
        $lockedDepartments = $user->managedDepartmentList('iso');
        $search = trim((string) $request->query('q'));
        $jenis = (string) $request->query('jenis');
        $department = (string) $request->query('department');
        // Filter status hanya untuk Admin ISO; tidak meratakan folder (tetap di folder yang dibuka).
        $status = $isAdmin && in_array($request->query('status'), ['aktif', 'nonaktif'], true) ? $request->query('status') : '';
        $filtering = $search !== '' || $jenis !== '' || $department !== '';

        $folderId = (string) $request->query('folder');
        $currentFolder = ctype_digit($folderId)
            ? IsoFolder::query()->visibleTo($user)->where('kategori', $kategori)->findOrFail((int) $folderId)
            : null;
        // Saat memfilter, cari di folder ini + semua subfolder (di halaman utama: seluruh menu).
        $scopeIds = $filtering && $currentFolder ? $currentFolder->selfAndDescendantIds() : null;

        $departmentFilter = function (Builder $query) use ($department) {
            $query->when($department === 'umum', fn ($query) => $query->whereNull('department_id'))
                ->when(ctype_digit($department), fn ($query) => $query->where('department_id', $department));
        };

        $documents = IsoDocument::query()
            ->visibleTo($user)
            ->with(['department', 'creator', 'folder'])
            ->where('kategori', $kategori)
            ->when(! $filtering, fn ($query) => $query->where('folder_id', $currentFolder?->id))
            ->when($scopeIds, fn ($query) => $query->whereIn('folder_id', $scopeIds))
            ->when($search !== '', fn ($query) => $query->where('nama', 'like', "%{$search}%"))
            ->when(isset(IsoDocument::JENIS[$jenis]), fn ($query) => $query->whereIn('extension', IsoDocument::JENIS[$jenis]['extensions']))
            ->when($jenis === 'lainnya', fn ($query) => $query->where(fn ($query) => $query->whereNull('extension')
                ->orWhereNotIn('extension', array_merge(...array_column(IsoDocument::JENIS, 'extensions')))))
            ->tap($departmentFilter)
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'aktif'))
            ->latest()
            ->orderByDesc('id')
            ->paginate(48)
            ->withQueryString();

        // Filter jenis file hanya berlaku untuk file, jadi folder disembunyikan.
        $folders = $jenis !== '' ? collect() : IsoFolder::query()
            ->visibleTo($user)
            ->with('department')
            // Admin Departemen: jumlah file hanya menghitung dokumen aktif (yang bisa mereka lihat).
            ->withCount(['children', 'documents' => fn ($query) => $query->when(! $isAdmin, fn ($query) => $query->where('is_active', true))])
            ->where('kategori', $kategori)
            ->when(! $filtering, fn ($query) => $query->where('parent_id', $currentFolder?->id))
            ->when($scopeIds, fn ($query) => $query->whereIn('parent_id', $scopeIds))
            ->when($search !== '', fn ($query) => $query->where('nama', 'like', "%{$search}%"))
            ->tap($departmentFilter)
            ->orderBy('nama')
            ->get();

        // Admin Departemen hanya mengelola Forms & SOP; di menu lain (Procedures) semuanya hanya bisa dilihat.
        $canManageKategori = IsoDocument::canManageKategori($user, $kategori) && ($isAdmin || $lockedDepartments->isNotEmpty());
        $managedDepartmentIds = $canManageKategori ? $lockedDepartments->modelKeys() : [];
        $canManageHere = $canManageKategori && (! $currentFolder || $isAdmin || in_array($currentFolder->department_id, $managedDepartmentIds, true));

        return view('iso.documents.index', [
            'kategori' => $kategori,
            'judul' => IsoDocument::KATEGORI[$kategori],
            'documents' => $documents,
            'folders' => $folders,
            'currentFolder' => $currentFolder,
            'breadcrumbs' => $currentFolder?->pathFromRoot() ?? collect(),
            'filtering' => $filtering,
            'search' => $search,
            'jenis' => $jenis,
            'department' => $department,
            'status' => $status,
            'isAdmin' => $isAdmin,
            // Upload & buat folder di lokasi yang sedang dibuka.
            'canManageHere' => $canManageHere,
            // Boleh mengelola sesuatu di menu ini (modal edit/hapus perlu dirender).
            'canManageKategori' => $canManageKategori,
            'lockedDepartments' => $lockedDepartments,
            // Dihitung sekali untuk tombol Edit/Hapus di tiap kartu (sama dengan isManageableBy).
            'managedDepartmentIds' => $managedDepartmentIds,
            'missingDepartment' => $user->isDepartmentAdmin('iso') && $lockedDepartments->isEmpty(),
            // Pilihan departemen di form: Admin ISO semua, Admin Departemen departemennya saja.
            'departments' => $isAdmin ? Department::orderBy('name')->get() : $lockedDepartments,
            'moveTargets' => $this->moveTargets($user, $kategori),
            // Pilihan penerima di modal kirim notifikasi (Admin ISO saja).
            'recipients' => $isAdmin ? IsoRecipient::active()->orderBy('nama')->get(['id', 'nama', 'email']) : collect(),
        ]);
    }

    /**
     * Upload file ke folder yang dibuka. Upload folder dari komputer: tiap file membawa folder_paths[i] (mis. "Produksi/Revisi")
     * dan folder kosong dikirim lewat folders[]; struktur foldernya dibuat (atau dipakai kalau sudah ada) di dalam folder tujuan.
     * Upload folder dikirim bertahap lewat fetch (JSON), jadi sudah_diupload / folder_dibuat dipakai untuk pesan totalnya.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('iso-documents');

        $kategori = $this->kategori($request);
        $this->authorizeKategoriManage($request, $kategori);
        $user = $request->user();

        $data = $request->validate([
            'files' => [Rule::requiredIf(! $request->filled('folders')), 'array', 'max:'.IsoDocument::MAX_FILES],
            'files.*' => ['required', 'file', 'max:'.IsoDocument::MAX_KB],
            'folder_paths' => ['nullable', 'array'],
            'folder_paths.*' => ['nullable', 'string', 'max:2000'],
            'folders' => ['nullable', 'array', 'max:500'],
            'folders.*' => ['nullable', 'string', 'max:2000'],
            'folder_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'sudah_diupload' => ['nullable', 'integer', 'min:0'],
            'folder_dibuat' => ['nullable', 'integer', 'min:0'],
        ], $this->messages());

        $folder = $this->targetFolder($request, $kategori, $data['folder_id'] ?? null);
        $departmentId = $this->departmentIn($folder, $user, $data['department_id'] ?? null);
        $folderPaths = $data['folder_paths'] ?? [];

        $paths = [];
        $createdFolders = 0;
        try {
            DB::transaction(function () use ($request, $kategori, $folder, $departmentId, $user, $folderPaths, $data, &$paths, &$createdFolders) {
                $folderAt = $this->folderResolver($kategori, $folder, $departmentId, $user, $createdFolders);

                foreach ((array) ($data['folders'] ?? []) as $emptyFolder) {
                    $folderAt((string) $emptyFolder);
                }

                foreach ((array) $request->file('files', []) as $index => $file) {
                    $fileFolder = $folderAt((string) ($folderPaths[$index] ?? ''));

                    // Nama file di disk dibuat acak oleh Laravel; nama asli hanya disimpan di database.
                    $paths[] = $path = $file->store("iso/{$kategori}", self::DISK);

                    IsoDocument::create([
                        'kategori' => $kategori,
                        'folder_id' => $fileFolder?->id,
                        'nama' => $this->originalName($file),
                        'path' => $path,
                        'extension' => $this->extensionOf($file->getClientOriginalName()),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'department_id' => $departmentId,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($paths);

            throw $exception;
        }

        $tujuan = $folder ? "folder {$folder->nama}" : IsoDocument::KATEGORI[$kategori];
        $totalFiles = count($paths) + (int) ($data['sudah_diupload'] ?? 0);
        $totalFolders = $createdFolders + (int) ($data['folder_dibuat'] ?? 0);
        $message = $totalFiles.' file'.($totalFolders ? " & {$totalFolders} folder" : '')." berhasil diupload ke {$tujuan}.";

        if ($request->expectsJson()) {
            // Pesan muncul setelah halaman dimuat ulang (batch terakhir yang menang).
            $request->session()->flash('status', $message);

            return response()->json(['uploaded' => count($paths), 'folders_created' => $createdFolders, 'message' => $message]);
        }

        return redirect()->route("iso.{$kategori}.index", $this->keptFilters($request))->with('status', $message);
    }

    /**
     * Pindahkan dokumen ke folder lain (drag & drop kartu file ke kartu folder / breadcrumb). Kosong = keluar dari folder.
     * Di luar folder, departemen dokumen dipertahankan.
     */
    public function move(Request $request, IsoDocument $document): JsonResponse
    {
        $kategori = $this->authorizeItem($request, $document, manage: true);
        $user = $request->user();
        $data = $request->validate(['folder_id' => ['nullable', 'integer']]);

        $folder = $this->targetFolder($request, $kategori, $data['folder_id'] ?? null);
        $document->update([
            'folder_id' => $folder?->id,
            'department_id' => $this->departmentIn($folder, $user, $document->department_id),
            'updated_by' => $user->id,
        ]);

        $tujuan = $folder ? "folder {$folder->nama}" : IsoDocument::KATEGORI[$kategori];
        $message = "{$document->nama} dipindahkan ke {$tujuan}.";
        $request->session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }

    /**
     * Cari / buat folder dari jalur relatif (mis. "Produksi/Revisi") di dalam folder tujuan. Folder yang sudah ada dengan nama
     * & departemen yang sama dipakai lagi, jadi upload bertahap tetap masuk ke folder yang sama.
     *
     * @return Closure(string): ?IsoFolder
     */
    private function folderResolver(string $kategori, ?IsoFolder $base, ?int $departmentId, User $user, int &$createdFolders): Closure
    {
        $cache = ['' => $base];

        return function (string $relativePath) use ($kategori, $base, $departmentId, $user, &$cache, &$createdFolders): ?IsoFolder {
            $segments = collect(explode('/', str_replace('\\', '/', $relativePath)))
                ->map(fn (string $segment) => Str::limit(Str::squish($segment), 255, ''))
                ->reject(fn (string $segment) => in_array($segment, ['', '.', '..'], true))
                ->values();

            $parent = $base;
            $key = '';
            foreach ($segments as $segment) {
                $key .= '/'.Str::lower($segment);

                $parent = $cache[$key] ??= IsoFolder::query()
                    ->where('kategori', $kategori)
                    ->where('parent_id', $parent?->id)
                    ->where('department_id', $departmentId)
                    ->where('nama', $segment)
                    ->first() ?? tap(IsoFolder::create([
                        'kategori' => $kategori,
                        'parent_id' => $parent?->id,
                        'nama' => $segment,
                        'department_id' => $departmentId,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]), function () use (&$createdFolders) {
                        $createdFolders++;
                    });
            }

            return $parent;
        };
    }

    /**
     * Ganti nama, pindah folder/departemen, dan/atau ganti file (file lama dihapus).
     */
    public function update(Request $request, IsoDocument $document): RedirectResponse
    {
        $kategori = $this->authorizeItem($request, $document, manage: true);
        $user = $request->user();

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'folder_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'file' => ['nullable', 'file', 'max:'.IsoDocument::MAX_KB],
        ], $this->messages());

        // Tanpa field folder_id (mis. form lama), dokumen tetap di folder-nya.
        $folder = $request->exists('folder_id') ? $this->targetFolder($request, $kategori, $data['folder_id'] ?? null) : $document->folder;

        $attributes = [
            'folder_id' => $folder?->id,
            'department_id' => $this->departmentIn($folder, $user, $data['department_id'] ?? null),
            'updated_by' => $user->id,
        ];

        $oldPath = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $oldPath = $document->path;
            $attributes += [
                'path' => $file->store("iso/{$kategori}", self::DISK),
                'extension' => $this->extensionOf($file->getClientOriginalName()),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        // Ekstensi dipertahankan supaya file yang diunduh tetap bisa dibuka (mis. "Prosedur" => "Prosedur.pdf").
        $extension = $attributes['extension'] ?? $document->extension;
        $nama = Str::squish($data['nama']);
        $attributes['nama'] = $extension && ! Str::endsWith(Str::lower($nama), '.'.$extension) ? "{$nama}.{$extension}" : $nama;

        $document->update($attributes);

        if ($oldPath) {
            Storage::disk(self::DISK)->delete($oldPath);
            IsoThumbnail::forget($oldPath);
        }

        return redirect()->route("iso.{$kategori}.index", $this->keptFilters($request))
            ->with('status', "Dokumen {$document->nama} berhasil diperbarui.");
    }

    public function destroy(Request $request, IsoDocument $document): RedirectResponse
    {
        $kategori = $this->authorizeItem($request, $document, manage: true);

        $document->delete();
        Storage::disk(self::DISK)->delete($document->path);
        IsoThumbnail::forget($document->path);

        return redirect()->route("iso.{$kategori}.index", $this->keptFilters($request))
            ->with('status', "Dokumen {$document->nama} berhasil dihapus.");
    }

    /**
     * Aktif / nonaktifkan dokumen (khusus Admin ISO). Dokumen nonaktif tidak terlihat oleh Admin Departemen.
     */
    public function status(Request $request, IsoDocument $document): JsonResponse|RedirectResponse
    {
        $kategori = $this->authorizeItem($request, $document, manage: true);
        abort_unless($request->user()->isModuleAdmin('iso'), 403, 'Hanya Admin ISO yang bisa mengubah status dokumen.');

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $document->update(['is_active' => (bool) $data['is_active'], 'updated_by' => $request->user()->id]);
        $label = $document->is_active ? 'aktif' : 'nonaktif';

        if ($request->expectsJson()) {
            return response()->json(['is_active' => $document->is_active, 'message' => "{$document->nama} sekarang {$label}."]);
        }

        return redirect()->route("iso.{$kategori}.index", $this->keptFilters($request))
            ->with('status', "Dokumen {$document->nama} sekarang {$label}.");
    }

    /**
     * Kirim email notifikasi pembaruan ke penerima terpilih (khusus Admin ISO, hanya dokumen aktif & penerima aktif).
     * Satu email per penerima supaya alamat penerima lain tidak terlihat.
     */
    public function notify(Request $request, IsoDocument $document): RedirectResponse
    {
        $kategori = $this->authorizeItem($request, $document, manage: true);
        $user = $request->user();
        abort_unless($user->isModuleAdmin('iso'), 403, 'Hanya Admin ISO yang bisa mengirim notifikasi.');

        if (! $document->is_active) {
            throw ValidationException::withMessages(['notify' => 'Notifikasi hanya bisa dikirim untuk dokumen yang aktif.']);
        }

        $data = $request->validate([
            'recipients' => ['required', 'array'],
            'recipients.*' => ['integer'],
            'pesan' => ['nullable', 'string', 'max:1000'],
            'lampirkan' => ['nullable', 'boolean'],
        ], [
            'recipients.required' => 'Pilih minimal satu penerima.',
            'pesan.max' => 'Catatan maksimal 1000 karakter.',
        ]);

        $lampirkan = (bool) ($data['lampirkan'] ?? false);
        if ($lampirkan && $document->size > self::MAX_ATTACHMENT_BYTES) {
            throw ValidationException::withMessages(['lampirkan' => 'File lebih dari 10 MB, terlalu besar untuk dilampirkan. Kirim tanpa lampiran.']);
        }

        // Penerima nonaktif / tidak dikenal diabaikan walaupun ikut terkirim dari form.
        $recipients = IsoRecipient::active()->whereIn('id', $data['recipients'])->get();
        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['recipients' => 'Tidak ada penerima aktif yang dipilih.']);
        }

        $document->loadMissing(['folder', 'department']);
        $pesan = filled($data['pesan'] ?? null) ? trim($data['pesan']) : null;
        $failed = [];

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email, $recipient->nama)->send(new IsoDocumentUpdated($document, $user, $pesan, $lampirkan));
            } catch (Throwable $exception) {
                report($exception);
                $failed[] = $recipient->email;
            }
        }

        $sent = $recipients->count() - count($failed);
        if ($sent > 0) {
            $document->forceFill(['notified_at' => now(), 'notified_by' => $user->id])->save();
        }

        $redirect = redirect()->route("iso.{$kategori}.index", $this->keptFilters($request));
        if ($failed !== []) {
            $redirect->withErrors(['notify' => 'Gagal mengirim ke: '.implode(', ', $failed).'. Cek pengaturan email server.']);
        }

        return $redirect->with('status', "Notifikasi {$document->nama} terkirim ke {$sent} email.");
    }

    /**
     * PDF & gambar dibuka di browser; jenis lain (atau ?download=1) selalu diunduh supaya file seperti HTML/SVG tidak dijalankan.
     */
    public function file(Request $request, IsoDocument $document): StreamedResponse
    {
        $this->authorizeItem($request, $document, manage: false);
        abort_unless(Storage::disk(self::DISK)->exists($document->path), 404, 'File tidak ditemukan.');

        $inline = $document->isPreviewable() && ! $request->boolean('download');

        return Storage::disk(self::DISK)->response($document->path, $document->nama, [
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }

    /**
     * Thumbnail kecil untuk kartu (gambar & halaman pertama PDF). Dibuat sekali lalu disimpan; URL membawa ?v= waktu
     * perubahan dokumen, jadi boleh di-cache browser lama.
     */
    public function thumbnail(Request $request, IsoDocument $document): StreamedResponse
    {
        $this->authorizeItem($request, $document, manage: false);

        $thumbnail = IsoThumbnail::for($document);
        abort_if($thumbnail === null, 404);

        return Storage::disk(self::DISK)->response($thumbnail, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Folder yang bisa jadi tujuan pindah file: semua folder menu ini yang boleh dikelola user, dengan label jalur lengkap.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    private function moveTargets(User $user, string $kategori): Collection
    {
        if (! IsoDocument::canManageKategori($user, $kategori)) {
            return collect();
        }

        $folders = IsoFolder::query()->visibleTo($user)->where('kategori', $kategori)->get(['id', 'parent_id', 'nama', 'department_id'])->keyBy('id');

        $label = function (IsoFolder $folder) use ($folders): string {
            $parts = [$folder->nama];
            for ($parent = $folders[$folder->parent_id] ?? null; $parent; $parent = $folders[$parent->parent_id] ?? null) {
                array_unshift($parts, $parent->nama);
            }

            return implode(' / ', $parts);
        };

        $isAdmin = $user->isModuleAdmin('iso');
        $managedDepartmentIds = $user->managedDepartmentIds('iso');

        return $folders->filter(fn (IsoFolder $folder) => $isAdmin || in_array($folder->department_id, $managedDepartmentIds, true))
            ->map(fn (IsoFolder $folder) => ['id' => $folder->id, 'label' => $label($folder)])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function originalName(UploadedFile $file): string
    {
        $nama = Str::squish($file->getClientOriginalName());

        return Str::limit($nama !== '' ? $nama : 'Dokumen', 255, '');
    }

    private function extensionOf(string $filename): ?string
    {
        $extension = Str::lower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension !== '' ? Str::limit($extension, 20, '') : null;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        $maxMb = IsoDocument::MAX_KB / 1024;

        return [
            'files.required' => 'Pilih minimal satu file.',
            'files.max' => 'Maksimal '.IsoDocument::MAX_FILES.' file sekali upload.',
            'files.*.file' => 'File gagal diupload, coba lagi.',
            'files.*.uploaded' => "File gagal diupload. Pastikan ukurannya tidak lebih dari {$maxMb} MB.",
            'files.*.max' => "Ukuran tiap file maksimal {$maxMb} MB.",
            'file.uploaded' => "File gagal diupload. Pastikan ukurannya tidak lebih dari {$maxMb} MB.",
            'file.max' => "Ukuran file maksimal {$maxMb} MB.",
            'nama.required' => 'Nama dokumen wajib diisi.',
            'nama.max' => 'Nama dokumen maksimal 255 karakter.',
            'department_id.exists' => 'Departemen tidak dikenal.',
        ];
    }
}
