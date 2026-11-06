<?php

namespace App\Modules\Iso\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hrms\Models\Department;
use App\Modules\Iso\Models\IsoDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Dokumen ISO (Procedures, Forms, SOP) dalam bentuk kartu seperti Google Drive. Ketiga menu memakai controller ini.
 * Admin ISO mengelola semua dokumen; Admin Departemen mengelola dokumen departemennya dan melihat dokumen umum.
 */
class DocumentController extends Controller
{
    private const DISK = 'local';

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

        $documents = IsoDocument::query()
            ->visibleTo($user)
            ->with(['department', 'creator'])
            ->where('kategori', $kategori)
            ->when($search !== '', fn ($query) => $query->where('nama', 'like', "%{$search}%"))
            ->when(isset(IsoDocument::JENIS[$jenis]), fn ($query) => $query->whereIn('extension', IsoDocument::JENIS[$jenis]['extensions']))
            ->when($jenis === 'lainnya', fn ($query) => $query->where(fn ($query) => $query->whereNull('extension')
                ->orWhereNotIn('extension', array_merge(...array_column(IsoDocument::JENIS, 'extensions')))))
            ->when($department === 'umum', fn ($query) => $query->whereNull('department_id'))
            ->when(ctype_digit($department), fn ($query) => $query->where('department_id', $department))
            ->latest()
            ->orderByDesc('id')
            ->paginate(48)
            ->withQueryString();

        return view('iso.documents.index', [
            'kategori' => $kategori,
            'judul' => IsoDocument::KATEGORI[$kategori],
            'documents' => $documents,
            'search' => $search,
            'jenis' => $jenis,
            'department' => $department,
            'isAdmin' => $isAdmin,
            'canUpload' => $isAdmin || $lockedDepartments->isNotEmpty(),
            'lockedDepartments' => $lockedDepartments,
            // Dihitung sekali untuk tombol Edit/Hapus di tiap kartu (sama dengan IsoDocument::isManageableBy).
            'managedDepartmentIds' => $lockedDepartments->modelKeys(),
            'missingDepartment' => $user->isDepartmentAdmin('iso') && $lockedDepartments->isEmpty(),
            // Pilihan departemen di form upload/edit: Admin ISO semua, Admin Departemen departemennya saja.
            'departments' => $isAdmin ? Department::orderBy('name')->get() : $lockedDepartments,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('iso-documents');

        $kategori = $this->kategori($request);
        $user = $request->user();

        $data = $request->validate([
            'files' => ['required', 'array', 'max:'.IsoDocument::MAX_FILES],
            'files.*' => ['required', 'file', 'max:'.IsoDocument::MAX_KB],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ], $this->messages());

        $departmentId = $this->departmentFor($user, $data['department_id'] ?? null);

        $paths = [];
        try {
            DB::transaction(function () use ($request, $kategori, $departmentId, $user, &$paths) {
                foreach ($request->file('files') as $file) {
                    // Nama file di disk dibuat acak oleh Laravel; nama asli hanya disimpan di database.
                    $paths[] = $path = $file->store("iso/{$kategori}", self::DISK);

                    IsoDocument::create([
                        'kategori' => $kategori,
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

        return redirect()->route("iso.{$kategori}.index", $this->keptFilters($request))
            ->with('status', count($paths).' file berhasil diupload ke '.IsoDocument::KATEGORI[$kategori].'.');
    }

    /**
     * Ganti nama, pindah departemen, dan/atau ganti file (file lama dihapus).
     */
    public function update(Request $request, IsoDocument $document): RedirectResponse
    {
        $kategori = $this->authorizeDocument($request, $document, manage: true);
        $user = $request->user();

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'file' => ['nullable', 'file', 'max:'.IsoDocument::MAX_KB],
        ], $this->messages());

        $attributes = [
            'department_id' => $this->departmentFor($user, $data['department_id'] ?? null),
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
        }

        return redirect()->route("iso.{$kategori}.index", $this->keptFilters($request))
            ->with('status', "Dokumen {$document->nama} berhasil diperbarui.");
    }

    public function destroy(Request $request, IsoDocument $document): RedirectResponse
    {
        $kategori = $this->authorizeDocument($request, $document, manage: true);

        $document->delete();
        Storage::disk(self::DISK)->delete($document->path);

        return redirect()->route("iso.{$kategori}.index", $this->keptFilters($request))
            ->with('status', "Dokumen {$document->nama} berhasil dihapus.");
    }

    /**
     * PDF & gambar dibuka di browser; jenis lain (atau ?download=1) selalu diunduh supaya file seperti HTML/SVG tidak dijalankan.
     */
    public function file(Request $request, IsoDocument $document): StreamedResponse
    {
        $this->authorizeDocument($request, $document, manage: false);
        abort_unless(Storage::disk(self::DISK)->exists($document->path), 404, 'File tidak ditemukan.');

        $inline = $document->isPreviewable() && ! $request->boolean('download');

        return Storage::disk(self::DISK)->response($document->path, $document->nama, [
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }

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
     * Dokumen harus berada di menu yang dibuka dan terlihat oleh user; ubah/hapus butuh hak kelola.
     */
    private function authorizeDocument(Request $request, IsoDocument $document, bool $manage): string
    {
        Gate::authorize('iso-documents');

        $kategori = $this->kategori($request);
        $user = $request->user();
        abort_unless($document->kategori === $kategori, 404);
        abort_unless(IsoDocument::query()->visibleTo($user)->whereKey($document->id)->exists(), 403, 'Dokumen ini milik departemen lain.');

        if ($manage) {
            abort_unless($document->isManageableBy($user), 403, 'Dokumen umum hanya bisa diubah oleh Admin ISO.');
        }

        return $kategori;
    }

    /**
     * Admin ISO bebas memilih (kosong = dokumen umum). Admin Departemen wajib salah satu departemennya;
     * kalau hanya punya satu departemen atau tidak memilih, dipakai departemen pertamanya.
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
     * Filter yang dikirim form (keep[...]) supaya kembali ke tampilan yang sama setelah simpan.
     *
     * @return array<string, string>
     */
    private function keptFilters(Request $request): array
    {
        return collect((array) $request->input('keep'))
            ->only(['q', 'jenis', 'department', 'page'])
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->all();
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
