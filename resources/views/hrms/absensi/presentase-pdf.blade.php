@php
    $rate = fn (?float $value) => $value === null ? '-' : number_format($value, 1, ',', '.').'%';
    $angka = fn (int $value) => number_format($value, 0, ',', '.');
    $jumlahPerGrup = array_count_values(array_column($kelompok, 'grup'));
    $periode = $mulai->translatedFormat($mulai->isSameMonth($sampai) ? 'd' : ($mulai->isSameYear($sampai) ? 'd F' : 'd F Y')).' - '.$sampai->translatedFormat('d F Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Presentase Absensi {{ $mulai->format('d-m-Y') }} sd {{ $sampai->format('d-m-Y') }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; line-height: 1.25; }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; gap: 8px; padding: 10px; background: #1e3a8a; }
        .toolbar button { padding: 8px 18px; border: 0; border-radius: 999px; font: 600 13px Arial, sans-serif; cursor: pointer; background: #fff; color: #1e3a8a; }
        .toolbar button.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.6); }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 10mm 12mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); }

        h1 { margin: 0 0 2px; font-size: 12pt; text-align: center; }
        .sub { margin: 0 0 10px; text-align: center; font-size: 8pt; color: #333; }

        table.pst { width: 100%; border-collapse: collapse; }
        table.pst th, table.pst td { border: 1px solid #000; padding: 1.5px 5px; white-space: nowrap; }
        table.pst thead { display: table-header-group; }
        table.pst thead th { background: #dce6f1; text-align: center; }
        table.pst thead tr.sum th { background: #bfbfbf; font-size: 8pt; text-align: right; }
        table.pst thead tr.sum th:first-child { text-align: left; }
        table.pst td.n, table.pst th.n { text-align: right; }
        table.pst td.c { text-align: center; }
        table.pst td.day { font-weight: 700; text-align: center; }
        table.pst td.grp { font-weight: 700; }
        table.pst td.rate { background: #f2f2f2; font-weight: 700; }
        table.pst td.bad { color: #b91c1c; }
        table.pst tbody.day-group { break-inside: avoid; border-top: 2px solid #000; }
        table.pst .empty { padding: 10px; text-align: center; font-style: italic; }

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
    <h1>PRESENTASE ABSENSI {{ Str::upper($periode) }}</h1>
    <p class="sub">{{ count($days) }} hari kerja terakhir (Senin - Jumat) · rate = (Cuti + S+I) / Total · target {{ $target }}%</p>

    <table class="pst">
        <colgroup>
            <col style="width: 12%;"><col style="width: 8%;"><col style="width: 16%;"><col style="width: 14%;">
            <col><col><col><col><col>
        </colgroup>
        <thead>
            <tr class="sum">
                <th colspan="4">TOTAL {{ count($days) }} HARI KERJA</th>
                <th class="n">{{ $angka($total['total']) }}</th>
                <th class="n">{{ $angka($total['hadir']) }}</th>
                <th class="n">{{ $angka($total['cuti']) }}</th>
                <th class="n">{{ $angka($total['si']) }}</th>
                <th @class(['n', 'bad' => $total['rate'] > $target])>{{ $rate($total['rate']) }}</th>
            </tr>
            <tr>
                <th>Day</th>
                <th>Date</th>
                <th>Grup</th>
                <th>Status</th>
                <th>Total</th>
                <th>Hadir</th>
                <th>Cuti</th>
                <th>S+I</th>
                <th>Rate</th>
            </tr>
        </thead>
        @forelse ($days as $day)
            <tbody class="day-group">
                @foreach ($kelompok as $kode => $info)
                    @php
                        $row = $day['rows'][$kode];
                        $grupBaru = $loop->first || $kelompok[array_keys($kelompok)[$loop->index - 1]]['grup'] !== $info['grup'];
                    @endphp
                    <tr>
                        @if ($loop->first)
                            <td rowspan="{{ count($kelompok) }}" class="day">{{ $day['tanggal']->translatedFormat('l') }}</td>
                            <td rowspan="{{ count($kelompok) }}" class="day">{{ $day['tanggal']->format('d/m') }}</td>
                        @endif
                        @if ($grupBaru)
                            <td rowspan="{{ $jumlahPerGrup[$info['grup']] }}" class="grp">{{ $info['grup'] }}</td>
                        @endif
                        <td>{{ $info['status'] }}</td>
                        <td class="n">{{ $row['total'] }}</td>
                        <td class="n">{{ $row['hadir'] }}</td>
                        <td class="n">{{ $row['cuti'] }}</td>
                        <td class="n">{{ $row['si'] }}</td>
                        <td @class(['n', 'rate', 'bad' => $row['rate'] > $target])>{{ $rate($row['rate']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        @empty
            <tbody><tr><td colspan="9" class="empty">Belum ada hari kerja di periode ini.</td></tr></tbody>
        @endforelse
    </table>
</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
    });
</script>
</body>
</html>
