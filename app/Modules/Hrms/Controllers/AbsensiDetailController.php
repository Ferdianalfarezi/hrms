<?php

namespace App\Modules\Hrms\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hrms\Models\Absensi;
use App\Modules\Hrms\Models\ShiftRotasi;
use App\Modules\Hrms\Support\LaporanAbsensiShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderName;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Laporan detail ketidakhadiran per shift (format Excel "summary harian"). Hanya Admin HRMS.
 */
class AbsensiDetailController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-module', 'hrms');

        $tanggal = $this->parseDate((string) $request->query('tanggal')) ?? Carbon::today();

        return view('hrms.absensi.detail', [
            'tanggal' => $tanggal,
            'kategoris' => array_keys(Absensi::KATEGORI),
            'kodes' => Absensi::KODE,
            'grupOptions' => ShiftRotasi::GRUP,
            ...LaporanAbsensiShift::build($tanggal),
        ]);
    }

    /**
     * Export laporan detail tanggal ini ke Excel: sheet "Detail" (shift malam + shift pagi) dan sheet "Grafik" (data grafik bulanan).
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('manage-module', 'hrms');

        $tanggal = $this->parseDate((string) $request->query('tanggal')) ?? Carbon::today();
        $laporan = LaporanAbsensiShift::build($tanggal);
        $kategoris = array_keys(Absensi::KATEGORI);

        return response()->streamDownload(function () use ($tanggal, $laporan, $kategoris) {
            $border = new Border(...array_map(fn (BorderName $name) => new BorderPart($name), BorderName::cases()));
            $bold = (new Style)->withFontBold(true);
            $title = (new Style)->withFontBold(true)->withFontSize(14);
            $header = (new Style)->withFontBold(true)->withBackgroundColor('DCE6F1')->withBorder($border);
            $cell = (new Style)->withBorder($border);
            $total = (new Style)->withFontBold(true)->withBackgroundColor('F2F2F2')->withBorder($border);
            $statusMp = fn (string $status) => str_starts_with($status, 'PKWT') ? 'Kontrak' : $status;
            $kodes = Absensi::KODE;

            $writer = new Writer;
            $writer->openToFile('php://output');
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Detail');
            $sheet->setColumnWidth(6, 1);
            $sheet->setColumnWidth(10, 2);
            $sheet->setColumnWidth(30, 3);
            $sheet->setColumnWidth(24, 4);
            $sheet->setColumnWidth(40, 6 + count($kategoris));
            $sheet->setColumnWidth(13, 7 + count($kategoris));

            $writer->addRow(Row::fromValuesWithStyle(['Laporan Absensi Harian '.$tanggal->translatedFormat('l, d F Y')], $title));
            $writer->addRow(Row::fromValues([$laporan['grupPagi']
                ? "Rotasi: grup {$laporan['grupPagi']} pagi, grup ".ShiftRotasi::lawan($laporan['grupPagi']).' malam, NS 07.00 - 16.00'
                : 'Jadwal rotasi grup A/B belum diatur']));

            foreach ($laporan['sections'] as $section) {
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValuesWithStyle([
                    $section['tanggal']->translatedFormat('l').' : '.$section['tanggal']->translatedFormat('d F Y')." ({$section['judul']})",
                ], $bold));
                $writer->addRow(Row::fromValues([
                    $section['keterangan'].' · grup '.($section['grups'] ? implode(', ', $section['grups']) : '-')
                    ." · MP {$section['headcount']} · Tidak hadir {$section['rows']->count()}"
                    .($section['persen'] !== null ? ' · '.number_format($section['persen'], 1, ',', '.').'%' : ''),
                ]));
                $writer->addRow(Row::fromValuesWithStyle(
                    ['No', 'NIK', 'Nama', 'Group', 'Shift', ...$kategoris, 'Keterangan', 'Durasi Libur', 'Status MP'], $header));

                foreach ($section['rows'] as $i => $row) {
                    $karyawan = $row['karyawan'];
                    $writer->addRow(Row::fromValuesWithStyle([
                        $i + 1,
                        $karyawan->nik,
                        $karyawan->nama,
                        trim(($karyawan->department?->name ?? '').' '.($karyawan->jabatan ?? '')) ?: '-',
                        $karyawan->group,
                        ...array_map(fn ($kategori) => $row['kategori'] === $kategori ? 1 : null, $kategoris),
                        $row['keterangan'] ?: ($kodes[$row['kode']]['label'] ?? $row['kode']),
                        $row['durasi'].' Hari',
                        $statusMp($karyawan->status),
                    ], $cell));
                }

                if ($section['rows']->isEmpty()) {
                    $writer->addRow(Row::fromValuesWithStyle([$section['grups'] ? 'Semua karyawan hadir.' : 'Grup shift belum diketahui.'], $cell));
                }

                $writer->addRow(Row::fromValuesWithStyle(
                    ['Total', null, null, null, null, ...array_values($section['totals']), null, null, null], $total));
            }

            // Grafik tidak bisa ikut diexport, jadi datanya ditaruh di sheet terpisah.
            $writer->addNewSheetAndMakeItCurrent()->setName('Grafik');
            foreach ($laporan['sections'] as $section) {
                $points = $section['grafik']['points'];
                $writer->addRow(Row::fromValuesWithStyle([
                    "Ketidakhadiran {$section['judul']} (%) · ".$section['grafik']['bulan']->translatedFormat('F Y'),
                ], $bold));
                $writer->addRow(Row::fromValuesWithStyle(['Tgl', ...array_column($points, 'day')], $header));
                $writer->addRow(Row::fromValuesWithStyle(['Target', ...array_fill(0, count($points), $section['grafik']['target'])], $cell));
                $writer->addRow(Row::fromValuesWithStyle(['Actual', ...array_column($points, 'actual')], $cell));
                $writer->addRow(Row::fromValues([]));
            }

            $writer->close();
        }, 'detail-absensi-'.$tanggal->toDateString().'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Simpan titik acuan rotasi: mulai tanggal ini grup_pagi masuk pagi, lalu bertukar tiap minggu.
     */
    public function storeRotasi(Request $request): RedirectResponse
    {
        Gate::authorize('manage-module', 'hrms');

        $data = $request->validate([
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'grup_pagi' => ['required', Rule::in(ShiftRotasi::GRUP)],
        ], [
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'grup_pagi.in' => 'Grup pagi harus A atau B.',
        ]);

        ShiftRotasi::updateOrCreate(
            ['tanggal_mulai' => $data['tanggal_mulai']],
            ['grup_pagi' => $data['grup_pagi'], 'created_by' => $request->user()->id],
        );

        return redirect()
            ->route('hrms.absensi.detail', ['tanggal' => $request->input('tanggal')])
            ->with('status', 'Jadwal rotasi shift disimpan.');
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
