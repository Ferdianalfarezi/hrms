@use('App\Modules\Hrms\Models\PenilaianTahunan')
@use('App\Modules\Hrms\Support\FormPenilaian')
@php
    $angka = fn (float $value) => number_format($value, 2, ',', '.');
    $absen = $penilaian->absen;
    $skor = $penilaian->skor ?? [];
    $kolomAbsen = ['0', '1 x', '2 x', '3 x', '4 x', '5 x', '6 x', '7 ~ 10', '11~15', '≥ 16'];
    $singkatanAbsen = ['A' => 'A', 'I' => 'I', 'S' => 'S', 'DT' => 'DT', 'DT30' => __('DT > 30 mnt'), 'PC' => 'PC', 'PC30' => __('PC > 30 mnt'), 'SD' => 'SD'];
    // Status karyawan di data (PKWT) => pilihan di form kertas (KKWT).
    $statusForm = ['PKWT 1' => 'KKWT 1', 'PKWT 2' => 'KKWT 2', 'PKWT 3' => 'KKWT 2+', 'Tetap' => 'Tetap'];
    $statusTerpilih = $statusForm[$karyawan->status] ?? null;
    $bulat = fn (bool $isi) => $isi ? '●' : '○';
    // Label pendek di kotak Penilai seperti form kertas; kolom yang di-override memakai jabatan yang meng-override.
    $labelPendek = ['asst_leader' => 'Asst. Leader', 'leader' => 'Leader', 'section' => 'Sec. Chief', 'manager' => 'Manager', 'gm' => 'GM'];
    $labelKolom = fn (array $tahap) => $labelPendek[$tahap['override'] ? $tahap['log']->level : $tahap['level']] ?? $tahap['label'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Penilaian') }} {{ $karyawan->nik }} - {{ $karyawan->nama }} ({{ $tahun }})</title>
    <style>
        @page { size: A4 portrait; margin: 8mm 9mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; line-height: 1.2; }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; gap: 8px; padding: 10px; background: #1e3a8a; }
        .toolbar button { padding: 8px 18px; border: 0; border-radius: 999px; font: 600 13px Arial, sans-serif; cursor: pointer; background: #fff; color: #1e3a8a; }
        .toolbar button.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.6); }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 8mm 9mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 1.5px 3px; vertical-align: middle; }
        th { font-weight: 700; text-align: center; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: 700; }
        .lay, .lay > tbody > tr > td, .lay > tr > td { border: 0; padding: 0; vertical-align: top; }
        .gap { height: 5px; }

        /* Kop */
        .kop td { border: 1px solid #000; }
        .kop .logo { width: 30%; text-align: center; padding: 3px 4px; }
        .kop .logo img { height: 13mm; display: block; margin: 0 auto 2px; }
        .kop .logo span { font-size: 7.5pt; font-weight: 700; }
        .kop .judul { text-align: center; font-family: 'Times New Roman', serif; font-size: 13pt; font-weight: 700; line-height: 1.15; }
        .kop .dok { width: 31%; padding: 2px 5px; font-family: 'Times New Roman', serif; font-size: 9pt; }
        .kop .dok table td { border: 0; padding: 0 2px; white-space: nowrap; }
        .ident td { font-size: 7pt; height: 5.5mm; }
        .ident td:first-child { width: 22%; }
        .ringkas th, .ringkas td { width: 12.5%; text-align: center; font-size: 6.5pt; }
        .ringkas td { height: 5mm; font-size: 8pt; font-weight: 700; }
        .range th { background: #f8cbad; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-family: 'Times New Roman', serif; font-size: 8pt; }
        .range td { text-align: center; font-family: 'Times New Roman', serif; font-size: 8pt; }
        .range tr.on td { font-weight: 700; background: #fde9d9; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sec-title { font-weight: 700; font-size: 8.5pt; padding: 5px 0 2px; }
        .matriks th { font-size: 6.5pt; }
        .matriks td { text-align: center; }
        .matriks td:first-child { text-align: left; }
        .matriks td.on span { display: inline-block; min-width: 15px; padding: 0 2px; border: 1.3px solid #000; border-radius: 999px; font-weight: 700; }
        .rumus { display: flex; justify-content: space-between; gap: 8px; font-size: 7pt; padding-top: 1px; }

        /* Performance */
        .mark { font-size: 8pt; font-weight: 700; line-height: 1; }

        /* Footer */
        .pilihan td { font-size: 8pt; }
        .pilihan .o { width: 6mm; text-align: center; font-size: 10pt; line-height: 1; }
        .rekom { border: 1px solid #000; height: 22mm; padding: 3px 5px; display: flex; flex-direction: column; justify-content: space-between; }
        .rekom .note { font-size: 6.5pt; font-style: italic; }
        .nilai-akhir th { font-family: 'Times New Roman', serif; font-size: 9pt; }
        .nilai-akhir .lbl { text-align: center; font-size: 6.5pt; font-weight: 700; height: 5mm; }
        .nilai-akhir .val { text-align: center; font-size: 9pt; font-weight: 700; height: 6mm; }
        .nilai-akhir .catatan { vertical-align: top; font-size: 7pt; white-space: pre-line; width: 56%; }
        .nilai-akhir .catatan u { display: block; margin-bottom: 2px; font-size: 8pt; }
        .ttd { table-layout: fixed; }
        .ttd th { font-family: 'Times New Roman', serif; font-size: 8pt; }
        .ttd td.isi { height: 17mm; text-align: center; vertical-align: middle; font-size: 6.5pt; }
        .ttd td.jab { text-align: center; font-family: 'Times New Roman', serif; font-size: 8pt; }
        .foot { margin-top: 4px; font-size: 6.5pt; color: #555; display: flex; justify-content: space-between; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; zoom: var(--fit, 1); }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">{{ __('Print / Simpan PDF') }}</button>
    <button type="button" class="ghost" onclick="window.close()">{{ __('Tutup') }}</button>
</div>

<div class="sheet">
    {{-- ===== HEADER ===== --}}
    <table class="kop">
        <tr>
            <td class="logo">
                <img src="{{ asset('images/step-logo.jpeg') }}" alt="{{ __('STEP') }}">
                <span>{{ __('PT. SARI TAKAGI ELOK PRODUK') }}</span>
            </td>
            <td class="judul">{{ __('FORM PENILAIAN') }}<br>{{ __($form['judul']) }}</td>
            <td class="dok">
                <table>
                    <tr><td>{{ __('No. Dokumen') }}</td><td>: {{ str_replace('/', ' / ', $form['dokumen']) }}</td></tr>
                    <tr><td>{{ __('Tanggal efektif') }}</td><td>: {{ $form['tanggal_efektif'] }}</td></tr>
                    <tr><td>{{ __('No. Revisi') }}</td><td>: {{ $form['revisi'] }}</td></tr>
                    <tr><td>{{ __('Halaman') }}</td><td>{{ __(': 1 dari 1') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="gap"></div>

    <table class="lay">
        <tr>
            <td style="width: 42%;">
                <table class="ident">
                    <tr><td>{{ __('Nama') }}</td><td class="b">{{ $karyawan->nama }}</td></tr>
                    <tr><td>{{ __('Bagian') }}</td><td class="b">{{ $penilaian->department?->name ?? '-' }} · {{ $penilaian->jabatan ?? $karyawan->jabatan }}</td></tr>
                </table>
            </td>
            <td></td>
            <td style="width: 32%;">
                <table class="ident">
                    <tr><td>N.I.K</td><td class="b">{{ $karyawan->nik }}</td></tr>
                    <tr><td>{{ __('Periode') }}</td><td class="b">{{ __('Januari - Desember :tahun', ['tahun' => $tahun]) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="gap"></div>

    <table class="lay">
        <tr>
            <td style="padding-right: 8px;">
                <table class="ringkas" style="width: 72%; margin-left: auto;">
                    <tr>
                        @foreach ($singkatanAbsen as $singkatan)
                            <th>{{ $singkatan }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach (array_keys($singkatanAbsen) as $kategori)
                            <td>{{ $absen['rincian'][$kategori]['jumlah'] ?? 0 }}</td>
                        @endforeach
                    </tr>
                </table>

                <div class="sec-title">{{ __('I. ABSEN (di isi oleh HRD)') }}</div>
                <table class="matriks">
                    <tr>
                        <th rowspan="2" style="width: 27%;">{{ __('Kategori Absen') }}</th>
                        <th colspan="{{ count($kolomAbsen) }}">{{ __('Banyaknya Absen') }}</th>
                        <th rowspan="2" style="width: 7%;">{{ __('Nilai') }}</th>
                    </tr>
                    <tr>
                        @foreach ($kolomAbsen as $kolom)
                            <th>{{ $kolom }}</th>
                        @endforeach
                    </tr>
                    @foreach (FormPenilaian::KONVERSI_ABSEN as $kategori => $konversi)
                        @php($terpakai = FormPenilaian::kolomAbsen($absen['rincian'][$kategori]['jumlah'] ?? 0))
                        <tr>
                            <td>{{ __($konversi['label']) }}</td>
                            @foreach ($konversi['poin'] as $index => $poin)
                                <td class="{{ $index === $terpakai ? 'on' : '' }}"><span>{{ $poin }}</span></td>
                            @endforeach
                            <td class="b">{{ $absen['rincian'][$kategori]['poin'] ?? 5 }}</td>
                        </tr>
                    @endforeach
                    <tr class="b">
                        <td colspan="{{ count($kolomAbsen) + 1 }}">{{ __('TOTAL') }}</td>
                        <td>{{ $absen['total_poin'] }}</td>
                    </tr>
                </table>
                <div class="rumus">
                    <span>{{ __('Nilai Maksimum = :maks (a) · Bobot Absen = :bobot% (b)', ['maks' => FormPenilaian::MAKS_ABSEN, 'bobot' => $form['bobot_absen']]) }}</span>
                    <span>{{ __('Nilai akhir = (Total nilai / a) x (b% x 100) =') }} <b>{{ $angka($penilaian->nilai_absen) }}</b></span>
                </div>
            </td>
            <td style="width: 24%;">
                <table class="range">
                    <tr><th colspan="2">{{ __('RANGE NILAI AKHIR') }}</th></tr>
                    <tr><th>{{ __('ANGKA') }}</th><th>{{ __('HURUF') }}</th></tr>
                    @foreach (['91 ~ 100' => 'A', '80 ~ 90' => 'B', '60 ~ 79' => 'C', '39 ~ 59' => 'D', '16 ~ 38' => 'E'] as $range => $huruf)
                        <tr class="{{ $penilaian->huruf === $huruf ? 'on' : '' }}"><td>{{ $range }}</td><td>{{ $huruf }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    {{-- ===== II - VI. PERFORMANCE ===== --}}
    @foreach ($form['butir'] as $kategori => $butirs)
        <div class="sec-title">{{ Str::upper(__(FormPenilaian::KATEGORI[$kategori])) }}</div>
        <table>
            <tr>
                <th style="width: 4%;">{{ __('No') }}</th>
                <th style="text-align: left;">{{ __('Faktor Penilaian') }}</th>
                @foreach (FormPenilaian::NILAI_HURUF as $huruf => $nilai)
                    <th style="width: 5%; line-height: 1;">{{ $huruf }}<br><span style="font-weight: 400; font-size: 6pt;">{{ $nilai }}</span></th>
                @endforeach
            </tr>
            @foreach ($butirs as $index => $butir)
                <tr>
                    <td class="c">{{ $index + 1 }}</td>
                    <td>{{ __($butir) }}</td>
                    @foreach (FormPenilaian::NILAI_HURUF as $huruf => $nilai)
                        <td class="c mark">{{ ($skor[$kategori][$index] ?? null) === $huruf ? '✓' : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
        <div class="rumus">
            <span>{{ __('Total nilai =') }} <b>{{ $performance['kategori'][$kategori]['total'] }}</b> · {{ __('Nilai Max = :maks (a) · Bobot = :bobot% (b)', ['maks' => count($butirs) * 5, 'bobot' => $form['bobot_kategori']]) }}</span>
            <span>{{ __('Nilai Akhir = (Total nilai / a) x (b% x 100) =') }} <b>{{ $angka($performance['kategori'][$kategori]['nilai']) }}</b></span>
        </div>
    @endforeach

    <div class="gap"></div>
    <div class="gap"></div>

    {{-- ===== FOOTER ===== --}}
    <table class="lay">
        <tr>
            <td style="width: 41%; padding-right: 10px;">
                <table class="pilihan" style="width: 92%;">
                    @foreach (PenilaianTahunan::HASIL as $option)
                        <tr>
                            @if ($loop->first)
                                <td rowspan="{{ count(PenilaianTahunan::HASIL) }}" style="width: 36%;">{{ __('Hasil Penilaian') }}</td>
                            @endif
                            <td class="o">{{ $bulat($penilaian->hasil === $option) }}</td>
                            <td>{{ __($option) }}</td>
                        </tr>
                    @endforeach
                </table>

                <div class="gap"></div>

                <table class="pilihan" style="width: 92%;">
                    <tr>
                        <td rowspan="3" style="width: 36%; vertical-align: top;">{{ __('Status Karyawan*') }}</td>
                        <td class="o">{{ $bulat($statusTerpilih === 'KKWT 1') }}</td>
                        <td>{{ __('KKWT 1*') }}</td>
                        <td class="o">{{ $bulat($statusTerpilih === 'Tetap') }}</td>
                        <td>{{ __('Tetap*') }}</td>
                    </tr>
                    <tr>
                        <td class="o">{{ $bulat($statusTerpilih === 'KKWT 2') }}</td>
                        <td colspan="3">{{ __('KKWT 2*') }}</td>
                    </tr>
                    <tr>
                        <td class="o">{{ $bulat($statusTerpilih === 'KKWT 2+') }}</td>
                        <td colspan="3">{{ __('KKWT 2+*') }}</td>
                    </tr>
                </table>

                <div class="gap"></div>

                <div class="rekom">
                    <u>{{ __('Rekomendasi Akhir HRD*') }}</u>
                    <span class="note">{{ __('Note : Tanda (**) diisi oleh HRD') }}</span>
                </div>
            </td>
            <td>
                <table class="nilai-akhir">
                    <tr><th colspan="3">{{ __('NILAI AKHIR') }}</th></tr>
                    <tr>
                        <td class="catatan" rowspan="5"><u>{{ __('Catatan Atasan') }}</u>{{ $penilaian->catatan ?: '' }}</td>
                        <td class="lbl" colspan="2">{{ __('NILAI ABSEN') }}</td>
                    </tr>
                    <tr><td class="val" colspan="2">{{ $angka($penilaian->nilai_absen) }}</td></tr>
                    <tr><td class="lbl" colspan="2">{{ __('PERFORMANCE') }}</td></tr>
                    <tr><td class="val" colspan="2">{{ $angka($penilaian->nilai_performance) }}</td></tr>
                    <tr><td class="lbl">{{ __('ANGKA') }}</td><td class="lbl">{{ __('HURUF') }}</td></tr>
                    <tr>
                        <td style="font-family: 'Times New Roman', serif; font-size: 8.5pt;">{{ __('TOTAL NILAI AKHIR **') }}</td>
                        <td class="val">{{ $angka($penilaian->nilai_akhir) }}</td>
                        <td class="val">{{ $penilaian->huruf }}</td>
                    </tr>
                </table>

                <table class="ttd" style="width: 84%; margin: -1px 0 0 auto;">
                    <tr><th colspan="{{ count($penilaiPerTahap) }}">{{ __('Penilai') }}</th></tr>
                    <tr>
                        @foreach (array_reverse($penilaiPerTahap) as $tahap)
                            <td class="isi" style="width: {{ 100 / count($penilaiPerTahap) }}%;"></td>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach (array_reverse($penilaiPerTahap) as $tahap)
                            <td class="jab">{{ $tahap['log']?->user?->name ?? $labelKolom($tahap) }}</td>
                        @endforeach
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="foot">
        <span>{{ __('Disetujui secara elektronik melalui HR System.') }}</span>
        <span>{{ __('Dicetak :waktu oleh :nama', ['waktu' => now()->translatedFormat('d M Y H:i'), 'nama' => auth()->user()->name]) }}</span>
    </div>
</div>

<script>
    // Supaya tetap 1 lembar A4: kalau isi lebih tinggi dari area cetak (297mm - margin 16mm), perkecil saat print.
    function pasSatuHalaman() {
        var sheet = document.querySelector('.sheet');
        var mm = sheet.offsetWidth / 210;
        var tinggiIsi = sheet.scrollHeight - 16 * mm;
        var tinggiHalaman = 270 * mm;
        document.documentElement.style.setProperty('--fit', Math.min(1, tinggiHalaman / tinggiIsi).toFixed(3));
    }
    window.addEventListener('load', function () {
        pasSatuHalaman();
        setTimeout(function () { window.print(); }, 300);
    });
</script>
</body>
</html>
