<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiApproval;
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
            ->get(['karyawan_id', 'tanggal', 'kode', 'menit_terlambat', 'keterangan'])
            ->each(function (Absensi $absensi) use (&$marks) {
                $marks[$absensi->karyawan_id][$absensi->tanggal->day] = ['kode' => $absensi->kode, 'menit' => $absensi->menit_terlambat, 'keterangan' => $absensi->keterangan];
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
            ->with(['karyawan', 'creator', 'latestApproval'])
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
     * Admin HRMS menimpa data yang sudah ada; Admin Departemen melewati tanggal yang sudah ada datanya,
     * karena mengubah data lama harus lewat Edit (perlu approval HRD).
     */
    public function storeEntry(Request $request): RedirectResponse
    {
        $data = $this->validateEntry($request, true);
        $karyawan = Karyawan::findOrFail($data['karyawan_id']);
        Gate::authorize('update', $karyawan);

        $user = $request->user();
        $isAdmin = $user->isModuleAdmin('hrms');
        $mulai = Carbon::createFromFormat('!Y-m-d', $data['tanggal']);
        $sampai = isset($data['sampai']) ? Carbon::createFromFormat('!Y-m-d', $data['sampai']) : $mulai->copy();
        $total = 0;
        $skipped = 0;

        for ($day = $mulai->copy(); $day->lte($sampai); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            $absensi = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $day->toDateString())->first();
            if ($absensi && ! $isAdmin) {
                $skipped++;

                continue;
            }

            $absensi ??= new Absensi(['karyawan_id' => $karyawan->id, 'tanggal' => $day->toDateString(), 'created_by' => $user->id]);
            $absensi->fill([
                'kode' => $data['kode'],
                'menit_terlambat' => $data['menit_terlambat'],
                'keterangan' => $data['keterangan'] ?? null,
                'updated_by' => $user->id,
            ])->save();
            $total++;
        }

        $skippedMessage = "{$skipped} hari sudah ada datanya, ubah lewat tombol Edit (perlu approval HRD).";

        if ($total === 0 && $skipped > 0) {
            return back()->withInput()->withErrors(['tanggal' => "Tidak ada yang disimpan: {$skippedMessage}"]);
        }

        return redirect()->route('hrms.absensi.index', ['tanggal' => $data['tanggal']])
            ->with('status', "Absensi {$karyawan->nik} - {$karyawan->nama} disimpan ({$total} hari).".($skipped ? " {$skippedMessage}" : ''));
    }

    /**
     * Admin HRMS langsung mengubah data. Admin Departemen hanya mengajukan perubahan yang menunggu approval HRD.
     */
    public function updateEntry(Request $request, Absensi $absensi): RedirectResponse
    {
        Gate::authorize('update', $absensi->karyawan);
        $isAdmin = $request->user()->isModuleAdmin('hrms');

        if (! $isAdmin && $absensi->pendingApproval()->exists()) {
            return back()->withErrors(['approval' => 'Absensi ini masih punya pengajuan perubahan yang menunggu approval HRD.']);
        }

        $data = $this->validateEntry($request, false, ! $isAdmin);

        $bentrok = Absensi::where('karyawan_id', $absensi->karyawan_id)->whereDate('tanggal', $data['tanggal'])->whereKeyNot($absensi->id)->exists();
        if ($bentrok) {
            return back()->withInput()->withErrors(['tanggal' => 'Karyawan ini sudah punya data absensi di tanggal tersebut.']);
        }

        $karyawan = $absensi->karyawan;

        if ($isAdmin) {
            $absensi->fill([
                'tanggal' => $data['tanggal'],
                'kode' => $data['kode'],
                'menit_terlambat' => $data['menit_terlambat'],
                'keterangan' => $data['keterangan'] ?? null,
                'updated_by' => $request->user()->id,
            ])->save();

            return redirect()->route('hrms.absensi.index', ['tanggal' => $data['tanggal']])
                ->with('status', "Absensi {$karyawan->nik} - {$karyawan->nama} diperbarui.");
        }

        $unchanged = $absensi->tanggal->toDateString() === $data['tanggal']
            && $absensi->kode === $data['kode']
            && $absensi->menit_terlambat === $data['menit_terlambat']
            && (string) $absensi->keterangan === (string) ($data['keterangan'] ?? '')
            && ! $request->hasFile('evidence');
        if ($unchanged) {
            return redirect()->route('hrms.absensi.index', ['tanggal' => $data['tanggal']])
                ->with('status', 'Tidak ada perubahan yang diajukan.');
        }

        $this->ajukanPerubahan($request, $absensi, [
            'tanggal_baru' => $data['tanggal'],
            'kode_baru' => $data['kode'],
            'menit_baru' => $data['menit_terlambat'],
            'keterangan_baru' => $data['keterangan'] ?? null,
            'alasan' => $data['alasan'],
        ]);

        return redirect()->route('hrms.absensi.index', ['tanggal' => $absensi->tanggal->toDateString()])
            ->with('status', "Perubahan absensi {$karyawan->nik} - {$karyawan->nama} diajukan, menunggu approval HRD.");
    }

    /**
     * Admin HRMS langsung menghapus. Admin Departemen mengajukan penghapusan (perlu approval HRD).
     */
    public function destroyEntry(Request $request, Absensi $absensi): RedirectResponse
    {
        Gate::authorize('update', $absensi->karyawan);
        $karyawan = $absensi->karyawan;
        $tanggal = $absensi->tanggal->toDateString();

        if ($request->user()->isModuleAdmin('hrms')) {
            $absensi->delete();

            return redirect()->route('hrms.absensi.index', ['tanggal' => $tanggal])
                ->with('status', "Absensi {$karyawan->nik} - {$karyawan->nama} dihapus (dianggap masuk).");
        }

        if ($absensi->pendingApproval()->exists()) {
            return back()->withErrors(['approval' => 'Absensi ini masih punya pengajuan perubahan yang menunggu approval HRD.']);
        }

        $data = $request->validateWithBag('hapus', [
            'alasan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan penghapusan wajib diisi.',
            'alasan.max' => 'Alasan maksimal 1000 karakter.',
        ]);

        $this->ajukanPerubahan($request, $absensi, [
            'tanggal_baru' => $tanggal,
            'kode_baru' => null,
            'menit_baru' => null,
            'keterangan_baru' => null,
            'alasan' => $data['alasan'],
        ]);

        return redirect()->route('hrms.absensi.index', ['tanggal' => $tanggal])
            ->with('status', "Penghapusan absensi {$karyawan->nik} - {$karyawan->nama} diajukan, menunggu approval HRD.");
    }

    /**
     * Simpan pengajuan perubahan beserta salinan data lama dan file evidence (mis. surat dokter).
     *
     * @param  array{tanggal_baru: string, kode_baru: ?string, menit_baru: ?int, keterangan_baru: ?string, alasan: string}  $baru
     */
    private function ajukanPerubahan(Request $request, Absensi $absensi, array $baru): AbsensiApproval
    {
        $evidence = $request->file('evidence');

        return AbsensiApproval::create([
            ...$baru,
            'absensi_id' => $absensi->id,
            'karyawan_id' => $absensi->karyawan_id,
            'tanggal_lama' => $absensi->tanggal->toDateString(),
            'kode_lama' => $absensi->kode,
            'menit_lama' => $absensi->menit_terlambat,
            'keterangan_lama' => $absensi->keterangan,
            'evidence_path' => $evidence?->store('absensi-approvals', 'local'),
            'evidence_name' => $evidence?->getClientOriginalName(),
            'status' => AbsensiApproval::STATUS_PENDING,
            'requested_by' => $request->user()->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEntry(Request $request, bool $creating, bool $approval = false): array
    {
        $mulai = $this->parseDate((string) $request->input('tanggal'));

        $data = $request->validate([
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
            'menit_terlambat' => $this->menitRules($request),
            'keterangan' => ['nullable', 'string', 'max:255'],
            ...($approval ? [
                'alasan' => ['required', 'string', 'max:1000'],
                'evidence' => [Rule::requiredIf(fn () => Absensi::isSakit($request->input('kode'))), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            ] : []),
        ], [
            'karyawan_id.required' => 'Pilih karyawan.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'kode.required' => 'Pilih kode absensi.',
            'kode.in' => 'Kode absensi tidak dikenal.',
            'sampai.after_or_equal' => 'Tanggal "sampai" tidak boleh sebelum tanggal mulai.',
            'sampai.before_or_equal' => 'Rentang maksimal 31 hari.',
            'keterangan.max' => 'Keterangan maksimal 255 karakter.',
            'alasan.required' => 'Alasan perubahan wajib diisi.',
            'alasan.max' => 'Alasan maksimal 1000 karakter.',
            'evidence.required' => 'Perubahan ke kode sakit wajib melampirkan surat dokter.',
            'evidence.mimes' => 'Evidence harus berupa gambar (JPG/PNG) atau PDF.',
            'evidence.max' => 'Ukuran evidence maksimal 5 MB.',
            ...$this->menitMessages(),
        ]);

        // Menit hanya disimpan untuk kode datang terlambat.
        $data['menit_terlambat'] = Absensi::isTerlambat($data['kode']) ? (int) $data['menit_terlambat'] : null;

        return $data;
    }

    /**
     * Menit terlambat wajib untuk DT< (1 - 30 menit) dan DT> (30 menit ke atas).
     *
     * @return list<mixed>
     */
    private function menitRules(Request $request): array
    {
        return [
            Rule::requiredIf(fn () => Absensi::isTerlambat($request->input('kode'))),
            'nullable', 'integer', 'min:1', 'max:720',
            function (string $attribute, mixed $value, Closure $fail) use ($request) {
                $kode = $request->input('kode');
                if ($kode === 'DT<' && (int) $value > Absensi::MENIT_PER_POTONGAN) {
                    $fail('Kode DT< maksimal 30 menit. Pilih DT> untuk terlambat lebih dari 30 menit.');
                } elseif ($kode === 'DT>' && (int) $value < Absensi::MENIT_PER_POTONGAN) {
                    $fail('Kode DT> minimal 30 menit. Pilih DT< untuk terlambat kurang dari 30 menit.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    private function menitMessages(): array
    {
        return [
            'menit_terlambat.required' => 'Isi berapa menit karyawan datang terlambat.',
            'menit_terlambat.integer' => 'Menit terlambat harus berupa angka.',
            'menit_terlambat.min' => 'Menit terlambat minimal 1 menit.',
            'menit_terlambat.max' => 'Menit terlambat maksimal 720 menit.',
        ];
    }

    /**
     * Simpan satu sel (karyawan + tanggal). Kode kosong = hapus tanda (karyawan dianggap masuk).
     * Admin Departemen hanya bisa mengisi sel kosong; mengubah data lama harus lewat menu Daftar (perlu approval HRD).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'karyawan_id' => ['required', 'integer', 'exists:karyawans,id'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'kode' => ['nullable', Rule::in(array_keys(Absensi::KODE))],
            'menit_terlambat' => $this->menitRules($request),
            'keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'kode.in' => 'Kode absensi tidak dikenal.',
            'keterangan.max' => 'Keterangan maksimal 255 karakter.',
            ...$this->menitMessages(),
        ]);

        // Admin Departemen hanya boleh mengisi karyawan di departemennya.
        $karyawan = Karyawan::findOrFail($data['karyawan_id']);
        Gate::authorize('update', $karyawan);
        $isAdmin = $request->user()->isModuleAdmin('hrms');

        // Sama seperti tabel CRUD: Admin Departemen hanya mengisi hari kerja (Senin - Jumat).
        if (! $isAdmin && $this->parseDate($data['tanggal'])?->isWeekend()) {
            return response()->json(['message' => 'Absensi hanya bisa diinput untuk hari Senin - Jumat.'], 422);
        }

        $existing = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $data['tanggal'])->first();

        if ($existing && ! $isAdmin) {
            return response()->json(['message' => 'Data absensi ini sudah ada. Ubah lewat menu Absensi > Daftar (perlu approval HRD).'], 422);
        }

        if (empty($data['kode'])) {
            $existing?->delete();

            return response()->json(['kode' => null, 'menit_terlambat' => null, 'keterangan' => null]);
        }

        $absensi = $existing ?? new Absensi(['karyawan_id' => $karyawan->id, 'tanggal' => $data['tanggal'], 'created_by' => $request->user()->id]);
        $absensi->fill([
            'kode' => $data['kode'],
            'menit_terlambat' => Absensi::isTerlambat($data['kode']) ? (int) $data['menit_terlambat'] : null,
            'keterangan' => $data['keterangan'] ?? null,
            'updated_by' => $request->user()->id,
        ])->save();

        return response()->json(['kode' => $absensi->kode, 'menit_terlambat' => $absensi->menit_terlambat, 'keterangan' => $absensi->keterangan]);
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
