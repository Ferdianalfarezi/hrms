<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Imports\KaryawanImport;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KaryawanController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');
        $jabatan = (string) $request->query('jabatan');
        $department = (string) $request->query('department');
        $group = (string) $request->query('group');

        $user = $request->user();
        $isAdmin = $user->isModuleAdmin('hrms');
        $lockedDepartments = $user->managedDepartmentList();
        // Filter departemen: Admin HRMS, atau Admin Departemen yang memegang lebih dari satu departemen.
        $canFilterDepartment = $isAdmin || $lockedDepartments->count() > 1;
        // Admin Departemen: semua query di halaman ini dibatasi ke departemennya.
        $visible = fn () => Karyawan::query()->visibleTo($user);

        $karyawans = $visible()
            ->with('department')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('nik', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%");
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($jabatan !== '', fn ($query) => $query->where('jabatan', $jabatan))
            ->when($group === 'none', fn ($query) => $query->whereNull('group'))
            ->when($group !== '' && $group !== 'none', fn ($query) => $query->where('group', $group))
            ->when($isAdmin && $department === 'none', fn ($query) => $query->whereNull('department_id'))
            ->when($canFilterDepartment && ctype_digit($department), fn ($query) => $query->where('department_id', $department))
            ->orderBy('nik')
            ->paginate(25)
            ->withQueryString();

        return view('hrms.karyawans.index', [
            'karyawans' => $karyawans,
            'search' => $search,
            'status' => $status,
            'jabatan' => $jabatan,
            'department' => $department,
            'group' => $group,
            'groupOptions' => $visible()->whereNotNull('group')->distinct()->orderBy('group')->pluck('group'),
            'isAdmin' => $isAdmin,
            // Departemen milik Admin Departemen (bisa lebih dari satu); kosong untuk admin HRMS / super admin.
            'lockedDepartments' => $lockedDepartments,
            'missingDepartment' => $user->isDepartmentAdmin() && $lockedDepartments->isEmpty(),
            'canFilterDepartment' => $canFilterDepartment,
            // Pilihan filter & form: Admin HRMS semua departemen, Admin Departemen departemennya saja.
            'departments' => $isAdmin ? Department::orderBy('name')->get() : $lockedDepartments,
            'withoutDepartment' => $isAdmin ? Karyawan::whereNull('department_id')->count() : 0,
            'statusCounts' => $visible()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'jabatanOptions' => $visible()->whereNotNull('jabatan')->distinct()->orderBy('jabatan')->pluck('jabatan'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Karyawan::class);

        $karyawan = Karyawan::create($this->validated($request));

        return redirect()->back()->with('status', "Karyawan {$karyawan->nik} - {$karyawan->nama} berhasil ditambahkan.");
    }

    public function update(Request $request, Karyawan $karyawan): RedirectResponse
    {
        Gate::authorize('update', $karyawan);

        $karyawan->update($this->validated($request, $karyawan));

        return redirect()->back()->with('status', "Karyawan {$karyawan->nik} - {$karyawan->nama} berhasil diperbarui.");
    }

    public function destroy(Karyawan $karyawan): RedirectResponse
    {
        Gate::authorize('delete', $karyawan);

        $karyawan->delete();

        return redirect()->back()->with('status', "Karyawan {$karyawan->nik} - {$karyawan->nama} berhasil dihapus.");
    }

    public function import(Request $request, KaryawanImport $importer): RedirectResponse
    {
        Gate::authorize('import', Karyawan::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'file.required' => 'Pilih file Excel terlebih dahulu.',
            'file.mimes' => 'File harus berformat .xlsx.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            $result = $importer->import($request->file('file')->getRealPath());
        } catch (RuntimeException $exception) {
            return redirect()->route('hrms.karyawans.index')->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()->route('hrms.karyawans.index')->with('import_result', $result);
    }

    /**
     * Template Excel kosong dengan header yang dikenali importer.
     */
    public function template(): StreamedResponse
    {
        Gate::authorize('import', Karyawan::class);

        return response()->streamDownload(function () {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $header = (new Style)->withFontBold(true);
            $date = (new Style)->withFormat('dd/mm/yyyy');

            $writer->addRow(Row::fromValuesWithStyle(array_keys(KaryawanImport::COLUMNS), $header));
            $writer->addRow(Row::fromValuesWithStyles(
                ['0001', 'Contoh Nama', 'Laki-laki', 'Bekasi', new \DateTimeImmutable('2000-01-31'), 'Karyawan', new \DateTimeImmutable('2024-07-01'), 'PKWT 1',
                    Department::orderBy('name')->value('name') ?? 'HRD', 'A'],
                [4 => $date, 6 => $date],
            ));
            $writer->close();
        }, 'template-import-karyawan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Karyawan $karyawan = null): array
    {
        $data = $request->validate([
            'nik' => ['required', 'regex:/^\d+$/', 'max:20', Rule::unique('karyawans', 'nik')->ignore($karyawan)],
            'nama' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'jenis_kelamin' => ['required', Rule::in(Karyawan::JENIS_KELAMIN)],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tgl_lahir' => ['nullable', 'date'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'tanggal_masuk' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Karyawan::STATUS)],
            'group' => ['nullable', 'string', 'max:20'],
            'potongan_absen' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], [
            'nik.required' => 'NIK wajib diisi.',
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'nik.unique' => 'NIK ini sudah dipakai karyawan lain.',
            'nama.required' => 'Nama wajib diisi.',
            'department_id.exists' => 'Departemen tidak ditemukan.',
            'jenis_kelamin.required' => 'Pilih jenis kelamin.',
            'jenis_kelamin.in' => 'Jenis kelamin tidak valid.',
            'tgl_lahir.date' => 'Tanggal lahir tidak valid.',
            'tanggal_masuk.date' => 'Tanggal masuk tidak valid.',
            'status.required' => 'Pilih status karyawan.',
            'status.in' => 'Status tidak valid.',
            'potongan_absen.integer' => 'Potongan absen harus berupa angka (rupiah).',
            'potongan_absen.min' => 'Potongan absen tidak boleh minus.',
            'potongan_absen.max' => 'Potongan absen maksimal Rp 10.000.000.',
            'max' => 'Isian terlalu panjang (maks. :max karakter).',
        ]);

        $data['group'] = isset($data['group']) ? Str::upper(trim($data['group'])) : null;

        // Admin Departemen hanya boleh memakai departemen yang dipegangnya; satu departemen = otomatis.
        if (! $request->user()->isModuleAdmin('hrms')) {
            $data['department_id'] = $this->departmentForDepartmentAdmin($request, $data['department_id'] ?? null);
        }

        // Potongan absen hanya diatur Admin HRMS. Kosong = pakai default (karyawan baru) / tidak diubah (edit).
        if (! $request->user()->isModuleAdmin('hrms') || ! isset($data['potongan_absen'])) {
            unset($data['potongan_absen']);
        }

        return $data;
    }

    /**
     * Departemen karyawan yang disimpan Admin Departemen: harus salah satu departemennya.
     * Kalau hanya memegang satu departemen, selalu departemen itu (pilihan lain diabaikan).
     */
    private function departmentForDepartmentAdmin(Request $request, mixed $chosen): ?int
    {
        $departmentIds = $request->user()->managedDepartmentIds();

        if (count($departmentIds) === 1) {
            return $departmentIds[0];
        }

        if ($chosen === null || ! in_array((int) $chosen, $departmentIds, true)) {
            throw ValidationException::withMessages(['department_id' => 'Pilih salah satu departemen yang Anda kelola.']);
        }

        return (int) $chosen;
    }
}
