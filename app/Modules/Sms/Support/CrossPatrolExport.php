<?php

namespace App\Modules\Sms\Support;

use App\Modules\Sms\Models\CrossPatrol;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export Cross Patrol ke Excel dengan format "Form Temuan Safety Patrol" (K3/FMR/15), lengkap dengan foto.
 * Logo perusahaan diambil dari public/images/logo-step.png kalau file itu ada.
 */
class CrossPatrolExport
{
    /** Info dokumen di pojok kanan atas form. */
    public const DOKUMEN = [
        'NO. Dokumen' => 'K3 / FMR / 15',
        'Revisi' => '0',
        'Tanggal Efektif' => '27 April 2014',
        'Halaman' => '1 dari 1',
    ];

    public const LOGO = 'images/logo-step.png';

    /** Kolom tabel: huruf => [judul, lebar]. */
    private const COLUMNS = [
        'A' => ['No', 5],
        'B' => ['FINDING', 24],
        'C' => ['FOTO TEMUAN', 22],
        'D' => ['EFEK / IMPACT', 18],
        'E' => ['TINGKAT RESIKO BAHAYA', 11],
        'F' => ['AREA', 12],
        'G' => ['REKOMENDASI PERBAIKAN', 24],
        'H' => ['TARGET PERBAIKAN', 13],
        'I' => ['PIC', 16],
        'J' => ['FOTO PERBAIKAN', 22],
        'K' => ['KATEGORI', 16],
        'L' => ['PATROLER', 18],
        'M' => ['OPEN', 13],
        'N' => ['CLOSED', 16],
    ];

    private const HEADER_ROW = 5;

    private const FIRST_DATA_ROW = 7;

    /** Tinggi baris data (point) supaya foto terlihat jelas. */
    private const DATA_ROW_HEIGHT = 105;

    /**
     * @param  Collection<int, CrossPatrol>  $patrols
     */
    public function __construct(
        private Collection $patrols,
        private ?string $tanggal = null,
        private ?string $plant = null,
        private ?int $zona = null,
    ) {}

    public function download(): StreamedResponse
    {
        $spreadsheet = $this->build();
        $filename = 'Cross Patrol '.($this->tanggal ?? Carbon::today()->toDateString()).'.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Cross Patrol');

        foreach (self::COLUMNS as $column => [, $width]) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $this->writeFormHeader($sheet);
        $this->writeTableHeader($sheet);
        $lastRow = $this->writeRows($sheet);

