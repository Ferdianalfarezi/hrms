<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\PenilaianTahunanLog;
use Illuminate\Http\Response;

/**
 * Halaman publik hasil scan QR di kotak Penilai: memastikan tanda tangan elektronik penilaian itu asli.
 * Tidak butuh login supaya bisa dibuka dari HP siapa pun yang memindai dokumen cetaknya.
 */
class VerifikasiPenilaianController extends Controller
{
    public function __invoke(string $kode): Response
    {
        $log = PenilaianTahunanLog::query()
            ->where('kode_verifikasi', $kode)
            ->whereIn('aksi', [PenilaianTahunanLog::AKSI_KIRIM, PenilaianTahunanLog::AKSI_SETUJU])
            ->with(['user', 'penilaian.karyawan', 'penilaian.department'])
            ->first();

        return response()->view('hrms.penilaian.verifikasi', ['log' => $log], $log ? 200 : 404);
    }
}
