@extends('layouts.app')

@section('title', __('Japan Resume'))

@section('styles')
    .jr-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .jr-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .jr-toolbar input[type=month] { width: auto; border-radius: 999px; padding: 9px 14px; }
    .month-nav { display: inline-flex; border: 1px solid var(--line-strong); border-radius: 999px; overflow: hidden; background: var(--glass-strong); }
    .month-nav a { padding: 9px 14px; font-size: 13px; font-weight: 600; color: var(--text); }
    .month-nav a + a { border-left: 1px solid var(--line-strong); }
    .month-nav a:hover { background: #fff; }

    .jr-card { padding: 0; overflow: hidden; }
    table.jr { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; font-variant-numeric: tabular-nums; }
    table.jr th, table.jr td { border: 1px solid var(--line-strong); padding: 7px 12px; text-align: center; white-space: nowrap; }
    table.jr thead th { background: #f5f8fe; color: var(--text); font-size: 11px; letter-spacing: .03em; }
    table.jr td.l { text-align: left; }
    table.jr td.num { text-align: right; }
    table.jr td.rate { background: #eef1f6; font-weight: 700; }
    table.jr tr.jr-total td { background: #fff7d6; font-weight: 700; font-size: 14px; }
    table.jr tr.jr-total td.rate { background: #fdeba6; }
    table.jr .jr-pct { margin-left: 6px; padding: 1px 7px; border-radius: 999px; background: #fdeba6; color: #7c5a00; font-size: 11px; font-weight: 700; }
    table.jr tbody tr:hover td { background: #f7faff; }
    table.jr tbody tr:hover td.rate { background: #e6eaf2; }
    table.jr .empty { color: var(--muted); padding: 28px; }
    .auto-badge { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px; background: var(--soft); color: var(--navy); font-size: 10px; font-weight: 700; vertical-align: 1px; }
    .jr-note { margin: 12px 4px 0; font-size: 12px; color: var(--muted); }
    .edit-badge { display: inline-block; margin-left: 6px; padding: 1px 7px; border-radius: 999px; background: #fff4d6; color: #8a5a00; font-size: 10px; font-weight: 700; vertical-align: 1px; }
    table.jr td.act { width: 1%; padding: 4px 8px; }
    .jr-act { display: inline-flex; gap: 4px; }
    .jr-act form { margin: 0; }
    .jr-act button { display: inline-grid; place-items: center; width: 28px; height: 28px; padding: 0; border: 1px solid var(--line-strong); border-radius: 8px; background: transparent; color: var(--muted); cursor: pointer; }
    .jr-act button:hover { background: #fff; color: var(--primary); border-color: var(--primary); }
    .jr-act svg { width: 14px; height: 14px; }
    /* Baris yang baru disimpan: disorot lalu memudar. */
    @keyframes jr-row-flash { 0%, 60% { background: #bbf7d0; } 100% { background: transparent; } }
    table.jr tr.row-new > td { animation: jr-row-flash 4s ease-out; }

    .jr-form { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .jr-form .wide { grid-column: 1 / -1; }
    .jr-form input[type=number] { text-align: right; }
    .jr-preview { grid-column: 1 / -1; display: flex; justify-content: space-between; padding: 10px 14px; border-radius: var(--radius-sm); background: var(--soft-2); font-size: 13px; }
    .jr-preview b { font-variant-numeric: tabular-nums; }
    .jr-form .hint { margin-top: 4px; font-size: 12px; color: var(--muted); }

    @media print {
        .app-nav, .jr-toolbar, .success, .alert, .jr-note, .no-print { display: none !important; }
        .shell { padding: 0; }
        .card { box-shadow: none; border: 0; backdrop-filter: none; }
        body { background: #fff; }
    }
@endsection

@section('content')
@use('App\Modules\Hrms\Models\JapanResume')
@php
    $persen = fn (?float $value) => $value === null ? '-' : number_format($value, 1, ',', '.').'%';
    $angka = fn (int $value) => number_format($value, 0, ',', '.');
@endphp

<div class="jr-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ __('Japan Resume') }}</h1>
        <p class="muted" style="margin: 2px 0 0;">{{ __('Rekap kehadiran harian :bulan · Total = MP Shift 1 + Shift 2', ['bulan' => $bulan->translatedFormat('F Y')]) }}</p>
    </div>
    <form method="GET" class="jr-toolbar">
        <span class="month-nav">
            <a href="{{ route('hrms.absensi.japan-resume', ['bulan' => $bulan->copy()->subMonthNoOverflow()->format('Y-m')]) }}" title="{{ __('Bulan sebelumnya') }}">&lsaquo;</a>
            <a href="{{ route('hrms.absensi.japan-resume') }}">{{ __('Bulan ini') }}</a>
            <a href="{{ route('hrms.absensi.japan-resume', ['bulan' => $bulan->copy()->addMonthNoOverflow()->format('Y-m')]) }}" title="{{ __('Bulan berikutnya') }}">&rsaquo;</a>
        </span>
        <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" onchange="this.form.requestSubmit()" aria-label="{{ __('Bulan') }}">
        <button type="button" class="btn btn-sm" data-jr-add>{{ __('+ Tambah tanggal') }}</button>
        <a href="{{ route('hrms.absensi.japan-resume.pdf', ['bulan' => $bulan->format('Y-m')]) }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener" data-turbo="false">{{ __('Unduh PDF') }}</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">{{ __('Cetak') }}</button>
    </form>
</div>

<section class="card jr-card">
    <div class="table-wrap">
        <table class="jr">
            <thead>
                <tr>
                    <th>{{ __('Hari') }}</th>
                    <th colspan="2">{{ __('Tanggal') }}</th>
                    <th>{{ __('Total') }}</th>
                    <th>{{ __('Hadir') }}</th>
                    <th>{{ __('Cuti') }}</th>
                    <th>S+I</th>
                    <th>{{ __('Rate') }}</th>
                    <th class="no-print"></th>
                </tr>
            </thead>
            <tbody>
                {{-- Baris total di atas seperti sheet Excel; persentase Cuti dan S+I terhadap total MP di kanan angkanya. --}}
                <tr class="jr-total">
                    <td colspan="3" class="l">{{ $bulan->translatedFormat('M') }} {{ __('Total') }}</td>
                    <td class="num">{{ $angka($total['total']) }}</td>
                    <td class="num">{{ $angka($total['hadir']) }}</td>
                    <td class="num">{{ $angka($total['cuti']) }} <span class="jr-pct">{{ $persen($total['persen_cuti']) }}</span></td>
                    <td class="num">{{ $angka($total['sakit_izin']) }} <span class="jr-pct">{{ $persen($total['persen_sakit_izin']) }}</span></td>
                    <td class="rate">{{ $persen($total['rate']) }}</td>
                    <td class="no-print"></td>
                </tr>
                @forelse ($rows as $row)
                    <tr @class(['row-new' => session('highlight') === $row['tanggal']->toDateString()])>
                        <td class="l">{{ $row['tanggal']->translatedFormat('l') }}</td>
                        <td>
                            {{ $row['tanggal']->format('n/j') }}
                            @if ($row['otomatis'])
                                <span class="auto-badge" title="{{ __('Dihitung otomatis dari data absensi') }}">{{ __('Otomatis') }}</span>
                            @elseif ($row['editor'])
                                <span class="edit-badge" title="{{ __('Disesuaikan oleh :nama', ['nama' => $row['editor']]) }}">{{ __('Diedit') }}</span>
                            @endif
                        </td>
                        <td>{{ $row['shift'] }}</td>
                        <td class="num">{{ $angka($row['total']) }}</td>
                        <td class="num">{{ $angka($row['hadir']) }}</td>
                        <td class="num">{{ $angka($row['cuti']) }}</td>
                        <td class="num">{{ $angka($row['sakit_izin']) }}</td>
                        <td class="rate">{{ $persen($row['rate']) }}</td>
                        <td class="act no-print">
                            <span class="jr-act">
                                <button type="button" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }} {{ $row['tanggal']->format('n/j') }}"
                                    data-jr-edit="{{ json_encode(['tanggal' => $row['tanggal']->toDateString(), 'label' => $row['tanggal']->translatedFormat('l, d F Y'), 'shift' => $row['shift'], 'total' => $row['total'], 'cuti' => $row['cuti'], 'sakit_izin' => $row['sakit_izin']]) }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                </button>
                                {{-- Angka hari otomatis yang disesuaikan bisa dikembalikan ke hitungan dari absensi. --}}
                                @if ($row['sumber'] === JapanResume::SUMBER_MANUAL)
                                    <form method="POST" action="{{ route('hrms.absensi.japan-resume.reset', $row['id']) }}" onsubmit="return confirm(__t('Kembalikan tanggal ini ke hitungan otomatis dari absensi?'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="{{ __('Reset ke otomatis') }}" aria-label="{{ __('Reset ke otomatis') }}">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty">{{ __('Belum ada data kehadiran di bulan ini.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<p class="jr-note">
    @if ($dataSampai)
        {{ __('Data sampai :tanggal dari file HRD (2026 summary). Hari setelahnya dihitung otomatis dari absensi: Cuti = CT/CK/CH/CB/IMP, S+I = Sakit + Izin + Alpa, WFH dihitung hadir.', ['tanggal' => $dataSampai->translatedFormat('d F Y')]) }}
    @else
        {{ __('Dihitung otomatis dari absensi: Cuti = CT/CK/CH/CB/IMP, S+I = Sakit + Izin + Alpa, WFH dihitung hadir.') }}
    @endif
    {{ __('両直 = dua shift masuk, 一直 = satu shift.') }}
    {{ __('Angka tiap tanggal bisa disesuaikan lewat tombol edit; Hadir dan rate dihitung ulang otomatis.') }}
</p>

{{-- Edit / tambah angka satu tanggal. Hadir & rate tidak diisi: Hadir = Total - Cuti - S+I. --}}
<dialog class="modal" id="jr-modal">
    <form method="POST" action="{{ route('hrms.absensi.japan-resume.store') }}" id="jr-form">
        @csrf
        <div class="modal-head">
            <h2 id="jr-modal-title">{{ __('Edit Japan Resume') }}</h2>
            <button type="button" class="modal-close" data-jr-close aria-label="{{ __('Tutup') }}">&times;</button>
        </div>
        <div class="modal-body jr-form">
            <div class="wide">
                <label for="jr-tanggal">{{ __('Tanggal') }}</label>
                <input id="jr-tanggal" type="date" name="tanggal" required>
                <div class="hint" id="jr-tanggal-label"></div>
                @error('tanggal') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="wide">
                <label for="jr-shift">{{ __('Shift') }}</label>
                <select id="jr-shift" name="shift" required>
                    @foreach (JapanResume::SHIFTS as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @error('shift') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="wide">
                <label for="jr-total">{{ __('Total') }}</label>
                <input id="jr-total" type="number" name="total" min="1" step="1" required>
                @error('total') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="jr-cuti">{{ __('Cuti') }}</label>
                <input id="jr-cuti" type="number" name="cuti" min="0" step="1" required>
                @error('cuti') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="jr-sakit_izin">S+I</label>
                <input id="jr-sakit_izin" type="number" name="sakit_izin" min="0" step="1" required>
                @error('sakit_izin') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div class="jr-preview">
                <span>{{ __('Hadir') }} <b id="jr-hadir">-</b></span>
                <span>{{ __('Rate') }} <b id="jr-rate">-</b></span>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-jr-close>{{ __('Batal') }}</button>
            <button type="submit" class="btn">{{ __('Simpan') }}</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('jr-modal');
        var form = document.getElementById('jr-form');
        if (!modal) return;
        var el = function (name) { return form.elements[name]; };
        var titleEdit = {{ Js::from(__('Edit Japan Resume')) }};
        var titleAdd = {{ Js::from(__('Tambah tanggal')) }};
        var defaultShift = {{ Js::from(JapanResume::SHIFT_DUA) }};

        // Hadir & rate dihitung ulang setiap angka diubah, seperti rumus di Excel.
        function preview() {
            var total = parseInt(el('total').value, 10) || 0;
            var absen = (parseInt(el('cuti').value, 10) || 0) + (parseInt(el('sakit_izin').value, 10) || 0);
            document.getElementById('jr-hadir').textContent = total ? (total - absen).toLocaleString('id-ID') : '-';
            document.getElementById('jr-rate').textContent = total ? (absen / total * 100).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%' : '-';
        }

        function open(record, keepErrors) {
            var editing = !!(record && record.label);
            document.getElementById('jr-modal-title').textContent = editing ? titleEdit : titleAdd;
            el('tanggal').value = (record && record.tanggal) || '';
            el('tanggal').readOnly = editing;
            document.getElementById('jr-tanggal-label').textContent = editing ? record.label : '';

            // Kode shift lain dari Excel (mis. 両 / 直) tetap bisa dipertahankan.
            var shift = (record && record.shift) || defaultShift;
            var select = el('shift');
            select.querySelectorAll('[data-custom]').forEach(function (option) { option.remove(); });
            if (!Array.prototype.some.call(select.options, function (option) { return option.value === shift; })) {
                var option = new Option(shift, shift);
                option.dataset.custom = '';
                select.add(option);
            }
            select.value = shift;

            ['total', 'cuti', 'sakit_izin'].forEach(function (name) {
                el(name).value = record && record[name] !== undefined && record[name] !== null ? record[name] : '';
            });
            if (!keepErrors) modal.querySelectorAll('.field-error').forEach(function (error) { error.remove(); });
            preview();
            modal.showModal();
            (editing ? el('total') : el('tanggal')).focus();
        }

        ['total', 'cuti', 'sakit_izin'].forEach(function (name) { el(name).addEventListener('input', preview); });
        modal.querySelectorAll('[data-jr-close]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
        document.querySelector('[data-jr-add]').addEventListener('click', function () { open(null); });
        document.querySelectorAll('[data-jr-edit]').forEach(function (button) {
            button.addEventListener('click', function () { open(JSON.parse(button.dataset.jrEdit)); });
        });

        // Validasi gagal: buka lagi form beserta isian sebelumnya.
        var old = {{ Js::from($errors->any() ? old() : null) }};
        if (old) open(old, true);

        var newRow = document.querySelector('tr.row-new');
        if (newRow) newRow.scrollIntoView({ block: 'center' });
    })();
</script>
@endpush