        // Siap cetak: landscape, muat selebar 1 halaman, judul tabel diulang di tiap halaman.
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(self::HEADER_ROW, self::HEADER_ROW + 1)
            ->setPrintArea('A1:N'.$lastRow);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.3);
        $sheet->freezePane('A'.self::FIRST_DATA_ROW);

        return $spreadsheet;
    }

    private function writeFormHeader(Worksheet $sheet): void
    {
        // Baris 1 tinggi untuk logo; baris 2-4 untuk PLANT, ZONA, TANGGAL.
        $sheet->getRowDimension(1)->setRowHeight(46);
        foreach (range(2, 4) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(16);
        }

        $logo = public_path(self::LOGO);
        if (is_file($logo)) {
            $drawing = new Drawing;
            $drawing->setPath($logo)->setCoordinates('A1')->setOffsetX(8)->setOffsetY(4)->setResizeProportional(true)->setHeight(52);
            $drawing->setWorksheet($sheet);
        }
        $sheet->mergeCells('A1:B1');
        $sheet->mergeCells('A2:B2')->setCellValue('A2', 'PLANT        : '.$this->plantLabel());
        $sheet->mergeCells('A3:B3')->setCellValue('A3', 'ZONA         : '.$this->zonaLabel());
        $sheet->mergeCells('A4:B4')->setCellValue('A4', 'TANGGAL   : '.$this->tanggalLabel());
        $sheet->getStyle('A2:B4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Tengah: judul form.
        $sheet->mergeCells('C1:L4')->setCellValue('C1', 'FORM TEMUAN SAFETY PATROL');
        $sheet->getStyle('C1')->getFont()->setName('Cambria')->setSize(28)->setBold(true);
        $sheet->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Kanan: info dokumen.
        $row = 1;
        foreach (self::DOKUMEN as $label => $value) {
            $sheet->setCellValue("M{$row}", $label);
            $sheet->setCellValueExplicit("N{$row}", ': '.$value, DataType::TYPE_STRING);
            $row++;
        }
        $sheet->getStyle('M1:N4')->getFont()->setSize(9);
        $sheet->getStyle('M1:N4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        foreach (['A1:B4', 'C1:L4', 'M1:N4'] as $range) {
            $sheet->getStyle($range)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
        }
    }

    private function writeTableHeader(Worksheet $sheet): void
    {
        $top = self::HEADER_ROW;
        $bottom = $top + 1;
        $sheet->getRowDimension($top)->setRowHeight(24);
        $sheet->getRowDimension($bottom)->setRowHeight(24);

        foreach (self::COLUMNS as $column => [$title]) {
            if (in_array($column, ['M', 'N'], true)) {
                $sheet->setCellValue("{$column}{$bottom}", $title);

                continue;
            }
            $sheet->mergeCells("{$column}{$top}:{$column}{$bottom}")->setCellValue("{$column}{$top}", $title);
        }
        $sheet->mergeCells("M{$top}:N{$top}")->setCellValue("M{$top}", 'STATUS');

        $style = $sheet->getStyle("A{$top}:N{$bottom}");
        $style->getFont()->setName('Cambria')->setBold(true)->setSize(11);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("M{$bottom}:N{$bottom}")->getFont()->setSize(9);
    }

    private function writeRows(Worksheet $sheet): int
    {
        $row = self::FIRST_DATA_ROW;

        foreach ($this->patrols->values() as $index => $patrol) {
            $sheet->getRowDimension($row)->setRowHeight(self::DATA_ROW_HEIGHT);
            $sheet->setCellValue("A{$row}", $index + 1);
            $sheet->setCellValue("B{$row}", $patrol->finding);
            $sheet->setCellValue("D{$row}", $patrol->efek);
            $sheet->setCellValue("E{$row}", $patrol->resikoLabel());
            $sheet->setCellValue("F{$row}", $patrol->area);
            $sheet->setCellValue("G{$row}", $patrol->rekomendasi);
            $sheet->setCellValueExplicit("H{$row}", $patrol->target->format('d.m.Y'), DataType::TYPE_STRING);
            $sheet->setCellValue("I{$row}", $patrol->pic);
            $sheet->setCellValue("K{$row}", $patrol->kategoriExportLabel());
            $sheet->setCellValue("L{$row}", $patrol->patroler);

            $this->placePhoto($sheet, $patrol->foto, 'C', $row);
            if ($patrol->foto_perbaikan) {
                $this->placePhoto($sheet, $patrol->foto_perbaikan, 'J', $row);
            }

            // Status ditandai dengan sel berwarna kuning di kolom OPEN atau CLOSED, seperti form aslinya.
            $sheet->getStyle(($patrol->isClosed() ? 'N' : 'M').$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');

            $row++;
        }

        $lastRow = max($row - 1, self::FIRST_DATA_ROW);
        if ($this->patrols->isEmpty()) {
            $sheet->mergeCells("A{$lastRow}:N{$lastRow}")->setCellValue("A{$lastRow}", 'Tidak ada temuan.');
            $sheet->getRowDimension($lastRow)->setRowHeight(30);
        }

        $style = $sheet->getStyle('A'.self::FIRST_DATA_ROW.":N{$lastRow}");
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return $lastRow;
    }

    /**
     * Taruh foto di tengah sel, diperkecil supaya muat di dalam sel.
     */
    private function placePhoto(Worksheet $sheet, ?string $path, string $column, int $row): void
    {
        $disk = Storage::disk('public');
        if (! $path || ! $disk->exists($path)) {
            return;
        }

        $file = $disk->path($path);
        $size = @getimagesize($file);
        if (! $size) {
            return;
        }

        // Lebar kolom (karakter) -> piksel kira-kira; tinggi baris (point) -> piksel.
        // Perkiraan dibuat sedikit lebih kecil supaya foto tidak melewati garis sel di Excel maupun LibreOffice.
        $cellWidth = (int) (self::COLUMNS[$column][1] * 6.6);
        $cellHeight = (int) (self::DATA_ROW_HEIGHT * 96 / 72);
        $scale = min(($cellWidth - 16) / $size[0], ($cellHeight - 16) / $size[1]);
        $width = (int) round($size[0] * $scale);
        $height = (int) round($size[1] * $scale);

        $drawing = new Drawing;
        $drawing->setPath($file)
            ->setCoordinates($column.$row)
            ->setResizeProportional(false)
            ->setWidth($width)
            ->setHeight($height)
            ->setOffsetX((int) (($cellWidth - $width) / 2))
            ->setOffsetY((int) (($cellHeight - $height) / 2));
        $drawing->setWorksheet($sheet);
    }

    private function plantLabel(): string
    {
        if ($this->plant !== null) {
            return $this->plant;
        }

        $plants = $this->patrols->pluck('plant')->unique()->values();

        return $plants->isEmpty() ? '-' : $plants->implode(', ');
    }

    private function zonaLabel(): string
    {
        if ($this->zona !== null) {
            return 'Zona '.$this->zona;
        }

        $zonas = $this->patrols->pluck('zona')->filter()->unique()->sort()->values();

        return $zonas->isEmpty() ? '-' : $zonas->map(fn ($zona) => 'Zona '.$zona)->implode(', ');
    }

    private function tanggalLabel(): string
    {
        if ($this->tanggal !== null) {
            return Carbon::parse($this->tanggal)->translatedFormat('d F Y');
        }

        $dates = $this->patrols->pluck('tanggal')->filter();
        if ($dates->isEmpty()) {
            return '-';
        }

        $first = $dates->min();
        $last = $dates->max();

        return $first->isSameDay($last)
            ? $first->translatedFormat('d F Y')
            : $first->translatedFormat('d F Y').' - '.$last->translatedFormat('d F Y');
    }
}
