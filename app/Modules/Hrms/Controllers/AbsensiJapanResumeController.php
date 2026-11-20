<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\JapanResume;
use App\Modules\Hrms\Support\JapanResumeBulanan;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
     * Excel satu bulan dengan susunan sheet bulanan file HRD: baris 1 persentase Cuti & S+I, baris 2 total bulan,
     * baris 3 judul kolom (DAY, Date, shift, Total, Hadir, Cuti, S+I, rate), lalu satu baris per hari.
     */
    public function excel(Request $request): StreamedResponse
    {
        Gate::authorize('manage-module', 'hrms');

        ['bulan' => $bulan, 'rows' => $rows, 'total' => $total] = $this->resume($request);

        return response()->streamDownload(function () use ($bulan, $rows, $total) {
            $border = new Border(...array_map(fn (BorderName $name) => new BorderPart($name), BorderName::cases()));
            $cell = (new Style)->withBorder($border);
            $tanggal = (new Style)->withBorder($border)->withFormat('m/d');
            $persen = (new Style)->withBorder($border)->withFormat('0.0%');
            $header = (new Style)->withFontBold(true)->withBackgroundColor('DCE6F1')->withBorder($border);
            $totalStyle = (new Style)->withFontBold(true)->withBackgroundColor('FFF2CC')->withBorder($border);
            $totalPersen = $totalStyle->withFormat('0.0%');
            $atasPersen = (new Style)->withFontBold(true)->withFormat('0.0%');
            $rasio = fn (?float $value) => $value === null ? null : $value / 100;

            $writer = new Writer;
            $writer->openToFile('php://output');
            $sheet = $writer->getCurrentSheet();
            $sheet->setName($bulan->format('M Y'));
            $sheet->setColumnWidth(12, 1);
            $sheet->setColumnWidth(14, 2);
            $sheet->setColumnWidth(10, 3, 4, 5, 6, 7, 8);

            $writer->addRow(new Row([
                Cell::fromValue(null), Cell::fromValue(null), Cell::fromValue(null), Cell::fromValue(null), Cell::fromValue(null),
                Cell::fromValue($rasio($total['persen_cuti']), $atasPersen),
                Cell::fromValue($rasio($total['persen_sakit_izin']), $atasPersen),
            ]));
            $writer->addRow(new Row([
                Cell::fromValue(null, $totalStyle),
                Cell::fromValue($bulan->format('M').' Total', $totalStyle),
                Cell::fromValue(null, $totalStyle),
                Cell::fromValue($total['total'], $totalStyle),
                Cell::fromValue($total['hadir'], $totalStyle),
                Cell::fromValue($total['cuti'], $totalStyle),
                Cell::fromValue($total['sakit_izin'], $totalStyle),
                Cell::fromValue($rasio($total['rate']), $totalPersen),
            ]));
            $writer->addRow(Row::fromValuesWithStyle(['DAY', 'Date', 'Shift', 'Total', 'Hadir', 'Cuti', 'S+I', 'rate'], $header));

            foreach ($rows as $row) {
                $writer->addRow(new Row([
                    Cell::fromValue($row['tanggal']->translatedFormat('l'), $cell),
                    Cell::fromValue(DateTimeImmutable::createFromInterface($row['tanggal']), $tanggal),
                    Cell::fromValue($row['shift'], $cell),
                    Cell::fromValue($row['total'], $cell),
                    Cell::fromValue($row['hadir'], $cell),
                    Cell::fromValue($row['cuti'], $cell),
                    Cell::fromValue($row['sakit_izin'], $cell),
                    Cell::fromValue($rasio($row['rate']), $persen),
                ]));
            }

            $writer->close();
        }, 'japan-resume-'.$bulan->format('Y-m').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Simpan angka satu tanggal (edit baris yang ada atau tambah tanggal baru). Angka yang disimpan selalu jadi
     * baris "manual", yang dipakai di atas hitungan dari absensi Harian maupun data Excel.
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

        $resume = JapanResume::whereDate('tanggal', $data['tanggal'])->first() ?? new JapanResume;
        $resume->fill([...$data, 'sumber' => JapanResume::SUMBER_MANUAL, 'updated_by' => $request->user()->id])->save();

        return $this->backToMonth($resume, __('Japan Resume :tanggal berhasil disimpan.', ['tanggal' => $resume->tanggal->format('n/j')]));
    }

    /**
     * Hapus angka yang disesuaikan admin: tanggal itu kembali memakai hitungan dari absensi Harian
     * (kalau belum ada data absensinya, baris tanggal itu tidak tampil; data Excel bisa dikembalikan lewat seeder).
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
