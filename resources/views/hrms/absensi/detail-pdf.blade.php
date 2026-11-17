@php
    $statusMp = fn (string $status) => str_starts_with($status, 'PKWT') ? 'Kontrak' : $status;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Absensi {{ $tanggal->format('d-m-Y') }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 9mm; }
        * { box-sizing: border-box; }
        :root { --navy: #1e3a8a; --muted: #4b5563; --line-strong: #9ca3af; }
        body { margin: 0; background: #e5e7eb; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; line-height: 1.25; }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; gap: 8px; padding: 10px; background: #1e3a8a; }
        .toolbar button { padding: 8px 18px; border: 0; border-radius: 999px; font: 600 13px Arial, sans-serif; cursor: pointer; background: #fff; color: #1e3a8a; }
        .toolbar button.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.6); }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 10mm 9mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); }

        h1 { margin: 0 0 2px; font-size: 12pt; text-align: center; }
        .sub { margin: 0 0 10px; text-align: center; font-size: 8pt; color: #333; }
        .sec { margin-top: 12px; break-inside: auto; }
        .sec-title { margin-bottom: 3px; }
        .sec-title b { font-size: 9pt; }
        .sec-title span { display: block; font-size: 7pt; color: #333; }

        table.det { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.det th, table.det td { border: 1px solid #000; padding: 2px 3px; text-align: center; vertical-align: middle; }
        table.det thead th { background: #dce6f1; font-size: 7pt; }
        table.det tr { break-inside: avoid; }
        table.det td.l { text-align: left; }
        table.det td.kat { font-weight: 700; width: 30px; }
        table.det tfoot td { background: #f2f2f2; font-weight: 700; }
        table.det .empty { font-style: italic; padding: 6px; }

        .charts { display: grid; grid-template-columns: 1fr 1fr; gap: 5mm; margin-top: 14px; break-inside: avoid; }
        .chart { break-inside: avoid; }
        .chart h3 { margin: 0 0 4px; font-size: 8pt; }
        .chart svg { width: 100%; height: auto; display: block; }
        .chart .grid line { stroke: #d1d5db; }
        .chart text { font-size: 11px; fill: var(--muted); font-family: Arial, sans-serif; }
        .chart .legend { display: flex; flex-wrap: wrap; gap: 2px 8px; font-size: 6pt; margin-top: 3px; }
        .chart .legend span::before { content: ""; display: inline-block; width: 10px; height: 3px; border-radius: 2px; vertical-align: middle; margin-right: 5px; background: var(--c); }
        .chart .legend span.bar::before { width: 7px; height: 7px; }
        table.chart-data { border-collapse: collapse; margin-top: 6px; width: 100%; table-layout: fixed; }
        table.chart-data th, table.chart-data td { border: 1px solid #9ca3af; padding: 1px 0; text-align: center; font-size: 4.8pt; font-weight: 400; }
        table.chart-data th:first-child { width: 54px; text-align: left; padding-left: 2px; white-space: nowrap; }
        table.chart-data .sel { background: #eef4ff; font-weight: 700; }

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
    <button type="button" onclick="window.print()">Unduh / Simpan PDF</button>
    <button type="button" class="ghost" onclick="window.close()">Tutup</button>
</div>

<div class="sheet">
    <h1>LAPORAN ABSENSI HARIAN</h1>
    <p class="sub">
        {{ $tanggal->translatedFormat('l, d F Y') }}
        · {{ $grupPagi ? "Rotasi: grup {$grupPagi} pagi, grup ".\App\Modules\Hrms\Models\ShiftRotasi::lawan($grupPagi).' malam, NS 07.00 - 16.00' : 'Jadwal rotasi grup A/B belum diatur' }}
        @unless ($hitungCuti) · cuti tidak dihitung @endunless
    </p>

    @foreach ($sections as $section)
        <div class="sec">
            <div class="sec-title">
                <b>{{ $section['tanggal']->translatedFormat('l') }} : {{ $section['tanggal']->translatedFormat('d F Y') }} ({{ $section['judul'] }})</b>
                <span>
                    {{ $section['keterangan'] }} · grup {{ $section['grups'] ? implode(', ', $section['grups']) : '-' }}
                    · MP {{ $section['headcount'] }} · Tidak hadir {{ $section['rows']->count() }}
                    @if ($section['persen'] !== null) · {{ number_format($section['persen'], 1, ',', '.') }}% @endif
                </span>
            </div>
            <table class="det">
                <colgroup>
                    <col style="width: 4%;"><col style="width: 6%;"><col style="width: 19%;"><col style="width: 15%;"><col style="width: 5%;">
                    @foreach ($kategoris as $kategori)
                        <col style="width: 4%;">
                    @endforeach
                    <col><col style="width: 7%;"><col style="width: 7%;">
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="2">No</th>
                        <th rowspan="2">NIK</th>
                        <th rowspan="2">Nama</th>
                        <th rowspan="2">Group</th>
                        <th rowspan="2">Shift</th>
                        <th colspan="{{ count($kategoris) }}">Kategori Absensi</th>
                        <th rowspan="2">Keterangan</th>
                        <th rowspan="2">Durasi Libur</th>
                        <th rowspan="2">Status MP</th>
                    </tr>
                    <tr>
                        @foreach ($kategoris as $kategori)
                            <th>{{ $kategori }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $row)
                        @php($karyawan = $row['karyawan'])
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $karyawan->nik }}</td>
                            <td class="l">{{ $karyawan->nama }}</td>
                            <td class="l">{{ trim(($karyawan->department?->name ?? '').' '.($karyawan->jabatan ?? '')) ?: '-' }}</td>
                            <td>{{ $karyawan->group }}</td>
                            @foreach ($kategoris as $kategori)
                                <td class="kat">{{ $row['kategori'] === $kategori ? 1 : '' }}</td>
                            @endforeach
                            <td class="l">{{ $row['keterangan'] ?: ($kodes[$row['kode']]['label'] ?? $row['kode']) }}</td>
                            <td>{{ $row['durasi'] }} Hari</td>
                            <td>{{ $statusMp($karyawan->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 8 + count($kategoris) }}" class="empty">
                            {{ $section['grups'] ? 'Semua karyawan hadir.' : 'Grup shift belum diketahui.' }}
                        </td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">Total</td>
                        @foreach ($kategoris as $kategori)
                            <td>{{ $section['totals'][$kategori] }}</td>
                        @endforeach
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach

    <div class="charts">
        @foreach ($sections as $section)
            <div class="chart">
                @include('hrms.absensi.partials.grafik-detail', ['section' => $section, 'hitungCuti' => $hitungCuti])
            </div>
        @endforeach
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
    });
</script>
</body>
</html>
