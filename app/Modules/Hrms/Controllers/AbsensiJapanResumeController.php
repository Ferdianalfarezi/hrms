<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\JapanResume;
use App\Modules\Hrms\Support\JapanResumeBulanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Japan Resume: rekap kehadiran harian (Total, Hadir, Cuti, S+I, rate) per bulan, seperti file HRD "2026 summary.xlsx".
 * Hanya Admin HRMS.
 */
class AbsensiJapanResumeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        return view('hrms.absensi.japan-resume', $this->resume($request));
    }

    /**
     * Halaman cetak A4 seperti sheet bulanan Excel; dialog print langsung terbuka untuk disimpan sebagai PDF.
     */
    public function pdf(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        return view('hrms.absensi.japan-resume-pdf', $this->resume($request));
    }

    /**
     * Simpan angka satu tanggal (edit baris yang ada atau tambah tanggal baru). Baris otomatis yang diedit
     * jadi baris "manual"; data Excel yang diedit tetap tercatat sebagai data Excel.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');

        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'shift' => ['required', 'string', 'max:10'],
            'total' => ['required', 'integer', 'min:1', 'max:100000'],
            'cuti' => ['required', 'integer', 'min:0', 'max:100000'],
            'sakit_izin' => ['required', 'integer', 'min:0', 'max:100000'],
        ], [
            'required' => __(':Attribute wajib diisi.'),
            'integer' => __(':Attribute harus berupa angka bulat.'),
            'min' => __(':Attribute minimal :min.'),
            'max' => __(':Attribute maksimal :max.'),
            'date_format' => __('Format :attribute tidak valid.'),
        ], ['tanggal' => 'tanggal', 'shift' => 'shift', 'total' => 'total', 'cuti' => 'cuti', 'sakit_izin' => 'S+I']);

        if ($data['total'] < $data['cuti'] + $data['sakit_izin']) {
            throw ValidationException::withMessages(['sakit_izin' => __('Cuti + S+I tidak boleh lebih dari Total.')]);
        }

        $resume = JapanResume::whereDate('tanggal', $data['tanggal'])->first() ?? new JapanResume(['sumber' => JapanResume::SUMBER_MANUAL]);
        $resume->fill([...$data, 'updated_by' => $request->user()->id])->save();

        return $this->backToMonth($resume, __('Japan Resume :tanggal berhasil disimpan.', ['tanggal' => $resume->tanggal->format('n/j')]));
    }

    /**
     * Kembalikan tanggal yang angkanya disesuaikan admin ke hitungan otomatis dari absensi.
     * Data dari Excel tidak bisa di-reset (tidak ada hitungan otomatis untuknya), cukup diedit.
     */
    public function reset(JapanResume $japanResume): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');
        abort_unless($japanResume->sumber === JapanResume::SUMBER_MANUAL, 422, __('Data dari Excel tidak bisa dikembalikan ke otomatis, silakan edit angkanya.'));

        $japanResume->delete();

        return $this->backToMonth($japanResume, __('Japan Resume :tanggal dikembalikan ke hitungan otomatis.', ['tanggal' => $japanResume->tanggal->format('n/j')]));
    }

    private function backToMonth(JapanResume $resume, string $message): RedirectResponse
    {
        return redirect()->route('hrms.absensi.japan-resume', ['bulan' => $resume->tanggal->format('Y-m')])
            ->with('status', $message)
            ->with('highlight', $resume->tanggal->toDateString());
    }

    private function resume(Request $request): array
    {
        return JapanResumeBulanan::build($this->parseMonth((string) $request->query('bulan')) ?? Carbon::today(), Carbon::today());
    }

    private function parseMonth(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::createFromFormat('!Y-m', $value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
