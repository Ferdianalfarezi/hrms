@php
    $persen = fn (?float $value) => $value === null ? '-' : number_format($value, 1, ',', '.').'%';
    $angka = fn (int $value) => number_format($value, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Japan Resume') }} {{ $bulan->format('Y-m') }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; color: #000; font-family: Arial, Helvetica, "Noto Sans JP", sans-serif; font-size: 8.5pt; line-height: 1.25; }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; gap: 8px; padding: 10px; background: #1e3a8a; }
        .toolbar button { padding: 8px 18px; border: 0; border-radius: 999px; font: 600 13px Arial, sans-serif; cursor: pointer; background: #fff; color: #1e3a8a; }
        .toolbar button.ghost { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.6); }
        .sheet { width: 210mm; min-height: 297mm; margin: 12px auto; padding: 12mm 14mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.15); }

        h1 { margin: 0 0 2px; font-size: 12pt; text-align: center; }
        .sub { margin: 0 0 10px; text-align: center; font-size: 8pt; color: #333; }

        table.jr { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        table.jr th, table.jr td { border: 1px solid #000; padding: 2.5px 6px; white-space: nowrap; }
        table.jr thead { display: table-header-group; }
        table.jr thead th { background: #dce6f1; text-align: center; }
        table.jr td.c { text-align: center; }
        table.jr td.n { text-align: right; }
        table.jr td.rate { background: #f2f2f2; font-weight: 700; text-align: right; }
        table.jr tr.total td { background: #fff2cc; font-weight: 700; font-size: 9pt; }
        table.jr tr.total td.rate { background: #ffe699; }
        table.jr .pct { margin-left: 4px; font-size: 7.5pt; font-weight: 700; color: #7c5a00; }
        table.jr tbody tr { break-inside: avoid; }
        table.jr .empty { padding: 10px; text-align: center; font-style: italic; }
        .note { margin-top: 8px; font-size: 7pt; color: #444; }

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
    <h1>{{ __('JAPAN RESUME') }} · {{ Str::upper($bulan->translatedFormat('F Y')) }}</h1>
    <p class="sub">{{ __('Total = MP Shift 1 + Shift 2 · rate = (Cuti + S+I) / Total') }}</p>

    <table class="jr">
        <thead>
            <tr>
                <th style="width: 16%;">{{ __('Hari') }}</th>
                <th style="width: 10%;">{{ __('Tanggal') }}</th>
                <th style="width: 10%;">{{ __('Shift') }}</th>
                <th style="width: 13%;">{{ __('Total') }}</th>
                <th style="width: 13%;">{{ __('Hadir') }}</th>
                <th style="width: 13%;">{{ __('Cuti') }}</th>
                <th style="width: 13%;">S+I</th>
                <th style="width: 12%;">{{ __('Rate') }}</th>
            </tr>
        </thead>
        <tbody>
            {{-- Baris total di atas seperti sheet Excel; persentase Cuti dan S+I di kanan angkanya. --}}
            <tr class="total">
                <td colspan="3">{{ $bulan->translatedFormat('M') }} {{ __('Total') }}</td>
                <td class="n">{{ $angka($total['total']) }}</td>
                <td class="n">{{ $angka($total['hadir']) }}</td>
                <td class="n">{{ $angka($total['cuti']) }}<span class="pct">{{ $persen($total['persen_cuti']) }}</span></td>
                <td class="n">{{ $angka($total['sakit_izin']) }}<span class="pct">{{ $persen($total['persen_sakit_izin']) }}</span></td>
                <td class="rate">{{ $persen($total['rate']) }}</td>
            </tr>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['tanggal']->translatedFormat('l') }}</td>
                    <td class="c">{{ $row['tanggal']->format('n/j') }}{{ $row['otomatis'] ? '*' : '' }}</td>
                    <td class="c">{{ $row['shift'] }}</td>
                    <td class="n">{{ $angka($row['total']) }}</td>
                    <td class="n">{{ $angka($row['hadir']) }}</td>
                    <td class="n">{{ $angka($row['cuti']) }}</td>
                    <td class="n">{{ $angka($row['sakit_izin']) }}</td>
                    <td class="rate">{{ $persen($row['rate']) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">{{ __('Belum ada data kehadiran di bulan ini.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (collect($rows)->contains('otomatis', true))
        <p class="note">* {{ __('Dihitung otomatis dari data absensi') }}</p>
    @endif
</div>

<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
    });
</script>
</body>
</html>
