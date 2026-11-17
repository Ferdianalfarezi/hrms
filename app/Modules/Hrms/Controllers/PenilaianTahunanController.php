<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Department;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\PenilaianTahunan;
use App\Modules\Hrms\Models\PenilaianTahunanLog;
use App\Modules\Hrms\Support\FormPenilaian;
use App\Modules\Hrms\Support\JenjangPenilaian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Penilaian akhir tahun karyawan: diisi jenjang di atasnya lalu di-approve bertingkat (lihat JenjangPenilaian).
 * Nilai absen (poin I) dihitung otomatis dari data absensi setahun, poin II s/d VI diisi manual A-E.
 */
class PenilaianTahunanController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('penilaian-tahunan');

        $user = $request->user();
        $isAdmin = $user->isModuleAdmin('hrms');
        $userLevel = JenjangPenilaian::userLevel($user);
        $departmentIds = JenjangPenilaian::departmentIds($user);
        $tahun = $this->tahun($request);
        $department = (string) $request->query('department');
        $jenjang = (string) $request->query('jenjang');
        $status = (string) $request->query('status');
        $search = trim((string) $request->query('q'));

        $jabatans = $isAdmin ? array_keys(FormPenilaian::FORM_JABATAN) : JenjangPenilaian::jabatanDiBawah($userLevel);
        $jenjangOptions = collect($jabatans)->map(fn (string $jabatan) => JenjangPenilaian::levelOf($jabatan))->unique()->values();

        $karyawans = Karyawan::query()
            ->with('department')
            ->whereIn('jabatan', $jabatans)
            ->when(! $isAdmin, fn ($query) => $query->whereIn('department_id', $departmentIds === [] ? [0] : $departmentIds))
            ->when(ctype_digit($department), fn ($query) => $query->where('department_id', $department))
            ->when(isset(JenjangPenilaian::RANK[$jenjang]), fn ($query) => $query->whereIn('jabatan', array_keys(
                array_filter(JenjangPenilaian::JABATAN, fn (string $level) => $level === $jenjang)
            )))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('nik', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%")))
            ->orderByRaw('LENGTH(nik)')
            ->orderBy('nik')
            ->get();

        $penilaians = PenilaianTahunan::query()
            ->where('tahun', $tahun)
            ->whereIn('karyawan_id', $karyawans->pluck('id'))
            ->get()
            ->keyBy('karyawan_id');
        $jumlahAbsen = $this->jumlahAbsen($karyawans->pluck('id')->all(), $tahun);
        $alur = new JenjangPenilaian;

        $rows = $karyawans->map(function (Karyawan $karyawan) use ($penilaians, $jumlahAbsen, $alur, $user) {
            $penilaian = $penilaians[$karyawan->id] ?? null;
            $tahapan = $penilaian?->tahapan ?? $alur->tahapanFor($karyawan);
            $form = $penilaian?->form ?? FormPenilaian::formForJabatan($karyawan->jabatan);
            $nilaiAbsen = $penilaian && ! $penilaian->isEditable()
                ? $penilaian->nilai_absen
                : FormPenilaian::hitungAbsen($jumlahAbsen[$karyawan->id] ?? [], FormPenilaian::form($form)['bobot_absen'])['nilai'];
            $posisi = $penilaian ? $penilaian->posisi_level : ($tahapan[0] ?? null);
            $rowStatus = $penilaian?->status ?? 'belum';

            return [
                'karyawan' => $karyawan,
                'penilaian' => $penilaian,
                'status' => $rowStatus,
                'tahapan' => $tahapan,
                'posisi' => $posisi,
                'nilai_absen' => $nilaiAbsen,
                'perlu_tindakan' => $rowStatus !== PenilaianTahunan::STATUS_DISETUJUI
                    && JenjangPenilaian::actsAs($user, $posisi, $karyawan->department_id ? (int) $karyawan->department_id : null),
            ];
        });

        $statusCounts = $rows->countBy('status');
        $perluTindakan = $rows->where('perlu_tindakan', true)->count();

        if ($status === 'saya') {
            $rows = $rows->where('perlu_tindakan', true)->values();
        } elseif ($status !== '') {
            $rows = $rows->where('status', $status)->values();
        }

        $perPage = 25;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $rows = (new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => $request->url(),
        ]))->withQueryString();

        return view('hrms.penilaian.index', [
            'rows' => $rows,
            'tahun' => $tahun,
            'department' => $department,
            'jenjang' => $jenjang,
            'status' => $status,
            'search' => $search,
            'statusCounts' => $statusCounts,
            'perluTindakan' => $perluTindakan,
            'jenjangOptions' => $jenjangOptions,
            'departments' => $isAdmin ? Department::orderBy('name')->get() : Department::whereKey($departmentIds)->orderBy('name')->get(),
            'isAdmin' => $isAdmin,
            'userLevel' => $userLevel,
        ]);
    }

    public function show(Request $request, Karyawan $karyawan): View
    {
        Gate::authorize('penilaian-tahunan');
        abort_unless(JenjangPenilaian::canView($request->user(), $karyawan), 403);

        $tahun = $this->tahun($request);
        $formKey = FormPenilaian::formForJabatan($karyawan->jabatan);
        abort_if($formKey === null, 404, __('Jabatan ini tidak memiliki form penilaian.'));

        $penilaian = $this->penilaianFor($karyawan, $tahun);
        $form = FormPenilaian::form($penilaian->form);
        $absen = $penilaian->isEditable()
            ? FormPenilaian::hitungAbsen($this->jumlahAbsen([$karyawan->id], $tahun)[$karyawan->id] ?? [], $form['bobot_absen'])
            : $penilaian->absen;

        $penilaian->loadMissing(['logs.user', 'penilai']);

        return view('hrms.penilaian.form', [
            'karyawan' => $karyawan->loadMissing('department'),
            'penilaian' => $penilaian,
            'tahun' => $tahun,
            'form' => $form,
            'absen' => $absen,
            'performance' => FormPenilaian::hitungPerformance($penilaian->form, $penilaian->skor ?? []),
            'canFill' => $this->canFill($request->user(), $penilaian),
            'canApprove' => $this->canApprove($request->user(), $penilaian),
            'penilaiPerTahap' => $this->penilaiPerTahap($penilaian),
        ]);
    }

    /**
     * Evidence penilaian yang sudah disetujui semua tahap, dalam format form A4 untuk dicetak / disimpan PDF.
     */
    public function print(Request $request, Karyawan $karyawan): View
    {
        Gate::authorize('penilaian-tahunan');
        abort_unless(JenjangPenilaian::canView($request->user(), $karyawan), 403);

        $tahun = $this->tahun($request);
        $penilaian = PenilaianTahunan::query()
            ->where('karyawan_id', $karyawan->id)
            ->where('tahun', $tahun)
            ->where('status', PenilaianTahunan::STATUS_DISETUJUI)
            ->with(['logs.user', 'department'])
            ->firstOrFail();

        return view('hrms.penilaian.print', [
            'karyawan' => $karyawan,
            'penilaian' => $penilaian,
            'tahun' => $tahun,
            'form' => FormPenilaian::form($penilaian->form),
            'performance' => FormPenilaian::hitungPerformance($penilaian->form, $penilaian->skor ?? []),
            'penilaiPerTahap' => $this->penilaiPerTahap($penilaian),
        ]);
    }

    /**
     * Simpan isian penilaian.
     * - Penilai (tahap pertama): aksi=draft menyimpan sementara, aksi=kirim meneruskan ke approver pertama.
     * - Approver yang sedang mendapat giliran boleh mengubah nilai: aksi=simpan menyimpan perubahan,
     *   aksi=setuju menyimpan lalu meneruskan ke jenjang berikutnya (atau selesai).
     * Kirim dan setuju mewajibkan semua butir terisi.
     */
    public function save(Request $request, Karyawan $karyawan): RedirectResponse
    {
        Gate::authorize('penilaian-tahunan');
        abort_unless(JenjangPenilaian::canView($request->user(), $karyawan), 403);

        $tahun = $this->tahun($request);
        abort_if(FormPenilaian::formForJabatan($karyawan->jabatan) === null, 404);
        $penilaian = $this->penilaianFor($karyawan, $tahun);
        $user = $request->user();
        $sebagaiPenilai = $this->canFill($user, $penilaian);
        abort_unless($sebagaiPenilai || $this->canApprove($user, $penilaian), 403);

        $data = $request->validate([
            'aksi' => ['required', Rule::in($sebagaiPenilai ? ['draft', 'kirim'] : ['simpan', 'setuju'])],
            'skor' => ['nullable', 'array'],
            'skor.*' => ['nullable', 'array'],
            'skor.*.*' => ['nullable', Rule::in(array_keys(FormPenilaian::NILAI_HURUF))],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'hasil' => ['nullable', Rule::in(PenilaianTahunan::HASIL)],
            'catatan_review' => ['nullable', 'string', 'max:255'],
        ], [
            'skor.*.*.in' => __('Nilai butir harus A, B, C, D, atau E.'),
            'catatan.max' => __('Catatan maksimal 2000 karakter.'),
            'catatan_review.max' => __('Catatan approval maksimal 255 karakter.'),
        ]);

        $aksi = $data['aksi'];
        $skor = $this->bersihkanSkor($penilaian->form, $data['skor'] ?? []);
        $performance = FormPenilaian::hitungPerformance($penilaian->form, $skor);

        if (in_array($aksi, ['kirim', 'setuju'], true) && ! $performance['lengkap']) {
            throw ValidationException::withMessages(['skor' => $aksi === 'kirim' ? __('Semua butir penilaian (poin II s/d VI) wajib diisi sebelum dikirim.') : __('Semua butir penilaian (poin II s/d VI) wajib diisi sebelum disetujui.')]);
        }

        // Nilai absen dikunci saat dikirim; approver hanya mengubah nilai performance.
        $absen = $sebagaiPenilai
            ? FormPenilaian::hitungAbsen($this->jumlahAbsen([$karyawan->id], $tahun)[$karyawan->id] ?? [], FormPenilaian::form($penilaian->form)['bobot_absen'])
            : $penilaian->absen;
        $nilaiAkhir = round($absen['nilai'] + $performance['nilai'], 2);
        $tahap = $penilaian->tahap;
        $level = $penilaian->posisi_level;

        DB::transaction(function () use ($penilaian, $user, $data, $aksi, $skor, $absen, $performance, $nilaiAkhir, $sebagaiPenilai, $tahap, $level) {
            $penilaian->fill([
                'absen' => $absen,
                'nilai_absen' => $absen['nilai'],
                'skor' => $skor,
                'nilai_performance' => $performance['nilai'],
                'nilai_akhir' => $nilaiAkhir,
                'huruf' => FormPenilaian::huruf($nilaiAkhir),
                'catatan' => $data['catatan'] ?? null,
                'hasil' => $data['hasil'] ?? null,
            ]);
            $diubahApprover = ! $sebagaiPenilai && $penilaian->isDirty(['skor', 'catatan', 'hasil']);

            if ($sebagaiPenilai) {
                $penilaian->penilai_id = $user->id;
            }

            if ($aksi === 'kirim') {
                $penilaian->submitted_at = now();
                $this->majukanTahap($penilaian, 1);
            } elseif ($aksi === 'setuju') {
                $this->majukanTahap($penilaian, $tahap + 1);
            }

            $penilaian->save();

            if ($diubahApprover) {
                $this->log($penilaian, $tahap, $level, PenilaianTahunanLog::AKSI_UBAH, $user);
            }
            match ($aksi) {
                'kirim' => $this->log($penilaian, 0, $penilaian->tahapan[0], PenilaianTahunanLog::AKSI_KIRIM, $user),
                'setuju' => $this->log($penilaian, $tahap, $level, PenilaianTahunanLog::AKSI_SETUJU, $user, $data['catatan_review'] ?? null),
                default => null,
            };
        });

        $nama = "{$karyawan->nik} - {$karyawan->nama}";
        $diteruskan = $penilaian->status === PenilaianTahunan::STATUS_DISETUJUI
            ? __('dan selesai.')
            : __('diteruskan ke :tahap.', ['tahap' => JenjangPenilaian::label($penilaian->posisi_level)]);

        return redirect()->route('hrms.penilaian.show', [$karyawan, 'tahun' => $tahun])->with('status', match ($aksi) {
            'draft' => __('Penilaian :nama disimpan sebagai draft.', ['nama' => $nama]),
            'simpan' => __('Perubahan penilaian :nama disimpan.', ['nama' => $nama]),
            'kirim' => __('Penilaian :nama dikirim :lanjut', ['nama' => $nama, 'lanjut' => $diteruskan]),
            default => __('Penilaian :nama disetujui :lanjut', ['nama' => $nama, 'lanjut' => $diteruskan]),
        });
    }

    /**
     * Pindah ke tahap tertentu; kalau tahapnya sudah habis, penilaian selesai (disetujui).
     */
    private function majukanTahap(PenilaianTahunan $penilaian, int $tahap): void
    {
        $selesai = $tahap >= count($penilaian->tahapan);

        $penilaian->fill([
            'tahap' => $tahap,
            'posisi_level' => $selesai ? null : $penilaian->tahapan[$tahap],
            'status' => $selesai ? PenilaianTahunan::STATUS_DISETUJUI : PenilaianTahunan::STATUS_MENUNGGU,
        ]);
    }

    /**
     * Penilaian tahun ini (atau objek baru yang belum disimpan). Selama belum dikirim, alur penilai dihitung ulang
     * supaya ikut perubahan akun/jabatan.
     */
    private function penilaianFor(Karyawan $karyawan, int $tahun): PenilaianTahunan
    {
        $penilaian = PenilaianTahunan::firstOrNew(['karyawan_id' => $karyawan->id, 'tahun' => $tahun]);

        if (! $penilaian->exists || $penilaian->isEditable()) {
            $tahapan = (new JenjangPenilaian)->tahapanFor($karyawan);
            $penilaian->fill([
                'jabatan' => $karyawan->jabatan,
                'department_id' => $karyawan->department_id,
                'form' => FormPenilaian::formForJabatan($karyawan->jabatan),
                'tahapan' => $tahapan,
                'tahap' => 0,
                'posisi_level' => $tahapan[0] ?? null,
                'status' => $penilaian->status ?? PenilaianTahunan::STATUS_DRAFT,
                'absen' => $penilaian->absen ?? [],
            ]);
        }

        return $penilaian;
    }

    /**
     * Penilai (tahap pertama) yang boleh mengisi: selama masih draft (belum dikirim).
     */
    private function canFill(User $user, PenilaianTahunan $penilaian): bool
    {
        return $penilaian->isEditable()
            && JenjangPenilaian::actsAs($user, $penilaian->tahapan[0] ?? null, $penilaian->department_id);
    }

    private function canApprove(User $user, PenilaianTahunan $penilaian): bool
    {
        return $penilaian->exists
            && $penilaian->isMenunggu()
            && JenjangPenilaian::canApproveAs($user, $penilaian->posisi_level, $penilaian->department_id);
    }

    /**
     * Kotak "Penilai" di form: satu kolom per tahap berisi nama & tanggal tindakan terakhir di tahap itu.
     * Kalau tahap di-approve jenjang yang lebih tinggi (override), judul kolom memakai jabatan yang meng-override.
     *
     * @return list<array{level: string, label: string, log: ?PenilaianTahunanLog, override: ?string, aktif: bool}>
     */
    private function penilaiPerTahap(PenilaianTahunan $penilaian): array
    {
        $logs = $penilaian->exists ? $penilaian->logs : collect();

        return collect($penilaian->tahapan)->map(function (string $level, int $tahap) use ($penilaian, $logs) {
            $log = $logs->where('tahap', $tahap)->whereIn('aksi', [PenilaianTahunanLog::AKSI_KIRIM, PenilaianTahunanLog::AKSI_SETUJU])->last();
            $override = $log && $log->level !== '' && $log->level !== $level;

            return [
                'level' => $level,
                'label' => JenjangPenilaian::label($override ? $log->level : $level),
                'log' => $log,
                'override' => $override ? JenjangPenilaian::label($level) : null,
                'aktif' => $penilaian->posisi_level === $level && $penilaian->status !== PenilaianTahunan::STATUS_DISETUJUI,
            ];
        })->all();
    }

    /**
     * Hanya simpan butir yang ada di form, dengan index butir yang benar.
     *
     * @param  array<string, mixed>  $skor
     * @return array<string, array<int, string>>
     */
    private function bersihkanSkor(string $formKey, array $skor): array
    {
        $bersih = [];
        foreach (FormPenilaian::form($formKey)['butir'] as $kategori => $butir) {
            foreach (array_keys($butir) as $index) {
                $huruf = $skor[$kategori][$index] ?? null;
                if (isset(FormPenilaian::NILAI_HURUF[$huruf])) {
                    $bersih[$kategori][$index] = $huruf;
                }
            }
        }

        return $bersih;
    }

    /**
     * Banyaknya kejadian per kategori absen form, dari data absensi 1 Januari - 31 Desember tahun penilaian.
     *
     * @param  list<int>  $karyawanIds
     * @return array<int, array<string, int>> karyawan_id => kategori absen => jumlah
     */
    private function jumlahAbsen(array $karyawanIds, int $tahun): array
    {
        if ($karyawanIds === []) {
            return [];
        }

        $kategoriKode = [];
        foreach (FormPenilaian::KONVERSI_ABSEN as $kategori => $konversi) {
            foreach ($konversi['kode'] as $kode) {
                $kategoriKode[$kode] = $kategori;
            }
        }

        $hasil = [];
        Absensi::query()
            ->selectRaw('karyawan_id, kode, count(*) as total')
            ->whereIn('karyawan_id', $karyawanIds)
            ->whereIn('kode', array_keys($kategoriKode))
            ->whereBetween('tanggal', ["{$tahun}-01-01", "{$tahun}-12-31"])
            ->groupBy('karyawan_id', 'kode')
            ->get()
            ->each(function ($row) use (&$hasil, $kategoriKode) {
                $kategori = $kategoriKode[$row->kode];
                $hasil[$row->karyawan_id][$kategori] = ($hasil[$row->karyawan_id][$kategori] ?? 0) + (int) $row->total;
            });

        return $hasil;
    }

    private function tahun(Request $request): int
    {
        $tahun = (int) $request->input('tahun', Carbon::today()->year);

        return $tahun >= 2000 && $tahun <= 2100 ? $tahun : Carbon::today()->year;
    }

    /**
     * Catat tindakan di tahap tertentu. Level yang disimpan = jabatan pelaku, jadi override oleh jenjang lebih tinggi
     * tetap terlihat (mis. tahap Section Chief disetujui Manager).
     */
    private function log(PenilaianTahunan $penilaian, int $tahap, ?string $level, string $aksi, User $user, ?string $catatan = null): void
    {
        $penilaian->logs()->create([
            'tahap' => $tahap,
            'level' => JenjangPenilaian::userLevel($user) ?? (string) $level,
            'aksi' => $aksi,
            'user_id' => $user->id,
            'catatan' => $catatan,
        ]);
    }
}
