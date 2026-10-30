<?php

namespace App\Modules\Hrms\Support;

use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\Karyawan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Persentase ketidakhadiran bulanan per hari kerja (Senin - Jumat), dipisah per kelompok karyawan:
 *  - AST.LEAD UP PKWTT: karyawan tetap yang punya jabatan (jabatan bukan "Karyawan").
 *  - OPERATOR PKWTT: karyawan tetap dengan jabatan "Karyawan".
 *  - OPERATOR PKWT: PKWT 1, 2, dan 3.
 *  - OPERATOR PEMAGANGAN: pemagangan.
 *
 * Cuti = kategori CT. S+I = sakit, izin, dan alpa (termasuk tidak absen di mesin tanpa keterangan). WFH dihitung hadir.
 */
class PresentaseAbsensi
{
    /** @var array<string, array{grup: string, status: string}> */
    public const KELOMPOK = [
        'ast_lead' => ['grup' => 'AST.LEAD UP', 'status' => 'PKWTT'],
        'pkwtt' => ['grup' => 'OPERATOR', 'status' => 'PKWTT'],
        'pkwt' => ['grup' => 'OPERATOR', 'status' => 'PKWT'],
        'magang' => ['grup' => 'OPERATOR', 'status' => 'PEMAGANGAN'],
    ];

    /** Jabatan operator; karyawan tetap dengan jabatan lain masuk AST.LEAD UP. */
    private const JABATAN_OPERATOR = 'karyawan';

    public static function kelompok(Karyawan $karyawan): ?string
    {
        return match (true) {
            $karyawan->status === 'Tetap' => in_array(strtolower(trim((string) $karyawan->jabatan)), [self::JABATAN_OPERATOR, ''], true) ? 'pkwtt' : 'ast_lead',
            str_starts_with((string) $karyawan->status, 'PKWT') => 'pkwt',
            $karyawan->status === 'Pemagangan' => 'magang',
            default => null,
        };
    }

    /**
     * @return array{bulan: Carbon, days: list<array{tanggal: Carbon, rows: array<string, array<string, mixed>>}>, total: array<string, mixed>}
     */
    public static function build(Carbon $bulan): array
    {
        $mulai = $bulan->copy()->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth()->startOfDay()->min(Carbon::today());

        $karyawans = Karyawan::get(['id', 'nik', 'group', 'status', 'jabatan', 'tanggal_masuk'])
            ->each(fn (Karyawan $karyawan) => $karyawan->setAttribute('kelompok', self::kelompok($karyawan)))
            ->filter(fn (Karyawan $karyawan) => $karyawan->kelompok !== null)
            ->values();

        $absen = $mulai->lte($akhir) ? self::absen($karyawans, $mulai, $akhir) : [];

        $days = [];
        for ($day = $mulai->copy(); $day->lte($akhir); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            $key = $day->toDateString();
            $aktif = $karyawans->filter(fn (Karyawan $karyawan) => $karyawan->tanggal_masuk === null || $karyawan->tanggal_masuk->lte($day));

            $days[] = [
                'tanggal' => $day->copy(),
                'rows' => collect(self::KELOMPOK)->map(function (array $kelompok, string $kode) use ($aktif, $absen, $key) {
                    $anggota = $aktif->where('kelompok', $kode);

                    return self::hitung([
                        'total' => $anggota->count(),
                        'cuti' => $anggota->filter(fn (Karyawan $karyawan) => ($absen[$karyawan->id][$key] ?? null) === 'cuti')->count(),
                        'si' => $anggota->filter(fn (Karyawan $karyawan) => ($absen[$karyawan->id][$key] ?? null) === 'si')->count(),
                    ]);
                })->all(),
            ];
        }

        $rows = collect($days)->flatMap(fn (array $day) => array_values($day['rows']));

        return [
            'bulan' => $mulai,
            'days' => $days,
            'total' => self::hitung(['total' => $rows->sum('total'), 'cuti' => $rows->sum('cuti'), 'si' => $rows->sum('si')]),
        ];
    }

    /**
     * Ketidakhadiran per karyawan per tanggal: [karyawan_id][Y-m-d] => 'cuti' | 'si'.
     *
     * @param  Collection<int, Karyawan>  $karyawans
     * @return array<int, array<string, string>>
     */
    private static function absen(Collection $karyawans, Carbon $mulai, Carbon $akhir): array
    {
        $absen = [];

        Absensi::whereIn('kode', Absensi::kodeTidakHadir())
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->get(['karyawan_id', 'tanggal', 'kode'])
            ->each(function (Absensi $absensi) use (&$absen) {
                $kategori = Absensi::kategori($absensi->kode);

                if ($kategori !== 'WFH') {
                    $absen[$absensi->karyawan_id][$absensi->tanggal->toDateString()] = $kategori === 'CT' ? 'cuti' : 'si';
                }
            });

        // Tidak absen di mesin dan belum ada keterangan dari Admin Departemen = alpa.
        foreach (KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $mulai, $akhir) as $karyawanId => $dates) {
            foreach (array_keys($dates) as $key) {
                $absen[$karyawanId][$key] ??= 'si';
            }
        }

        return $absen;
    }

    /**
     * @param  array{total: int, cuti: int, si: int}  $row
     * @return array{total: int, cuti: int, si: int, hadir: int, rate: ?float}
     */
    private static function hitung(array $row): array
    {
        $tidakHadir = $row['cuti'] + $row['si'];

        return [
            ...$row,
            'hadir' => $row['total'] - $tidakHadir,
            'rate' => $row['total'] > 0 ? $tidakHadir / $row['total'] * 100 : null,
        ];
    }
}
