@use('App\Modules\Hrms\Models\PenilaianTahunan')
@use('App\Modules\Hrms\Support\FormPenilaian')
@use('App\Modules\Hrms\Support\QrCode')
@php
    // Tata letak mengikuti form kertas HRD (contoh: FORM PENILAIAN AST. LEADER, HRD / FMR / 34).
    $angka = fn (float $value) => number_format($value, 2, ',', '.');
    $absen = $penilaian->absen;
    $skor = $penilaian->skor ?? [];
    $kolomAbsen = ['0', '1 x', '2 x', '3 x', '4 x', '5 x', '6 x', '7 ~ 10', '11~15', '≥ 16'];
    $singkatanAbsen = ['A' => 'A', 'I' => 'I', 'S' => 'S', 'DT' => 'DT', 'DT30' => 'DT > 30 mnt', 'PC' => 'PC', 'PC30' => 'PC > 30 mnt', 'SD' => 'SD'];
    $levelForm = ['operator' => 'Operator', 'asst_leader' => 'Asst. Leader', 'leader' => 'Leader', 'section' => 'Section Chief & Ast. Manager', 'manager' => 'Manager'];
    // Status karyawan di data (PKWT) => pilihan di form kertas Operator (KKWT).
    $statusForm = ['PKWT 1' => 'KKWT 1', 'PKWT 2' => 'KKWT 2', 'PKWT 3' => 'KKWT 2+', 'Tetap' => 'Tetap'];
    $statusTerpilih = $statusForm[$karyawan->status] ?? null;
    $bulat = fn (bool $isi) => $isi ? '●' : '○';
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
        body { margin: 0; background: #e5e7eb; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 7pt; line-height: 1.15; }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; gap: 8px; padding: 10px; background: #1e3a8a; }
        .toolbar button { padding: 8px 18px; border: 0; border-radius: 999px; font: 600 13px Arial, sans-serif; cursor: pointer; background: #fff; color: #1e3a8a; }
        .toolbar button.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.6); }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 8mm 9mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
        .frame { border: 1px solid #000; padding: 1.5mm 2mm 4mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 1px 3px; vertical-align: middle; font-weight: 400; }
        .c { text-align: center; }
        .b { font-weight: 700; }
        .times { font-family: 'Times New Roman', Times, serif; }
        .lay, .lay > tbody > tr > td, .lay > tr > td { border: 0; padding: 0; vertical-align: top; }
        td.nb, th.nb { border: 0; }
        .gap { height: 2.2mm; }

        /* Kop */
        .kop td { vertical-align: top; }
        .kop .logo { width: 27%; height: 16mm; padding: 1mm 2mm; position: relative; }
        .kop .logo img { height: 9mm; display: block; margin: 0 auto; }
        .kop .logo span { position: absolute; left: 2mm; bottom: 1mm; font-size: 7pt; font-weight: 700; }
        .kop .judul { text-align: center; font-size: 10pt; font-weight: 700; padding-top: 2.5mm; line-height: 1.25; }
        .kop .dok { width: 26%; padding: 1mm 1.5mm; border-left: 1px solid #000; }
        .kop .dok table td { border: 0; padding: 0 1px; font-size: 6.5pt; white-space: nowrap; line-height: 1.5; }

        /* Identitas */
        .ident td { height: 3.8mm; font-size: 7pt; padding: 0 2px; }
        .ident td.lbl { width: 26%; }

        /* I. Absen */
        .absen-judul { font-weight: 700; font-size: 7pt; padding-top: 3.2mm !important; }
        .ringkas th { font-family: 'Times New Roman', Times, serif; font-size: 7.5pt; height: 5.5mm; width: 12.5%; text-align: center; line-height: 1; }
        .ringkas th small { font-size: 5.5pt; }
        .ringkas td { height: 3.6mm; text-align: center; font-size: 7pt; font-weight: 700; }
        .range { width: 100%; }
        .range th { font-family: 'Times New Roman', Times, serif; font-weight: 700; font-size: 7.5pt; background: #f8cbad; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .range td { font-family: 'Times New Roman', Times, serif; font-size: 8pt; text-align: center; height: 3.6mm; padding: 0; }
        .range tr.on td { font-weight: 700; background: #fde9d9; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .matriks { table-layout: fixed; }
        .matriks th { font-size: 6pt; text-align: center; padding: 0 1px; }
        .matriks th.kat { font-weight: 700; font-size: 7pt; }
        .matriks th.banyak { font-weight: 700; font-size: 7pt; }
        .matriks td { text-align: center; font-size: 6.5pt; padding: 0 1px; height: 3.3mm; }
        .matriks td.kat { text-align: left; font-size: 6.5pt; padding-left: 2px; }
        .matriks td.on span { display: inline-block; min-width: 13px; border: 1.2px solid #000; border-radius: 999px; font-weight: 700; }
        .matriks td.rumus { border: 0; text-align: left; font-size: 5.5pt; height: 3mm; padding: 0 1px; white-space: nowrap; }
        .matriks td.akhir { font-weight: 700; font-size: 7pt; }
        .bobot-absen { font-size: 5.5pt; padding-top: .3mm; }

        /* II - VI. Performance */
        .nilai { table-layout: fixed; }
        .nilai td { height: 3.4mm; padding: 0 2px; font-size: 6.5pt; }
        .nilai td.no { text-align: center; }
        .nilai td.skor { text-align: center; font-weight: 700; font-size: 7.5pt; line-height: 1; }
        .nilai td.head { text-align: center; font-size: 6.5pt; }
        .nilai td.sec { border: 0; font-weight: 700; font-size: 7pt; padding: 1.6mm 0 .3mm; vertical-align: bottom; }
        .nilai td.rumus { border: 0; font-size: 5.5pt; font-weight: 700; white-space: nowrap; height: 3mm; }
        .nilai td.total { text-align: center; font-weight: 700; font-size: 7pt; }
        .nilai td.sama { font-size: 7pt; font-weight: 700; padding-left: 2px; }

        /* Bagian bawah */
        .hasil td { font-size: 7pt; height: 4mm; }
        .hasil td.o { width: 7mm; text-align: center; font-size: 10pt; line-height: 1; border-right: 0; }
        .hasil td.opsi { border-left: 0; }
        .ttd { table-layout: fixed; }
        .ttd th { font-family: 'Times New Roman', Times, serif; font-weight: 700; font-size: 8pt; height: 4.5mm; }
        .ttd td.isi { height: 17mm; text-align: center; vertical-align: middle; padding: 1mm 1px .5mm; }
        .ttd .qr svg { display: block; width: 11.5mm; height: 11.5mm; margin: 0 auto; }
        .ttd .qr-tgl { margin-top: .4mm; font-size: 5pt; color: #333; }
        .ttd td.nama { height: 4mm; text-align: center; font-family: 'Times New Roman', Times, serif; font-size: 6pt; line-height: 1.05; padding: .3mm 1px; overflow-wrap: anywhere; }
        .nilai-akhir th { font-family: 'Times New Roman', Times, serif; font-weight: 700; font-size: 8pt; height: 4.5mm; }
        .nilai-akhir td { font-size: 6.5pt; height: 3.8mm; }
        .nilai-akhir td.catatan { width: 63%; vertical-align: top; font-family: 'Times New Roman', Times, serif; font-size: 7.5pt; white-space: pre-line; border-bottom: 0; }
        .nilai-akhir td.catatan u { display: block; margin-bottom: 1px; }
        .nilai-akhir td.lbl { text-align: center; font-weight: 700; font-size: 5.5pt; }
        .nilai-akhir td.val { text-align: center; font-weight: 700; font-size: 7.5pt; }
        .nilai-akhir td.total { font-family: 'Times New Roman', Times, serif; font-size: 8pt; }
        .rekom { border: 1px solid #000; height: 10mm; padding: .6mm 1.5mm; font-size: 5.5pt; }
        .status td { font-size: 6.5pt; height: 3.6mm; }
        .status td.o { width: 6mm; text-align: center; font-size: 9pt; line-height: 1; }
        .foot { margin-top: 3px; font-size: 6.5pt; color: #555; display: flex; justify-content: space-between; }
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
<div class="frame">
    {{-- ===== KOP ===== --}}
    <table class="kop">
        <tr>
            <td class="logo">
                <img src="{{ asset('images/step-logo.jpeg') }}" alt="STEP">
                <span>{{ __('PT. SARI TAKAGI ELOK PRODUK') }}</span>
            </td>
            <td class="judul">{{ __('FORM PENILAIAN') }}<br>{{ __($form['judul']) }}</td>
            <td class="dok">
                <table>
                    <tr><td>{{ __('No. Dokumen') }}</td><td>: {{ str_replace('/', ' / ', $form['dokumen']) }}</td></tr>
                    <tr><td>{{ __('Tanggal Diterbitkan') }}</td><td>: {{ $form['tanggal_efektif'] }}</td></tr>
                    <tr><td>{{ __('No. Revisi') }}</td><td>: {{ $form['revisi'] }}</td></tr>
                    <tr><td>{{ __('Halaman') }}</td><td>{{ __(': 1 dari 1') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="gap"></div>

    {{-- ===== IDENTITAS ===== --}}
    <table class="lay">
        <tr>
            <td style="width: 33%;">
                <table class="ident">
                    <tr><td class="lbl">{{ __('Nama') }}</td><td class="b">{{ $karyawan->nama }}</td></tr>
                    <tr><td class="lbl">{{ __('Bagian') }}</td><td class="b">{{ $penilaian->department?->name ?? '-' }}</td></tr>
                </table>
            </td>
            <td></td>
            <td style="width: 33%;">
                <table class="ident">
                    <tr><td class="lbl" style="width: 24%;">N.I.K</td><td class="b">{{ $karyawan->nik }}</td></tr>
                    <tr><td class="lbl" style="width: 24%;">{{ __('Periode') }}</td><td class="b">{{ __('Januari - Desember :tahun', ['tahun' => $tahun]) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ===== I. ABSEN + RANGE NILAI AKHIR ===== --}}
    <table class="lay" style="margin-top: 1.5mm;">
        <tr>
            <td style="width: 71%;">
                <table class="lay">
                    <tr>
                        <td class="absen-judul" style="width: 30.5%;">{{ __('I. ABSEN (di isi oleh HRD )') }}</td>
                        <td style="width: 53%;">
                            <table class="ringkas">
                                <tr>
                                    @foreach ($singkatanAbsen as $kategori => $singkatan)
                                        <th>@if (in_array($kategori, ['DT30', 'PC30'], true))<small>{{ Str::before($singkatan, ' > ') }} &gt;<br>30 mnt</small>@else{{ $singkatan }}@endif</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    @foreach (array_keys($singkatanAbsen) as $kategori)
                                        <td>{{ $absen['rincian'][$kategori]['jumlah'] ?? 0 }}</td>
                                    @endforeach
                                </tr>
                            </table>
                        </td>
                        <td></td>
                    </tr>
                </table>

                <table class="matriks" style="margin-top: 2.2mm;">
                    <colgroup>
                        <col style="width: 27.5%;">
                        @foreach ($kolomAbsen as $kolom)
                            <col style="width: 6.5%;">
                        @endforeach
                        <col style="width: 7.5%;">
                    </colgroup>
                    <tr>
                        <th rowspan="2" class="kat">{{ __('Kategori Absen') }}</th>
                        <th colspan="{{ count($kolomAbsen) }}" class="banyak">{{ __('Banyaknya Absen') }}</th>
                        <th class="nb" rowspan="2"></th>
                    </tr>
                    <tr>
                        @foreach ($kolomAbsen as $kolom)
                            <th class="b">{{ $kolom }}</th>
                        @endforeach
                    </tr>
                    @foreach (FormPenilaian::KONVERSI_ABSEN as $kategori => $konversi)
                        @php($terpakai = FormPenilaian::kolomAbsen($absen['rincian'][$kategori]['jumlah'] ?? 0))
                        <tr>
                            <td class="kat">{{ __($konversi['label']) }}</td>
                            @foreach ($konversi['poin'] as $index => $poin)
                                <td @class(['on' => $index === $terpakai])><span>{{ $poin }}</span></td>
                            @endforeach
                            <td class="nb"></td>
                        </tr>
                    @endforeach
                    {{-- Kolom tambahan setelah ≥ 16: total nilai di baris TOTAL, nilai akhir absen tepat di bawahnya. --}}
                    <tr>
                        <td class="kat">{{ __('TOTAL') }}</td>
                        @foreach ($kolomAbsen as $kolom)
                            <td></td>
                        @endforeach
                        <td class="b">{{ $absen['total_poin'] }}</td>
                    </tr>
                    <tr>
                        <td class="rumus">{{ __('Nilai Maksimum = :maks (a)', ['maks' => FormPenilaian::MAKS_ABSEN]) }}</td>
                        <td class="rumus" colspan="{{ count($kolomAbsen) }}" style="text-align: right; padding-right: 3px;">{{ __('Nilai akhir = (Total nilai / a ) x ( b% x 100)') }}</td>
                        <td class="akhir">{{ $angka($penilaian->nilai_absen) }}</td>
                    </tr>
                </table>
                <div class="bobot-absen">{{ __('Bobot Absen :level = :bobot% (b)', ['level' => __($levelForm[$penilaian->form] ?? ''), 'bobot' => $form['bobot_absen']]) }}</div>
            </td>
            <td style="width: 10.5%;"></td>
            <td style="width: 16%; padding-top: .5mm;">
                <table class="range">
                    <tr><th colspan="2">{{ __('RANGE NILAI AKHIR') }}</th></tr>
                    <tr><th>{{ __('ANGKA') }}</th><th>{{ __('HURUF') }}</th></tr>
                    @foreach (['91 ~ 100' => 'A', '80 ~ 90' => 'B', '60 ~ 79' => 'C', '39 ~ 59' => 'D', '16 ~ 38' => 'E'] as $range => $huruf)
                        <tr @class(['on' => $penilaian->huruf === $huruf])><td>{{ $range }}</td><td>{{ $huruf }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    {{-- ===== II - VI. PERFORMANCE (satu tabel supaya kolom nilai A - E sejajar dengan KOLOM NILAI) ===== --}}
    <table class="nilai" style="width: 92%; margin-top: 2mm;">
        <colgroup>
            <col style="width: 2.4%;">
            <col style="width: 64.6%;">
            @foreach (FormPenilaian::NILAI_HURUF as $huruf => $nilai)
                <col style="width: 5.3%;">
            @endforeach
            <col style="width: 6.5%;">
        </colgroup>
        <tr>
            <td class="nb" colspan="2"></td>
            <td class="head" colspan="{{ count(FormPenilaian::NILAI_HURUF) }}">{{ __('KOLOM NILAI') }}</td>
            <td class="nb"></td>
        </tr>
        <tr>
            <td class="nb" colspan="2"></td>
            @foreach (FormPenilaian::NILAI_HURUF as $huruf => $nilai)
                <td class="head">{{ $huruf }}</td>
            @endforeach
            <td class="nb"></td>
        </tr>
        @foreach ($form['butir'] as $kategori => $butirs)
            <tr>
                <td class="sec" colspan="2">{{ Str::upper(__(FormPenilaian::KATEGORI[$kategori])) }}</td>
                @foreach (FormPenilaian::NILAI_HURUF as $huruf => $nilai)
                    @if ($loop->parent->first)
                        <td class="head">{{ $nilai }}</td>
                    @else
                        <td class="nb"></td>
                    @endif
                @endforeach
                <td class="nb"></td>
            </tr>
            @foreach ($butirs as $index => $butir)
                <tr>
                    <td class="no">{{ $index + 1 }}</td>
                    <td>{{ __($butir) }}</td>
                    @foreach (FormPenilaian::NILAI_HURUF as $huruf => $nilai)
                        <td class="skor">{{ ($skor[$kategori][$index] ?? null) === $huruf ? '✓' : '' }}</td>
                    @endforeach
                    <td class="nb"></td>
                </tr>
            @endforeach
            <tr>
                <td class="nb"></td>
                <td class="rumus" style="padding-left: 52%;">{{ __('Nilai Max = :maks (a)', ['maks' => count($butirs) * 5]) }}</td>
                <td class="total" colspan="{{ count(FormPenilaian::NILAI_HURUF) + 1 }}">{{ $performance['kategori'][$kategori]['total'] }}</td>
            </tr>
            <tr>
                <td class="nb"></td>
                <td class="rumus" style="padding-left: 52%;">{{ __('Bobot = :bobot % (b)', ['bobot' => $form['bobot_kategori']]) }}</td>
                <td class="rumus" colspan="{{ count(FormPenilaian::NILAI_HURUF) - 1 }}" style="text-align: right; padding-right: 3px;">{{ __('Nilai Akhir = (Total nilai/a) x ( b %x 100)') }}</td>
                <td class="sama" colspan="2">= {{ $angka($performance['kategori'][$kategori]['nilai']) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="gap"></div>

    {{-- ===== BAGIAN BAWAH ===== --}}
    <table class="lay">
        <tr>
            <td style="width: 34%;">
                <table class="hasil">
                    @foreach (PenilaianTahunan::HASIL as $option)
                        <tr>
                            @if ($loop->first)
                                <td rowspan="{{ count(PenilaianTahunan::HASIL) }}" class="c" style="width: 37%; vertical-align: top;">{{ __('Hasil Penilaian') }}</td>
                            @endif
                            <td class="o">{{ $bulat($penilaian->hasil === $option) }}</td>
                            <td class="opsi">{{ __($option) }}</td>
                        </tr>
                    @endforeach
                </table>

                @if ($penilaian->form === 'operator')
                    <table class="status" style="margin-top: 1.5mm;">
                        <tr>
                            <td rowspan="3" class="c" style="width: 37%; vertical-align: top;">{{ __('Status Karyawan*') }}</td>
                            <td class="o">{{ $bulat($statusTerpilih === 'KKWT 1') }}</td>
                            <td>{{ __('KKWT 1*') }}</td>
                            <td class="o">{{ $bulat($statusTerpilih === 'Tetap') }}</td>
                            <td>{{ __('Tetap*') }}</td>
                        </tr>
                        <tr><td class="o">{{ $bulat($statusTerpilih === 'KKWT 2') }}</td><td colspan="3">{{ __('KKWT 2*') }}</td></tr>
                        <tr><td class="o">{{ $bulat($statusTerpilih === 'KKWT 2+') }}</td><td colspan="3">{{ __('KKWT 2+*') }}</td></tr>
                    </table>
                @endif

                {{-- 4 kotak tetap, diisi dari kanan: Asst. Leader, Leader, Section Chief, Manager / GM (lihat kotakPenilai). --}}
                <table class="ttd" style="margin-top: 7mm;">
                    <tr><th colspan="4">{{ __('Penilai') }}</th></tr>
                    <tr>
                        @foreach ($kotakPenilai as $tahap)
                            <td class="isi">
                                @if (($tahap['log'] ?? null)?->kode_verifikasi)
                                    <div class="qr">{!! QrCode::svg(route('verifikasi.penilaian', $tahap['log']->kode_verifikasi)) !!}</div>
                                    <div class="qr-tgl">{{ $tahap['log']->created_at->translatedFormat('d M Y H:i') }}</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($kotakPenilai as $tahap)
                            @php($namaPenilai = ($tahap['log'] ?? null)?->user?->name)
                            <td class="nama" title="{{ $namaPenilai }}">{{ $namaPenilai }}</td>
                        @endforeach
                    </tr>
                </table>
            </td>
            <td style="width: 9%;"></td>
            <td>
                <table class="nilai-akhir" style="width: 77%; margin-left: 11%;">
                    <tr><th colspan="3">{{ __('NILAI AKHIR') }}</th></tr>
                    <tr>
                        <td class="catatan" rowspan="5"><u>{{ __('Catatan Atasan') }}</u>{{ $penilaian->catatan ?: '' }}</td>
                        <td class="lbl" colspan="2">{{ __('NILAI ABSEN') }}</td>
                    </tr>
                    <tr><td class="lbl">{{ __('ANGKA') }}</td><td class="lbl">{{ __('HURUF') }}</td></tr>
                    <tr><td class="val" colspan="2">{{ $angka($penilaian->nilai_absen) }}</td></tr>
                    <tr><td class="lbl" colspan="2">{{ __('PERFORMANCE') }}</td></tr>
                    <tr><td class="val" colspan="2">{{ $angka($penilaian->nilai_performance) }}</td></tr>
                    <tr>
                        <td class="total">{{ __('TOTAL NILAI AKHIR **') }}</td>
                        <td class="val">{{ $angka($penilaian->nilai_akhir) }}</td>
                        <td class="val">{{ $penilaian->huruf }}</td>
                    </tr>
                </table>
                <div class="rekom" style="margin-top: 3mm;">{{ __('Rekomendasi Akhir HRD :') }}</div>
            </td>
        </tr>
    </table>
</div>

    <div class="foot">
        <span>
            @if ($penilaian->status === PenilaianTahunan::STATUS_DISETUJUI)
                {{ __('Disetujui secara elektronik melalui HR System.') }}
            @else
                {{ __('Masih dalam proses approval (menunggu :tahap) · ditandatangani elektronik melalui HR System.', ['tahap' => \App\Modules\Hrms\Support\JenjangPenilaian::label($penilaian->posisi_level)]) }}
            @endif
        </span>
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
