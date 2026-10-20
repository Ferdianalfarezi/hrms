<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Support\KomparasiAbsensi;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class AbsensiController extends Controller
{
    /**
     * Laporan absensi bulanan seperti di Excel. Default tanggal = hari ini; kolom tanggal terpilih disorot.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isModuleAdmin('hrms');
        $tanggal = $this->parseDate((string) $request->query('tanggal')) ?? Carbon::today();

        // Admin Departemen mengisi lewat tabel CRUD biasa, bukan matriks bulanan.
        if (! $isAdmin) {
            return $this->entries($request, $tanggal);
        }

        return $this->matrix($request, $tanggal, 'hrms.absensi.index');
    }

    /**
     * Timeline bulanan untuk Admin Departemen: matriks yang sama dengan Admin HRMS, tapi hanya karyawan departemennya.
     */
    public function timeline(Request $request): View|RedirectResponse
    {
        $tanggal = $this->parseDate((string) $request->query('tanggal')) ?? Carbon::today();

        // Admin HRMS sudah punya matriks lengkap di halaman Harian.
        if ($request->user()->isModuleAdmin('hrms')) {
            return redirect()->route('hrms.absensi.index', $request->query());
        }

        return $this->matrix($request, $tanggal, 'hrms.absensi.timeline');
    }

    private function matrix(Request $request, Carbon $tanggal, string $routeName): View
    {
        $user = $request->user();
        $isAdmin = $user->isModuleAdmin('hrms');
        $department = $isAdmin ? (string) $request->query('department') : '';
        $group = (string) $request->query('group');

        $karyawans = Karyawan::query()
            ->visibleTo($user)
            ->with('department')
            ->when($department === 'none', fn ($query) => $query->whereNull('department_id'))
            ->when(ctype_digit($department), fn ($query) => $query->where('department_id', $department))
            ->when($group === 'none', fn ($query) => $query->whereNull('group'))
            ->when($group !== '' && $group !== 'none', fn ($query) => $query->where('group', $group))
            // Urut NIK secara numerik: panjang dulu, jadi 0013 < 1105 < 99394.
            ->orderByRaw('LENGTH(nik)')
            ->orderBy('nik')
            ->get();

        $monthStart = $tanggal->copy()->startOfMonth();
        $monthEnd = $tanggal->copy()->endOfMonth();
        $days = collect(range(1, $monthStart->daysInMonth))->map(function (int $day) use ($monthStart, $tanggal) {
            $date = $monthStart->copy()->day($day);

            return ['day' => $day, 'date' => $date->toDateString(), 'weekday' => $date->translatedFormat('D'),
                'weekend' => $date->isWeekend(), 'selected' => $date->isSameDay($tanggal), 'today' => $date->isToday(),
                // Hari kerja (Senin - Jumat) yang sudah lewat: sel kosong berarti karyawan masuk (ditandai hijau).
                'worked' => ! $date->isWeekend() && $date->lte(Carbon::today())];
        });

        // [karyawan_id][hari] => ['kode' => ..., 'keterangan' => ...]
        $marks = [];
        Absensi::whereIn('karyawan_id', $karyawans->pluck('id'))
            ->whereBetween('tanggal', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get(['karyawan_id', 'tanggal', 'kode', 'keterangan'])
            ->each(function (Absensi $absensi) use (&$marks) {
                $marks[$absensi->karyawan_id][$absensi->tanggal->day] = ['kode' => $absensi->kode, 'keterangan' => $absensi->keterangan];
            });

        $managedDepartmentId = $user->managedDepartmentId();

        return view('hrms.absensi.index', [
            'routeName' => $routeName,
            'tanggal' => $tanggal,
            'karyawans' => $karyawans,
            'days' => $days,
            'marks' => $marks,
            // Mesin absen bilang tidak masuk tapi belum ada kode: [karyawan_id][Y-m-d] => true (sel merah).
            'tanpaKeterangan' => KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $monthStart, $monthEnd),
            // Mesin absen bilang hadir: kode tidak hadir dari admin di sel ini ditampilkan hijau (kemungkinan salah input).
            'hadirMesin' => KomparasiAbsensi::selHadirDiMesin($karyawans, $monthStart, $monthEnd),
            'kodes' => Absensi::KODE,
            'isAdmin' => $isAdmin,
            'canEdit' => $isAdmin || $managedDepartmentId !== null,
            'lockedDepartment' => $managedDepartmentId ? Department::find($managedDepartmentId) : null,
            'missingDepartment' => $user->isDepartmentAdmin() && $managedDepartmentId === null,
            'department' => $department,
            'group' => $group,
            'departments' => $isAdmin ? Department::orderBy('name')->get() : collect(),
            'groupOptions' => Karyawan::query()->visibleTo($user)->whereNotNull('group')->distinct()->orderBy('group')->pluck('group'),
        ]);
    }

    /**
     * Daftar karyawan yang tidak hadir / terlambat pada satu tanggal, untuk Admin Departemen.
     */
    private function entries(Request $request, Carbon $tanggal): View
    {
        $user = $request->user();
        $search = trim((string) $request->query('q'));
        $managedDepartmentId = $user->managedDepartmentId();

        $karyawans = Karyawan::query()->visibleTo($user)->orderByRaw('LENGTH(nik)')->orderBy('nik')->get(['id', 'nik', 'nama', 'group']);

        $absensis = Absensi::query()
            ->with(['karyawan', 'creator'])
            ->whereIn('karyawan_id', $karyawans->pluck('id'))
            ->whereDate('tanggal', $tanggal->toDateString())
            ->when($search !== '', fn ($query) => $query->whereHas('karyawan', fn ($query) => $query
                ->where(fn ($query) => $query->where('nik', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%"))))
            ->get()
            ->sortBy(fn (Absensi $absensi) => [strlen($absensi->karyawan->nik), $absensi->karyawan->nik])
            ->values();

        return view('hrms.absensi.entries', [
            'tanggal' => $tanggal,
            'search' => $search,
            'absensis' => $absensis,
            'karyawans' => $karyawans,
            'kodes' => Absensi::KODE,
            'lockedDepartment' => $managedDepartmentId ? Department::find($managedDepartmentId) : null,
            'missingDepartment' => $user->isDepartmentAdmin() && $managedDepartmentId === null,
            'canEdit' => $managedDepartmentId !== null,
        ]);
    }

    /**
     * Tambah dari tabel CRUD. Bisa satu tanggal atau rentang (mis. cuti beberapa hari); Sabtu & Minggu dilewati.
     * Tanggal yang sudah punya data untuk karyawan itu ditimpa.
     */
    public function storeEntry(Request $request): RedirectResponse
    {
        $data = $this->validateEntry($request, true);
        $karyawan = Karyawan::findOrFail($data['karyawan_id']);
        Gate::authorize('update', $karyawan);

        $mulai = Carbon::createFromFormat('!Y-m-d', $data['tanggal']);
        $sampai = isset($data['sampai']) ? Carbon::createFromFormat('!Y-m-d', $data['sampai']) : $mulai->copy();
        $total = 0;

        for ($day = $mulai->copy(); $day->lte($sampai); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $day->toDateString())->first()
                ?? new Absensi(['karyawan_id' => $karyawan->id, 'tanggal' => $day->toDateString(), 'created_by' => $request->user()->id]);
            $absensi->fill(['kode' => $data['kode'], 'keterangan' => $data['keterangan'] ?? null, 'updated_by' => $request->user()->id])->save();
            $total++;
        }

        return redirect()->route('hrms.absensi.index', ['tanggal' => $data['tanggal']])
            ->with('status', "Absensi {$karyawan->nik} - {$karyawan->nama} disimpan ({$total} hari).");
    }

    public function updateEntry(Request $request, Absensi $absensi): RedirectResponse
    {
        Gate::authorize('update', $absensi->karyawan);
        $data = $this->validateEntry($request, false);

        $bentrok = Absensi::where('karyawan_id', $absensi->karyawan_id)->whereDate('tanggal', $data['tanggal'])->whereKeyNot($absensi->id)->exists();
        if ($bentrok) {
            return back()->withInput()->withErrors(['tanggal' => 'Karyawan ini sudah punya data absensi di tanggal tersebut.']);
        }

        $absensi->fill([...$data, 'updated_by' => $request->user()->id])->save();

        return redirect()->route('hrms.absensi.index', ['tanggal' => $data['tanggal']])
            ->with('status', "Absensi {$absensi->karyawan->nik} - {$absensi->karyawan->nama} diperbarui.");
    }

    public function destroyEntry(Absensi $absensi): RedirectResponse
    {
        Gate::authorize('update', $absensi->karyawan);
        $absensi->delete();

        return redirect()->route('hrms.absensi.index', ['tanggal' => $absensi->tanggal->toDateString()])
            ->with('status', "Absensi {$absensi->karyawan->nik} - {$absensi->karyawan->nama} dihapus (dianggap masuk).");
    }

    private function validateEntry(Request $request, bool $creating): array
    {
        $mulai = $this->parseDate((string) $request->input('tanggal'));

        return $request->validate([
            ...($creating ? [
                'karyawan_id' => ['required', 'integer', 'exists:karyawans,id'],
                'sampai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal',
                    ...($mulai ? ['before_or_equal:'.$mulai->copy()->addDays(30)->toDateString()] : [])],
            ] : []),
            // Admin Departemen hanya mengisi hari kerja (Senin - Jumat).
            'tanggal' => ['required', 'date_format:Y-m-d', function (string $attribute, mixed $value, Closure $fail) {
                if ($this->parseDate((string) $value)?->isWeekend()) {
                    $fail('Absensi hanya bisa diinput untuk hari Senin - Jumat.');
                }
            }],
            'kode' => ['required', Rule::in(array_keys(Absensi::KODE))],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'karyawan_id.required' => 'Pilih karyawan.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'kode.required' => 'Pilih kode absensi.',
            'kode.in' => 'Kode absensi tidak dikenal.',
            'sampai.after_or_equal' => 'Tanggal "sampai" tidak boleh sebelum tanggal mulai.',
            'sampai.before_or_equal' => 'Rentang maksimal 31 hari.',
            'keterangan.max' => 'Keterangan maksimal 255 karakter.',
        ]);
    }

    /**
     * Simpan satu sel (karyawan + tanggal). Kode kosong = hapus tanda (karyawan dianggap masuk).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'karyawan_id' => ['required', 'integer', 'exists:karyawans,id'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'kode' => ['nullable', Rule::in(array_keys(Absensi::KODE))],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'kode.in' => 'Kode absensi tidak dikenal.',
            'keterangan.max' => 'Keterangan maksimal 255 karakter.',
        ]);

        // Admin Departemen hanya boleh mengisi karyawan di departemennya.
        $karyawan = Karyawan::findOrFail($data['karyawan_id']);
        Gate::authorize('update', $karyawan);

        // Sama seperti tabel CRUD: Admin Departemen hanya mengisi hari kerja (Senin - Jumat).
        if (! $request->user()->isModuleAdmin('hrms') && $this->parseDate($data['tanggal'])?->isWeekend()) {
            return response()->json(['message' => 'Absensi hanya bisa diinput untuk hari Senin - Jumat.'], 422);
        }

        $existing = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $data['tanggal'])->first();

        if (empty($data['kode'])) {
            $existing?->delete();

            return response()->json(['kode' => null, 'keterangan' => null]);
        }

        $absensi = $existing ?? new Absensi(['karyawan_id' => $karyawan->id, 'tanggal' => $data['tanggal'], 'created_by' => $request->user()->id]);
        $absensi->fill(['kode' => $data['kode'], 'keterangan' => $data['keterangan'] ?? null, 'updated_by' => $request->user()->id])->save();

        return response()->json(['kode' => $absensi->kode, 'keterangan' => $absensi->keterangan]);
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::createFromFormat('!Y-m-d', $value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
