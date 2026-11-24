<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\AbsensiApproval;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use App\Modules\Hrms\Support\KomparasiAbsensi;
use App\Modules\Hrms\Support\ProduktivitasHariIni;
use Closure;
use Illuminate\Database\Eloquent\Collection;
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
        $lockedDepartments = $user->managedDepartmentList();
        $canFilterDepartment = $isAdmin || $lockedDepartments->count() > 1;
        $department = $canFilterDepartment ? (string) $request->query('department') : '';
        $group = (string) $request->query('group');
        $status = in_array($request->query('status'), ['tidak_masuk', 'terlambat'], true) ? $request->query('status') : '';

        // Filter bulan tanpa tanggal: bulan berjalan menyorot hari ini, bulan lain menyorot tanggal 1.
        $bulan = $this->parseMonth((string) $request->query('bulan'));
        if ($bulan && ! $this->parseDate((string) $request->query('tanggal'))) {
            $tanggal = $bulan->isSameMonth(Carbon::today()) ? Carbon::today() : $bulan;
        }

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

        if ($status === 'tidak_masuk') {
            $karyawans = $this->tidakMasuk($karyawans, $tanggal);
        } elseif ($status === 'terlambat') {
            $karyawans = $this->terlambat($karyawans, $tanggal);
        }

        $monthStart = $tanggal->copy()->startOfMonth();
        $monthEnd = $tanggal->copy()->endOfMonth();
        $days = collect(range(1, $monthStart->daysInMonth))->map(function (int $day) use ($monthStart, $tanggal) {
            $date = $monthStart->copy()->day($day);

            return ['day' => $day, 'date' => $date->toDateString(), 'weekday' => $date->translatedFormat('D'),
                'weekend' => $date->isWeekend(), 'selected' => $date->isSameDay($tanggal), 'today' => $date->isToday(),
                // Hari kerja (Senin - Jumat) yang sudah lewat: sel kosong berarti karyawan masuk (ditandai hijau).
                'worked' => ! $date->isWeekend() && $date->lte(Carbon::today())];
        });

        // Baris bawah tabel dihitung per hari kerja seperti dashboard: tiap tanggal = grup pagi + NS di tanggal itu
        // + grup malam di tanggal shift malamnya (bisa jatuh di bulan sebelumnya, jadi statusnya dikirim terpisah).
        $rotasis = ShiftRotasi::orderBy('tanggal_mulai')->get();
        $jadwalBulan = $days->mapWithKeys(fn (array $day) => [$day['day'] => ProduktivitasHariIni::jadwalHariKerja(Carbon::parse($day['date']), $rotasis)])->all();

        // [karyawan_id][hari] => ['kode' => ..., 'keterangan' => ...]
        $marks = [];
        Absensi::whereIn('karyawan_id', $karyawans->pluck('id'))
            ->whereBetween('tanggal', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get(['karyawan_id', 'tanggal', 'kode', 'menit_terlambat', 'keterangan'])
            ->each(function (Absensi $absensi) use (&$marks) {
                $marks[$absensi->karyawan_id][$absensi->tanggal->day] = ['kode' => $absensi->kode, 'menit' => $absensi->menit_terlambat, 'keterangan' => $absensi->keterangan];
            });

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
            'kodes' => Absensi::kodeTampil(),
            'isAdmin' => $isAdmin,
            'canEdit' => $isAdmin || $lockedDepartments->isNotEmpty(),
            'lockedDepartments' => $lockedDepartments,
            'missingDepartment' => $user->isDepartmentAdmin() && $lockedDepartments->isEmpty(),
            'department' => $department,
            'group' => $group,
            'status' => $status,
            // Chip Hadir / Tidak hadir: seluruh karyawan yang bisa dilihat user (tidak ikut filter), dihitung per hari kerja
            // seperti kartu per shift di dashboard: grup pagi + NS di tanggal itu, grup malam di tanggal shift malamnya.
            'ringkasanHarian' => ProduktivitasHariIni::ringkasanHariKerja(fn () => Karyawan::query()->visibleTo($user), $tanggal),
            'jadwalHariKerja' => ProduktivitasHariIni::jadwalHariKerja($tanggal, $rotasis),
            'jadwalBulan' => $jadwalBulan,
            'statusLuarBulan' => $this->statusLuarBulan($karyawans, $jadwalBulan, $monthStart),
            'canFilterDepartment' => $canFilterDepartment,
            'departments' => $isAdmin ? Department::orderBy('name')->get() : $lockedDepartments,
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
        $lockedDepartments = $user->managedDepartmentList();

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
            'kodes' => Absensi::kodeTampil(),
            'lockedDepartments' => $lockedDepartments,
            'missingDepartment' => $user->isDepartmentAdmin() && $lockedDepartments->isEmpty(),
            'canEdit' => $lockedDepartments->isNotEmpty(),
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

        $skippedMessage = __(':count hari sudah ada datanya, ubah lewat tombol Edit (perlu approval HRD).', ['count' => $skipped]);

        if ($total === 0 && $skipped > 0) {
            return back()->withInput()->withErrors(['tanggal' => __('Tidak ada yang disimpan: :alasan', ['alasan' => $skippedMessage])]);
        }

        return redirect()->route('hrms.absensi.index', ['tanggal' => $data['tanggal']])
            ->with('status', __('Absensi :nik - :nama disimpan (:total hari).', ['nik' => $karyawan->nik, 'nama' => $karyawan->nama, 'total' => $total]).($skipped ? " {$skippedMessage}" : ''));
    }

    /**
     * Admin HRMS langsung mengubah data. Admin Departemen hanya mengajukan perubahan yang menunggu approval HRD.
     */
    public function updateEntry(Request $request, Absensi $absensi): RedirectResponse
    {
        Gate::authorize('update', $absensi->karyawan);
        $isAdmin = $request->user()->isModuleAdmin('hrms');

        if ($absensi->pendingApproval()->exists()) {
            return back()->withErrors(['approval' => $this->pesanMenungguApproval($isAdmin)]);
        }

        $data = $this->validateEntry($request, false, ! $isAdmin);

        $bentrok = Absensi::where('karyawan_id', $absensi->karyawan_id)->whereDate('tanggal', $data['tanggal'])->whereKeyNot($absensi->id)->exists();
        if ($bentrok) {
            return back()->withInput()->withErrors(['tanggal' => __('Karyawan ini sudah punya data absensi di tanggal tersebut.')]);
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
                ->with('status', __('Absensi :nik - :nama diperbarui.', ['nik' => $karyawan->nik, 'nama' => $karyawan->nama]));
        }

        $unchanged = $absensi->tanggal->toDateString() === $data['tanggal']
            && $absensi->kode === $data['kode']
            && $absensi->menit_terlambat === $data['menit_terlambat']
            && (string) $absensi->keterangan === (string) ($data['keterangan'] ?? '')
            && ! $request->hasFile('evidence');
        if ($unchanged) {
            return redirect()->route('hrms.absensi.index', ['tanggal' => $data['tanggal']])
                ->with('status', __('Tidak ada perubahan yang diajukan.'));
        }

        $this->ajukanPerubahan($request, $absensi, [
            'tanggal_baru' => $data['tanggal'],
            'kode_baru' => $data['kode'],
            'menit_baru' => $data['menit_terlambat'],
            'keterangan_baru' => $data['keterangan'] ?? null,
            'alasan' => $data['alasan'],
        ]);

        return redirect()->route('hrms.absensi.index', ['tanggal' => $absensi->tanggal->toDateString()])
            ->with('status', __('Perubahan absensi :nik - :nama diajukan, menunggu approval HRD.', ['nik' => $karyawan->nik, 'nama' => $karyawan->nama]));
    }

    /**
     * Admin HRMS langsung menghapus. Admin Departemen mengajukan penghapusan (perlu approval HRD).
     */
    public function destroyEntry(Request $request, Absensi $absensi): RedirectResponse
    {
        Gate::authorize('update', $absensi->karyawan);
        $karyawan = $absensi->karyawan;
        $tanggal = $absensi->tanggal->toDateString();

        $isAdmin = $request->user()->isModuleAdmin('hrms');

        if ($absensi->pendingApproval()->exists()) {
            return back()->withErrors(['approval' => $this->pesanMenungguApproval($isAdmin)]);
        }

        if ($isAdmin) {
            $absensi->delete();

            return redirect()->route('hrms.absensi.index', ['tanggal' => $tanggal])
                ->with('status', __('Absensi :nik - :nama dihapus (dianggap masuk).', ['nik' => $karyawan->nik, 'nama' => $karyawan->nama]));
        }

        $data = $request->validateWithBag('hapus', [
            'alasan' => ['required', 'string', 'max:1000'],
        ], [
            'alasan.required' => __('Alasan penghapusan wajib diisi.'),
            'alasan.max' => __('Alasan maksimal 1000 karakter.'),
        ]);

        $this->ajukanPerubahan($request, $absensi, [
            'tanggal_baru' => $tanggal,
            'kode_baru' => null,
            'menit_baru' => null,
            'keterangan_baru' => null,
            'alasan' => $data['alasan'],
        ]);

        return redirect()->route('hrms.absensi.index', ['tanggal' => $tanggal])
            ->with('status', __('Penghapusan absensi :nik - :nama diajukan, menunggu approval HRD.', ['nik' => $karyawan->nik, 'nama' => $karyawan->nama]));
    }

    /**
     * Absensi yang masih punya pengajuan pending tidak boleh diubah/dihapus langsung, termasuk oleh Admin HRMS,
     * supaya pengajuannya tidak kehilangan data sebelum diputuskan.
     */
    private function pesanMenungguApproval(bool $isAdmin): string
    {
        return $isAdmin
            ? __('Absensi ini masih punya pengajuan yang menunggu approval. Setujui atau tolak dulu di menu Approval Absensi.')
            : __('Absensi ini masih punya pengajuan perubahan yang menunggu approval HRD.');
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
                    $fail(__('Absensi hanya bisa diinput untuk hari Senin - Jumat.'));
                }
            }],
            'kode' => ['required', Rule::in(array_keys(Absensi::KODE))],
            'menit_terlambat' => $this->menitRules($request),
            'pilihan_alasan' => ['nullable', 'string', 'max:255'],
            'keterangan' => [Rule::requiredIf(fn () => $request->input('pilihan_alasan') === Absensi::ALASAN_LAIN), 'nullable', 'string', 'max:255'],
            ...($approval ? [
                'alasan' => ['required', 'string', 'max:1000'],
                'evidence' => [Rule::requiredIf(fn () => Absensi::isSakit($request->input('kode'))), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            ] : []),
        ], [
            'karyawan_id.required' => __('Pilih karyawan.'),
            'tanggal.required' => __('Tanggal wajib diisi.'),
            'kode.required' => __('Pilih kode absensi.'),
            'kode.in' => __('Kode absensi tidak dikenal.'),
            'sampai.after_or_equal' => __('Tanggal "sampai" tidak boleh sebelum tanggal mulai.'),
            'sampai.before_or_equal' => __('Rentang maksimal 31 hari.'),
            'keterangan.max' => __('Keterangan maksimal 255 karakter.'),
            'keterangan.required' => __('Keterangan wajib diisi kalau alasan "Lain-lain".'),
            'alasan.required' => __('Alasan perubahan wajib diisi.'),
            'alasan.max' => __('Alasan maksimal 1000 karakter.'),
            'evidence.required' => __('Perubahan ke kode sakit wajib melampirkan surat dokter.'),
            'evidence.mimes' => __('Evidence harus berupa gambar (JPG/PNG) atau PDF.'),
            'evidence.max' => __('Ukuran evidence maksimal 5 MB.'),
            ...$this->menitMessages($request),
        ]);

        // Menit hanya disimpan untuk kode datang terlambat / pulang cepat; DT< dan PC< kosong = 30 menit.
        $data['menit_terlambat'] = Absensi::menitUntuk($data['kode'], $data['menit_terlambat'] ?? null);

        return $data;
    }

    /**
     * Menit wajib untuk DT> / PC> (menit sebenarnya, dihitung dibulatkan ke atas per 30 menit).
     * DT< / PC< boleh dikosongkan (otomatis 30 menit) dan maksimal 30 menit.
     *
     * @return list<mixed>
     */
    private function menitRules(Request $request): array
    {
        return [
            Rule::requiredIf(fn () => Absensi::wajibIsiMenit($request->input('kode'))),
            'nullable', 'integer', 'min:1',
            'max:'.(in_array($request->input('kode'), Absensi::KODE_MENIT_OTOMATIS, true) ? Absensi::MENIT_PER_POTONGAN : 720),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function menitMessages(Request $request): array
    {
        if (in_array($request->input('kode'), Absensi::KODE_MENIT_OTOMATIS, true)) {
            return [
                'menit_terlambat.integer' => __('Menit harus berupa angka.'),
                'menit_terlambat.min' => __('Menit minimal 1 menit.'),
                'menit_terlambat.max' => __(':kode maksimal 30 menit. Lebih dari 30 menit pilih :besar.', [
                    'kode' => $request->input('kode'),
                    'besar' => str_replace('<', '>', (string) $request->input('kode')),
                ]),
            ];
        }

        if (Absensi::isPulangCepat($request->input('kode'))) {
            return [
                'menit_terlambat.required' => __('Isi berapa menit karyawan pulang cepat.'),
                'menit_terlambat.integer' => __('Menit pulang cepat harus berupa angka.'),
                'menit_terlambat.min' => __('Menit pulang cepat minimal 1 menit.'),
                'menit_terlambat.max' => __('Menit pulang cepat maksimal 720 menit.'),
            ];
        }

        return [
            'menit_terlambat.required' => __('Isi berapa menit karyawan datang terlambat.'),
            'menit_terlambat.integer' => __('Menit terlambat harus berupa angka.'),
            'menit_terlambat.min' => __('Menit terlambat minimal 1 menit.'),
            'menit_terlambat.max' => __('Menit terlambat maksimal 720 menit.'),
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
            'pilihan_alasan' => ['nullable', 'string', 'max:255'],
            'keterangan' => [Rule::requiredIf(fn () => $request->input('pilihan_alasan') === Absensi::ALASAN_LAIN), 'nullable', 'string', 'max:255'],
        ], [
            'kode.in' => __('Kode absensi tidak dikenal.'),
            'keterangan.max' => __('Keterangan maksimal 255 karakter.'),
            'keterangan.required' => __('Keterangan wajib diisi kalau alasan "Lain-lain".'),
            ...$this->menitMessages($request),
        ]);

        // Admin Departemen hanya boleh mengisi karyawan di departemennya.
        $karyawan = Karyawan::findOrFail($data['karyawan_id']);
        Gate::authorize('update', $karyawan);
        $isAdmin = $request->user()->isModuleAdmin('hrms');

        // Sama seperti tabel CRUD: Admin Departemen hanya mengisi hari kerja (Senin - Jumat).
        if (! $isAdmin && $this->parseDate($data['tanggal'])?->isWeekend()) {
            return response()->json(['message' => __('Absensi hanya bisa diinput untuk hari Senin - Jumat.')], 422);
        }

        $existing = Absensi::where('karyawan_id', $karyawan->id)->whereDate('tanggal', $data['tanggal'])->first();

        if ($existing && ! $isAdmin) {
            return response()->json(['message' => __('Data absensi ini sudah ada. Ubah lewat menu Absensi > Daftar (perlu approval HRD).')], 422);
        }

        if ($existing?->pendingApproval()->exists()) {
            return response()->json(['message' => $this->pesanMenungguApproval(true)], 422);
        }

        if (empty($data['kode'])) {
            $existing?->delete();

            return response()->json(['kode' => null, 'menit_terlambat' => null, 'keterangan' => null]);
        }

        $absensi = $existing ?? new Absensi(['karyawan_id' => $karyawan->id, 'tanggal' => $data['tanggal'], 'created_by' => $request->user()->id]);
        $absensi->fill([
            'kode' => $data['kode'],
            'menit_terlambat' => Absensi::menitUntuk($data['kode'], $data['menit_terlambat'] ?? null),
            'keterangan' => $data['keterangan'] ?? null,
            'updated_by' => $request->user()->id,
        ])->save();

        return response()->json(['kode' => $absensi->kode, 'menit_terlambat' => $absensi->menit_terlambat, 'keterangan' => $absensi->keterangan]);
    }

    /**
     * Anggota tiap shift di hari kerja $tanggal beserta tanggal datanya: grup pagi + NS di tanggal itu,
     * grup malam di tanggal shift malamnya. Tanpa rotasi: semua karyawan di tanggal itu.
     *
     * @param  Collection<int, Karyawan>  $karyawans
     * @return list<array{0: Collection<int, Karyawan>, 1: Carbon}>
     */
    private function shiftHariKerja(Collection $karyawans, Carbon $tanggal): array
    {
        $jadwal = ProduktivitasHariIni::jadwalHariKerja($tanggal);

        if ($jadwal['pagi'] === null && $jadwal['malam'] === null) {
            return [[$karyawans, $tanggal]];
        }

        return [
            [$karyawans->filter(fn (Karyawan $karyawan) => $karyawan->group === 'NS' || $karyawan->group === $jadwal['pagi']), $tanggal],
            [$karyawans->filter(fn (Karyawan $karyawan) => $karyawan->group === $jadwal['malam']), Carbon::parse($jadwal['tanggalMalam'])],
        ];
    }

    /**
     * Karyawan yang tidak masuk di hari kerja itu (per shift, sama seperti dashboard): punya kode tidak hadir (bukan DT/PC),
     * atau tidak masuk di mesin absen tapi belum ada keterangan.
     *
     * @param  Collection<int, Karyawan>  $karyawans
     * @return Collection<int, Karyawan>
     */
    private function tidakMasuk(Collection $karyawans, Carbon $tanggal): Collection
    {
        $ids = [];
        foreach ($this->shiftHariKerja($karyawans, $tanggal) as [$anggota, $tanggalData]) {
            $date = $tanggalData->toDateString();
            $berkode = Absensi::whereIn('karyawan_id', $anggota->pluck('id'))
                ->whereDate('tanggal', $date)
                ->whereIn('kode', Absensi::kodeTidakHadir())
                ->pluck('karyawan_id');
            $tanpaKeterangan = KomparasiAbsensi::selBelumAdaKeterangan($anggota->values(), $tanggalData, $tanggalData);

            foreach ($berkode as $id) {
                $ids[$id] = true;
            }
            foreach ($anggota as $karyawan) {
                if (isset($tanpaKeterangan[$karyawan->id][$date])) {
                    $ids[$karyawan->id] = true;
                }
            }
        }

        return $karyawans->filter(fn (Karyawan $karyawan) => isset($ids[$karyawan->id]))->values();
    }

    /**
     * Karyawan yang datang terlambat (kode DT< / DT>) di hari kerja itu, per shift seperti dashboard.
     *
     * @param  Collection<int, Karyawan>  $karyawans
     * @return Collection<int, Karyawan>
     */
    private function terlambat(Collection $karyawans, Carbon $tanggal): Collection
    {
        $ids = [];
        foreach ($this->shiftHariKerja($karyawans, $tanggal) as [$anggota, $tanggalData]) {
            Absensi::whereIn('karyawan_id', $anggota->pluck('id'))
                ->whereDate('tanggal', $tanggalData->toDateString())
                ->whereIn('kode', Absensi::KODE_TERLAMBAT)
                ->pluck('karyawan_id')
                ->each(function ($id) use (&$ids) {
                    $ids[$id] = true;
                });
        }

        return $karyawans->filter(fn (Karyawan $karyawan) => isset($ids[$karyawan->id]))->values();
    }

    /**
     * Status karyawan grup malam di tanggal shift malam yang jatuh sebelum bulan tampil (mis. tanggal 1 = malam
     * tanggal terakhir bulan lalu), supaya baris bawah tabel tetap bisa dihitung per hari kerja di browser.
     *
     * @param  Collection<int, Karyawan>  $karyawans
     * @param  array<int, array{tanggal: string, pagi: ?string, malam: ?string, tanggalMalam: string}>  $jadwalBulan
     * @return array<int, array<string, array{kode: string, absen: bool}>>
     */
    private function statusLuarBulan(Collection $karyawans, array $jadwalBulan, Carbon $monthStart): array
    {
        $status = [];

        foreach ($jadwalBulan as $jadwal) {
            $malam = Carbon::parse($jadwal['tanggalMalam']);
            if ($jadwal['malam'] === null || ! $malam->lt($monthStart)) {
                continue;
            }

            $date = $malam->toDateString();
            $anggota = $karyawans->filter(fn (Karyawan $karyawan) => $karyawan->group === $jadwal['malam'])->values();
            Absensi::whereIn('karyawan_id', $anggota->pluck('id'))
                ->whereDate('tanggal', $date)
                ->get(['karyawan_id', 'kode'])
                ->each(function (Absensi $absensi) use (&$status, $date) {
                    $status[$absensi->karyawan_id][$date] = [
                        'kode' => $absensi->kode,
                        'absen' => ! (Absensi::KODE[$absensi->kode]['hadir'] ?? true),
                    ];
                });
            foreach (KomparasiAbsensi::selBelumAdaKeterangan($anggota, $malam, $malam) as $id => $tanggals) {
                if (isset($tanggals[$date]) && ! isset($status[$id][$date])) {
                    $status[$id][$date] = ['kode' => '', 'absen' => true];
                }
            }
        }

        return $status;
    }

    private function parseMonth(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::createFromFormat('!Y-m', $value) : null;
        } catch (Throwable) {
            return null;
        }
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
