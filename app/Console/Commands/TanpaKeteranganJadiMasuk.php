<?php

namespace App\Console\Commands;

use App\Modules\Hrms\Models\AbsensiMesin;
use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Hrms\Models\ShiftRotasi;
use App\Modules\Hrms\Support\KomparasiAbsensi;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

#[Signature('absensi:tanpa-keterangan-jadi-masuk
    {tanggal : Tanggal laporan absensi (Y-m-d), mis. 2026-11-06}
    {--dry-run : Hanya tampilkan karyawan yang akan diubah, tanpa menghapus}
    {--force : Jalankan tanpa konfirmasi}')]
#[Description('Jadikan karyawan "tanpa keterangan" (sel merah) pada tanggal laporan itu menjadi masuk, dengan menghapus data tidak absen dari mesin.')]
class TanpaKeteranganJadiMasuk extends Command
{
    /**
     * Sel merah = NIK ada di data mesin (tidak absen) dan belum ada kode absensi. Menghapus baris mesinnya membuat sel kembali
     * kosong (= masuk). Grup shift malam melapor ke tanggal sebelumnya, jadi baris mesin dicari sampai 4 hari sesudahnya
     * dan dipilih yang tanggal laporannya sama, seperti di matriks Harian.
     */
    public function handle(): int
    {
        try {
            $tanggal = Carbon::createFromFormat('!Y-m-d', (string) $this->argument('tanggal'));
        } catch (Throwable) {
            $this->error('Format tanggal harus Y-m-d, mis. 2026-11-06.');

            return self::FAILURE;
        }
        $date = $tanggal->toDateString();

        $karyawans = Karyawan::query()->get(['id', 'nik', 'nama', 'group']);
        $tanpaKeterangan = KomparasiAbsensi::selBelumAdaKeterangan($karyawans, $tanggal, $tanggal);
        $targets = $karyawans->filter(fn (Karyawan $karyawan) => isset($tanpaKeterangan[$karyawan->id][$date]))->keyBy('nik');

        if ($targets->isEmpty()) {
            $this->info("Tidak ada karyawan tanpa keterangan pada {$date}.");

            return self::SUCCESS;
        }

        $rotasis = ShiftRotasi::orderBy('tanggal_mulai')->get();
        $rows = AbsensiMesin::whereIn('nik', $targets->keys())
            ->where('tanggal', '>=', $date)
            ->where('tanggal', '<', $tanggal->copy()->addDays(4)->toDateString())
            ->get()
            ->filter(fn (AbsensiMesin $row) => KomparasiAbsensi::tanggalLaporan($row->tanggal, $targets[$row->nik]->group, $rotasis)->isSameDay($tanggal))
            ->values();

        $this->table(['NIK', 'Nama', 'Group', 'Tanggal data mesin'], $rows->map(fn (AbsensiMesin $row) => [
            $row->nik, $targets[$row->nik]->nama, $targets[$row->nik]->group ?? '-', $row->tanggal->toDateString(),
        ]));
        $this->line("{$targets->count()} karyawan tanpa keterangan pada {$date} akan dijadikan masuk.");

        if ($this->option('dry-run')) {
            $this->comment('Dry run: tidak ada data yang dihapus.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Hapus data tidak absen dari mesin untuk karyawan di atas?')) {
            $this->comment('Dibatalkan.');

            return self::SUCCESS;
        }

        $deleted = AbsensiMesin::whereKey($rows->modelKeys())->delete();
        $this->info("{$deleted} data mesin dihapus, {$targets->count()} karyawan sekarang tercatat masuk pada {$date}.");

        return self::SUCCESS;
    }
}
