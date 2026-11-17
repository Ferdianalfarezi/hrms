@php
    $persen = fn (?float $value, int $decimals) => $value === null ? '-' : number_format($value, $decimals, ',', '.');
    $angka = fn (int $value) => $value ?: '';
    // Urut kronologis seperti format kertas HRD: Shift 2 (malam sebelumnya) di atas, lalu Shift 1.
    $urutSections = collect($sections)->sortBy(fn (array $section) => $section['tanggal']->toDateString())->values();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Resume Absensi') }} {{ $tanggal->format('d-m-Y') }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 8pt; line-height: 1.25; }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; gap: 8px; padding: 10px; background: #1e3a8a; }
        .toolbar button { padding: 8px 18px; border: 0; border-radius: 999px; font: 600 13px Arial, sans-serif; cursor: pointer; background: #fff; color: #1e3a8a; }
        .toolbar button.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.6); }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 12mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); }

        h1 { margin: 0 0 14px; font-size: 12pt; text-align: center; letter-spacing: .02em; }
        .sec-title { margin: 12px 0 3px; font-weight: 700; font-size: 8pt; }
        table.rsm { width: 100%; border-collapse: collapse; }
        table.rsm th, table.rsm td { border: 1px solid #000; padding: 2px 4px; text-align: center; vertical-align: middle; }
        table.rsm th { font-weight: 700; font-size: 7.5pt; }
        table.rsm thead tr:first-child th { border-top-width: 2px; }
        table.rsm td.l { text-align: left; white-space: nowrap; }
        table.rsm .tot { background: #bfbfbf; font-weight: 700; }
        table.rsm tfoot td { font-weight: 700; border-top-width: 2px; border-bottom-width: 2px; }
        table.rsm tfoot td.tot { background: #a6a6a6; }
        table.rsm td.bad { color: #b91c1c; }
        .empty { padding: 8px; font-style: italic; }

        .ttd { width: 62%; margin: 22px 0 0 auto; }
        .ttd .tempat { margin-bottom: 4px; font-size: 8pt; }
        table.ttd-box { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.ttd-box td { border: 1px solid #000; text-align: center; padding: 3px; font-size: 7.5pt; }
        table.ttd-box .space td { height: 60px; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">{{ __('Unduh / Simpan PDF') }}</button>
    <button type="button" class="ghost" onclick="window.close()">{{ __('Tutup') }}</button>
</div>

<div class="sheet">
    <h1>{{ __('RESUME ABSENSI HARIAN PT.STEP') }}</h1>

    @foreach ($urutSections as $section)
        @php
            $suffix = $section['judul'] === 'Shift 2' ? ' S.2' : '';
        @endphp
        <div class="sec-title">{{ $section['tanggal']->translatedFormat('l, d F Y') }} ({{ __($section['judul']) }})</div>
        <table class="rsm">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 6%;">{{ __('PLANT') }}</th>
                    <th rowspan="2" style="width: 25%;">{{ __('STATUS') }}</th>
                    <th rowspan="2" style="width: 9%;">{{ __('JUMLAH KARY') }}</th>
                    <th colspan="4">{{ __('KATEGORI ABSEN') }}</th>
                    <th rowspan="2" class="tot" style="width: 7%;">{{ __('TOTAL ABSEN') }}</th>
                    <th rowspan="2" class="tot" style="width: 8%;">{{ __('TOTAL %') }}<br>{{ __('DGN CUTI') }}</th>
                    <th rowspan="2" class="tot" style="width: 8%;">{{ __('TOTAL %') }}<br>{{ __('TANPA CUTI') }}</th>
                    <th rowspan="2" style="width: 8%;">{{ __('JML KARY. KERJA') }}</th>
                    <th rowspan="2" style="width: 5%;">SKD</th>
                    <th rowspan="2" style="width: 5%;">CT</th>
                </tr>
                <tr>
                    <th>{{ __('CUTI') }}</th>
                    <th>{{ __('SAKIT') }}</th>
                    <th>{{ __('IJIN') }}</th>
                    <th>{{ __('ALPA') }}</th>
                </tr>
            </thead>
            <tbody>
                @if ($section['grups'])
                    @foreach ($section['rows'] as $row)
                        <tr>
                            <td>{{ $row['no'] }}</td>
                            <td class="l">{{ $row['label'] }}{{ $suffix }}</td>
                            <td>{{ $row['jumlah'] }}</td>
                            <td>{{ $row['cuti'] }}</td>
                            <td>{{ $row['sakit'] }}</td>
                            <td>{{ $row['ijin'] }}</td>
                            <td>{{ $row['alpa'] }}</td>
                            <td class="tot">{{ $row['total_absen'] }}</td>
                            <td @class(['tot', 'bad' => $row['persen'] > $target])>{{ $persen($row['persen'], 2) }}</td>
                            <td @class(['tot', 'bad' => $row['persen_tanpa_cuti'] > $target])>{{ $persen($row['persen_tanpa_cuti'], 2) }}</td>
                            <td>{{ $row['kerja'] }}</td>
                            <td>{{ $angka($row['skd']) }}</td>
                            <td>{{ $angka($row['ct']) }}</td>
                        </tr>
                    @endforeach
                @else
                    <tr><td colspan="13" class="empty">{{ __('Grup shift belum diketahui — atur jadwal rotasi di menu Detail.') }}</td></tr>
                @endif
            </tbody>
            @if ($section['grups'])
                <tfoot>
                    <tr>
                        <td colspan="2">{{ __('TOTAL') }}</td>
                        <td>{{ $section['total']['jumlah'] }}</td>
                        <td>{{ $section['total']['cuti'] }}</td>
                        <td>{{ $section['total']['sakit'] }}</td>
                        <td>{{ $section['total']['ijin'] }}</td>
                        <td>{{ $section['total']['alpa'] }}</td>
                        <td class="tot">{{ $section['total']['total_absen'] }}</td>
                        <td @class(['tot', 'bad' => $section['total']['persen'] > $target])>{{ $persen($section['total']['persen'], 1) }}</td>
                        <td @class(['tot', 'bad' => $section['total']['persen_tanpa_cuti'] > $target])>{{ $persen($section['total']['persen_tanpa_cuti'], 1) }}</td>
                        <td>{{ $section['total']['kerja'] }}</td>
                        <td>{{ $angka($section['total']['skd']) }}</td>
                        <td>{{ $angka($section['total']['ct']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endforeach

    <div class="ttd">
        <div class="tempat">Cikarang, {{ $tanggal->translatedFormat('d F Y') }}</div>
        <table class="ttd-box">
            <tr>
                @foreach ($penandatangan as $ttd)
                    <td>{{ __($ttd['jabatan']) }}</td>
                @endforeach
            </tr>
            <tr class="space">
                @foreach ($penandatangan as $ttd)
                    <td></td>
                @endforeach
            </tr>
            <tr>
                @foreach ($penandatangan as $ttd)
                    <td>{{ $ttd['nama'] }}</td>
                @endforeach
            </tr>
        </table>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
    });
</script>
</body>
</html>
