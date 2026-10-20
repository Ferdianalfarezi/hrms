@extends('layouts.app')

@section('title', 'Absensi')

@section('styles')
    .abs-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .abs-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
    .abs-toolbar input[type=date] { width: auto; border-radius: 999px; padding: 9px 14px; }
    .abs-toolbar input[type=search] { width: 220px; border-radius: 999px; }
    .abs-toolbar select { width: auto; min-width: 150px; border-radius: 999px; }
    .day-nav { display: inline-flex; border: 1px solid var(--line-strong); border-radius: 999px; overflow: hidden; background: var(--glass-strong); }
    .day-nav a { padding: 9px 14px; font-size: 13px; font-weight: 600; color: var(--text); }
    .day-nav a + a { border-left: 1px solid var(--line-strong); }
    .day-nav a:hover { background: #fff; }

    .abs-hint { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; justify-content: flex-end; }
    .abs-toolbar-row { display: flex; align-items: center; gap: 8px 16px; flex-wrap: wrap; margin-bottom: 14px; }
    .abs-toolbar-row .abs-toolbar { margin-bottom: 0; }
    .day-summary { display: flex; gap: 6px; flex-wrap: wrap; margin-left: auto; justify-content: flex-end; }
    .day-summary .chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; background: var(--glass-strong); border: 1px solid #fff; font-size: 13px; white-space: nowrap; }
    .day-summary .chip b { font-weight: 700; }
    .day-summary .chip .pct { color: var(--muted); font-size: 12px; }
    .day-summary .chip.hadir { background: var(--success-bg); color: var(--success); border-color: transparent; }
    .day-summary .chip.absen { background: #fee2e2; color: #b91c1c; border-color: transparent; }
    .day-summary .chip.hadir .pct, .day-summary .chip.absen .pct { color: inherit; opacity: .75; }
    .day-summary .chip.tanpa-ket { background: #fee2e2; color: #b91c1c; border-color: transparent; }

    .legend-list { display: grid; gap: 8px; }
    .legend-item { display: flex; align-items: center; gap: 12px; font-size: 14px; padding: 6px 0; border-bottom: 1px solid var(--line); }
    .legend-item:last-child { border-bottom: 0; }
    .legend-item .k-tag { min-width: 48px; }

    /* Warna kode */
    .k { font-weight: 700; border-radius: 6px; }
    .k-red { background: #fee2e2; color: #b91c1c; }
    .k-amber { background: #fef3c7; color: #92400e; }
    .k-violet { background: #ede9fe; color: #6d28d9; }
    .k-orange { background: #ffedd5; color: #c2410c; }
    .k-blue { background: #dbeafe; color: #1d4ed8; }
    .k-green { background: #dcfce7; color: #15803d; }
    .k-tag { display: inline-block; min-width: 34px; padding: 2px 6px; text-align: center; font-size: 11px; font-style: normal; }

    .abs-wrap { overflow: auto; max-height: calc(100vh - 290px); min-height: 380px; background: #fff; border-radius: var(--radius); border: 1px solid #fff; box-shadow: var(--shadow); }
    table.abs { border-collapse: separate; border-spacing: 0; font-size: 12px; width: max-content; }
    table.abs th, table.abs td { padding: 0 6px; height: 34px; border-right: 1px solid var(--line); border-bottom: 1px solid var(--line); white-space: nowrap; text-align: center; background: #fff; }
    table.abs thead th { position: sticky; top: 0; z-index: 3; background: #f5f8fe; color: var(--muted); font-weight: 600; font-size: 11px; text-transform: none; letter-spacing: 0; }
    table.abs thead tr:nth-child(2) th { top: 34px; }
    table.abs .c-no { position: sticky; left: 0; z-index: 2; width: 44px; min-width: 44px; }
    table.abs .c-nik { position: sticky; left: 44px; z-index: 2; width: 60px; min-width: 60px; }
    table.abs .c-nama { position: sticky; left: 104px; z-index: 2; min-width: 190px; max-width: 190px; text-align: left; overflow: hidden; text-overflow: ellipsis; }
    table.abs .c-group { position: sticky; left: 294px; z-index: 2; width: 56px; min-width: 56px; font-weight: 600; color: var(--text); box-shadow: 4px 0 6px -4px rgba(15, 35, 80, .12); }
    table.abs thead .c-no, table.abs thead .c-nik, table.abs thead .c-nama, table.abs thead .c-group { z-index: 4; }
    table.abs .c-bagian { text-align: left; min-width: 70px; }
    table.abs td.c-nama { font-weight: 600; color: var(--text); }
    table.abs .day { width: 40px; min-width: 40px; padding: 0 3px; }
    table.abs th.day small { display: block; font-size: 9px; font-weight: 500; line-height: 1; }
    table.abs th.day b { display: block; font-size: 12px; color: var(--text); line-height: 1.3; }
    /* Sabtu & Minggu (libur) berwarna merah */
    table.abs .sun { background: #fecaca; }
    table.abs thead th.sun { background: #fca5a5; color: #991b1b; }
    table.abs thead th.sun b { color: #991b1b; }
    table.abs .sel { background: #eef4ff; }
    table.abs thead th.sel { background: var(--navy); color: #fff; }
    table.abs thead th.sel b { color: #fff; }
    table.abs .sum { width: 38px; min-width: 38px; background: #fafbfe; font-weight: 600; color: var(--text); }
    table.abs .sum.zero { color: #c5cddc; font-weight: 400; }
    table.abs .c-ket { text-align: left; min-width: 180px; color: var(--muted); }
    table.abs tbody tr:hover td { background: #f7faff; }
    table.abs tbody tr:hover td.sel { background: #e3ecff; }
    table.abs tbody tr:hover td.sun { background: #fca5a5; }

    .cell { width: 100%; height: 26px; border: 0; font: inherit; font-size: 11px; font-weight: 700; cursor: default; padding: 0; border-radius: 6px; }
    .cell:not(.k) { background: transparent; }
    .cell.present:not(.k) { background: #86efac; }
    .cell.present.editable:not(.k):hover { background: #4ade80; }
    /* Mesin absen: tidak masuk, Admin Departemen belum mengisi keterangan */
    .cell.tanpa-ket:not(.k) { background: #fee2e2; }
    .cell.tanpa-ket.editable:not(.k):hover { background: #fecaca; }
    .cell.editable { cursor: pointer; }
    .cell.editable:hover { outline: 2px solid var(--primary); outline-offset: -2px; }
    .cell.saved { animation: saved .9s ease-out; }
    @keyframes saved { from { box-shadow: 0 0 0 3px rgba(37, 99, 235, .55); } to { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); } }

    table.abs tfoot td { background: #fafbfe; font-weight: 600; color: var(--text); }
    table.abs tfoot td.foot-label { position: sticky; left: 0; z-index: 2; text-align: left; color: var(--muted); }
    table.abs tfoot tr.foot-total td { background: #eef3fd; }
    table.abs tfoot td.zero { color: #c5cddc; font-weight: 400; }

    .kode-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
    .kode-opt { position: relative; }
    .kode-opt input { position: absolute; opacity: 0; }
    .kode-opt span { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1.5px solid var(--line-strong); border-radius: 10px; font-size: 12px; cursor: pointer; font-weight: 500; }
    .kode-opt input:checked + span { border-color: var(--primary); background: #eef4ff; }
    .kode-opt input:focus-visible + span { outline: 2px solid var(--primary); }
@endsection

@section('content')
@php
    $prev = $tanggal->copy()->subDay()->toDateString();
    $next = $tanggal->copy()->addDay()->toDateString();
    $keepFilters = array_filter(['department' => $department, 'group' => $group]);
@endphp

<div class="abs-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Laporan Absensi Harian</h1>
        <p class="muted" style="margin: 2px 0 0;">
            {{ $tanggal->translatedFormat('l, d F Y') }} · {{ $karyawans->count() }} karyawan
            @if ($lockedDepartment)
                · departemen <span class="badge">{{ $lockedDepartment->name }}</span>
            @endif
        </p>
    </div>
    <div class="abs-hint">
        @if ($canEdit)
            <p class="muted small" style="margin: 0; max-width: 340px; text-align: right;">
                Klik sel pada tanggal untuk mengisi keterangan. Sel kosong berarti karyawan <strong>masuk</strong>.
            </p>
        @endif
        <button type="button" class="btn btn-ghost btn-sm" data-open-legend>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
            Keterangan kode
        </button>
    </div>
</div>

@if ($missingDepartment)
    <div class="alert">Akun Anda adalah Admin Departemen tetapi belum dipilihkan departemen. Hubungi Super Admin.</div>
@endif

<div class="abs-toolbar-row">
<form method="GET" class="abs-toolbar" id="abs-filter">
    <span class="day-nav">
        <a href="{{ route($routeName, ['tanggal' => $prev, ...$keepFilters]) }}" title="Hari sebelumnya">&lsaquo;</a>
        <a href="{{ route($routeName, $keepFilters) }}">Hari ini</a>
        <a href="{{ route($routeName, ['tanggal' => $next, ...$keepFilters]) }}" title="Hari berikutnya">&rsaquo;</a>
    </span>
    <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.requestSubmit()" aria-label="Tanggal">
    @if ($isAdmin)
        <select name="department" onchange="this.form.requestSubmit()">
            <option value="">Semua departemen</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
            <option value="none" @selected($department === 'none')>Tanpa departemen</option>
        </select>
    @endif
    <select name="group" onchange="this.form.requestSubmit()" style="min-width: 130px;">
        <option value="">Semua group</option>
        @foreach ($groupOptions as $option)
            <option value="{{ $option }}" @selected($group === $option)>Group {{ $option }}</option>
        @endforeach
        <option value="none" @selected($group === 'none')>Tanpa group</option>
    </select>
    <input type="search" id="abs-search" placeholder="Cari NIK / nama..." autocomplete="off">
</form>

{{-- Ringkasan tanggal terpilih, diisi oleh JS (ikut berubah saat sel disimpan). --}}
<div class="day-summary" id="day-summary" aria-live="polite"></div>
</div>

{{-- Daftar arti kode, dibuka lewat tombol "Keterangan kode". --}}
<dialog class="modal" id="legend-modal" style="width: min(520px, calc(100% - 32px));">
    <div class="modal-head">
        <h2>Keterangan Kode Absensi</h2>
        <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-body">
        <div class="legend-list">
            <div class="legend-item">
                <span class="k k-tag" style="background: #86efac; color: #14532d;">hijau</span>
                <span>Masuk</span>
            </div>
            <div class="legend-item">
                <span class="k k-tag" style="background: #86efac; color: #14532d;">I</span>
                <span>Hijau berkode: hadir di mesin absen, tapi admin input tidak hadir (cek ulang)</span>
            </div>
            <div class="legend-item">
                <span class="k k-tag k-red">merah</span>
                <span>Tidak masuk di mesin absen, belum ada keterangan</span>
            </div>
            @foreach ($kodes as $kode => $info)
                <div class="legend-item">
                    <span class="k k-tag k-{{ $info['tone'] }}">{{ $kode }}</span>
                    <span>{{ $info['label'] }}</span>
                    @if ($info['hadir'])
                        <span class="badge" style="margin-left: auto;">dihitung hadir</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</dialog>

<div class="abs-wrap" id="abs-wrap">
    <table class="abs" id="abs-table">
        <thead>
            <tr>
                <th class="c-no" rowspan="2">No</th>
                <th class="c-nik" rowspan="2">NIK</th>
                <th class="c-nama" rowspan="2">Nama Karyawan</th>
                <th class="c-group" rowspan="2">Group</th>
                <th class="c-bagian" rowspan="2">Bagian</th>
                <th colspan="{{ $days->count() }}">{{ $tanggal->translatedFormat('F Y') }}</th>
                <th colspan="{{ count($kodes) + 1 }}">Rekap bulan ini</th>
                <th class="c-ket" rowspan="2">Keterangan {{ $tanggal->translatedFormat('d M') }}</th>
            </tr>
            <tr>
                @foreach ($days as $day)
                    <th @class(['day', 'sun' => $day['weekend'], 'sel' => $day['selected']]) @if ($day['selected']) id="selected-day" @endif>
                        <small>{{ $day['weekday'] }}</small><b>{{ $day['day'] }}</b>
                    </th>
                @endforeach
                @foreach ($kodes as $kode => $info)
                    <th class="sum" title="{{ $info['label'] }}">{{ $kode }}</th>
                @endforeach
                <th class="sum">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($karyawans as $karyawan)
                @php($rowMarks = $marks[$karyawan->id] ?? [])
                <tr data-row="{{ $karyawan->id }}" data-search="{{ \Illuminate\Support\Str::lower($karyawan->nik.' '.$karyawan->nama) }}">
                    <td class="c-no muted">{{ $loop->iteration }}</td>
                    <td class="c-nik">{{ $karyawan->nik }}</td>
                    <td class="c-nama" title="{{ $karyawan->nama }}">{{ $karyawan->nama }}</td>
                    <td class="c-group">{{ $karyawan->group ?? '-' }}</td>
                    <td class="c-bagian">{{ $karyawan->department?->name ?? '-' }}</td>
                    @foreach ($days as $day)
                        @php($mark = $rowMarks[$day['day']] ?? null)
                        @php($salahInput = $mark && isset($hadirMesin[$karyawan->id][$day['date']]) && ! ($kodes[$mark['kode']]['hadir'] ?? false))
                        @php($tone = $mark && ! $salahInput ? ($kodes[$mark['kode']]['tone'] ?? null) : null)
                        @php($tanpaKet = ! $mark && isset($tanpaKeterangan[$karyawan->id][$day['date']]))
                        @php($label = $mark ? ($kodes[$mark['kode']]['label'] ?? $mark['kode']) : null)
                        @php($title = match (true) {
                            $salahInput => 'Hadir di mesin absen · admin input '.$label.', cek ulang',
                            $mark !== null => $label.($mark['keterangan'] ? ' · '.$mark['keterangan'] : ''),
                            $tanpaKet => 'Tidak masuk · belum ada keterangan',
                            default => 'Masuk',
                        })
                        {{-- Admin Departemen hanya mengisi hari kerja (Senin - Jumat). --}}
                        @php($cellEditable = $canEdit && ($isAdmin || ! $day['weekend']))
                        <td @class(['day', 'sun' => $day['weekend'], 'sel' => $day['selected']])>
                            <button type="button"
                                    @class(['cell', 'editable' => $cellEditable, 'k k-'.$tone => $tone !== null, 'tanpa-ket' => $tanpaKet, 'present' => $tone === null && ! $tanpaKet && ($day['worked'] || $salahInput)])
                                    data-day="{{ $day['day'] }}" data-date="{{ $day['date'] }}" @if ($day['worked']) data-worked @endif @if (isset($tanpaKeterangan[$karyawan->id][$day['date']])) data-tanpa-ket @endif @if (isset($hadirMesin[$karyawan->id][$day['date']])) data-hadir-mesin @endif
                                    data-kode="{{ $mark['kode'] ?? '' }}" data-ket="{{ $mark['keterangan'] ?? '' }}"
                                    title="{{ $title }}"
                                    @disabled(! $cellEditable)>{{ $mark['kode'] ?? '' }}</button>
                        </td>
                    @endforeach
                    @foreach ($kodes as $kode => $info)
                        <td class="sum zero" data-sum="{{ $kode }}">0</td>
                    @endforeach
                    <td class="sum zero" data-sum="total">0</td>
                    @php($selectedMark = $rowMarks[$tanggal->day] ?? null)
                    <td class="c-ket" data-ket-selected>{{ $selectedMark['keterangan'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 6 + $days->count() + count($kodes) + 1 }}" class="muted" style="text-align: left; padding: 32px;">
                        Tidak ada karyawan untuk ditampilkan.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($karyawans->isNotEmpty())
            <tfoot>
                @foreach ($kodes as $kode => $info)
                    <tr>
                        <td class="foot-label" colspan="5">
                            @if ($loop->first)<strong style="color: var(--text);">DETAIL ABSENSI</strong> · @endif
                            <i class="k k-tag k-{{ $info['tone'] }}">{{ $kode }}</i>
                        </td>
                        @foreach ($days as $day)
                            <td @class(['day', 'zero', 'sun' => $day['weekend'], 'sel' => $day['selected']]) data-foot="{{ $kode }}" data-day="{{ $day['day'] }}">0</td>
                        @endforeach
                        <td colspan="{{ count($kodes) + 2 }}"></td>
                    </tr>
                @endforeach
                <tr class="foot-total">
                    <td class="foot-label" colspan="5"><strong style="color: var(--text);">Total tidak hadir</strong></td>
                    @foreach ($days as $day)
                        <td @class(['day', 'sun' => $day['weekend'], 'sel' => $day['selected']]) data-foot="absen" data-day="{{ $day['day'] }}">0</td>
                    @endforeach
                    <td colspan="{{ count($kodes) + 2 }}"></td>
                </tr>
                <tr class="foot-total">
                    <td class="foot-label" colspan="5"><strong style="color: var(--text);">% tidak hadir</strong> <span class="small">dari {{ $karyawans->count() }} karyawan</span></td>
                    @foreach ($days as $day)
                        <td @class(['day', 'sun' => $day['weekend'], 'sel' => $day['selected']]) data-foot="persen" data-day="{{ $day['day'] }}">0</td>
                    @endforeach
                    <td colspan="{{ count($kodes) + 2 }}"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</div>

@if ($canEdit)
    <dialog class="modal" id="abs-modal" style="width: min(560px, calc(100% - 32px));">
        <form id="abs-form" action="{{ route('hrms.absensi.store') }}">
            @csrf
            <input type="hidden" name="karyawan_id">
            <input type="hidden" name="tanggal">
            <div class="modal-head">
                <div>
                    <h2 id="abs-modal-title">Absensi</h2>
                    <div class="muted small" id="abs-modal-sub"></div>
                </div>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert" id="abs-error" hidden></div>
                <div class="kode-grid">
                    <label class="kode-opt">
                        <input type="radio" name="kode" value="">
                        <span><i class="k k-tag" style="background: var(--success-bg); color: var(--success);">✓</i>Masuk</span>
                    </label>
                    @foreach ($kodes as $kode => $info)
                        <label class="kode-opt">
                            <input type="radio" name="kode" value="{{ $kode }}">
                            <span><i class="k k-tag k-{{ $info['tone'] }}">{{ $kode }}</i>{{ $info['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                <div style="margin-top: 16px;">
                    <label for="abs-ket">Keterangan <span class="muted">(opsional)</span></label>
                    <input id="abs-ket" type="text" name="keterangan" maxlength="255" placeholder="Mis. PC 13:00, DT 07:27, surat dokter menyusul">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" id="abs-submit">Simpan</button>
            </div>
        </form>
    </dialog>
@endif
@endsection

@push('scripts')
<script>
    (function () {
        var kodes = {{ Js::from($kodes) }};
        var table = document.getElementById('abs-table');
        var wrap = document.getElementById('abs-wrap');
        var selectedDay = {{ $tanggal->day }};
        var headcount = {{ $karyawans->count() }};

        function setNumber(cell, value) {
            cell.textContent = value;
            cell.classList.toggle('zero', !value || value === '0');
        }

        // Hitung ulang rekap per karyawan, detail per tanggal, dan ringkasan tanggal terpilih.
        function recalc() {
            var perDay = {};
            table.querySelectorAll('tbody tr[data-row]').forEach(function (row) {
                var counts = {}, total = 0;
                row.querySelectorAll('.cell').forEach(function (cell) {
                    var kode = cell.dataset.kode;
                    if (!kode) return;
                    counts[kode] = (counts[kode] || 0) + 1;
                    total++;
                    var day = cell.dataset.day;
                    perDay[day] = perDay[day] || { _absen: 0 };
                    perDay[day][kode] = (perDay[day][kode] || 0) + 1;
                    if (kodes[kode] && !kodes[kode].hadir) perDay[day]._absen++;
                });
                row.querySelectorAll('[data-sum]').forEach(function (cell) {
                    setNumber(cell, cell.dataset.sum === 'total' ? total : (counts[cell.dataset.sum] || 0));
                });
            });

            table.querySelectorAll('tfoot [data-foot]').forEach(function (cell) {
                var day = perDay[cell.dataset.day] || { _absen: 0 };
                if (cell.dataset.foot === 'absen') setNumber(cell, day._absen);
                else if (cell.dataset.foot === 'persen') setNumber(cell, headcount ? (Math.round(day._absen * 1000 / headcount) / 10).toString().replace('.', ',') : 0);
                else setNumber(cell, day[cell.dataset.foot] || 0);
            });

            var today = perDay[selectedDay] || { _absen: 0 };
            var hadir = headcount - today._absen;
            var pct = function (value) {
                return headcount ? (Math.round(value * 1000 / headcount) / 10).toString().replace('.', ',') + '%' : '0%';
            };
            var html = '<span class="chip hadir">Hadir <b>' + hadir + '</b><span class="pct">' + pct(hadir) + '</span></span>'
                + '<span class="chip absen">Tidak hadir <b>' + today._absen + '</b><span class="pct">' + pct(today._absen) + '</span></span>';
            // Mesin absen: tidak masuk tapi belum ada kode (sel merah) pada tanggal terpilih.
            var tanpaKet = table.querySelectorAll('tbody tr[data-row] .cell.tanpa-ket[data-day="' + selectedDay + '"]').length;
            if (tanpaKet) {
                html += '<span class="chip tanpa-ket">Tidak hadir tanpa keterangan <b>' + tanpaKet + '</b></span>';
            }
            Object.keys(kodes).forEach(function (kode) {
                if (today[kode]) {
                    html += '<span class="chip"><i class="k k-tag k-' + kodes[kode].tone + '">' + kode.replace('<', '&lt;').replace('>', '&gt;') + '</i><b>' + today[kode] + '</b></span>';
                }
            });
            var summary = document.getElementById('day-summary');
            summary.innerHTML = html;
            // Kode berisi "<" / ">" jadi diisi lewat textContent supaya aman.
            var i = 0;
            Object.keys(kodes).forEach(function (kode) {
                if (today[kode]) summary.querySelectorAll('.chip i')[i++].textContent = kode;
            });
        }

        // Pencarian cepat di browser (tidak mengubah angka rekap).
        document.getElementById('abs-search').addEventListener('input', function (event) {
            var term = event.target.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr[data-row]').forEach(function (row) {
                row.hidden = term !== '' && row.dataset.search.indexOf(term) === -1;
            });
        });
        document.getElementById('abs-filter').addEventListener('submit', function () {
            document.getElementById('abs-search').disabled = true; // jangan ikut ke URL
        });

        // Geser tabel supaya kolom tanggal terpilih langsung terlihat.
        var selectedHeader = document.getElementById('selected-day');
        if (selectedHeader) {
            wrap.scrollLeft = Math.max(selectedHeader.offsetLeft - 520, 0);
        }

        recalc();

        var modal = document.getElementById('abs-modal');
        // Modal keterangan kode (bisa dibuka semua user, termasuk yang tidak bisa mengisi).
        var legend = document.getElementById('legend-modal');
        document.querySelector('[data-open-legend]').addEventListener('click', function () { legend.showModal(); });
        legend.querySelector('[data-close]').addEventListener('click', function () { legend.close(); });
        legend.addEventListener('click', function (event) { if (event.target === legend) legend.close(); });

        if (!modal) return;
        var form = document.getElementById('abs-form');
        var error = document.getElementById('abs-error');
        var activeCell = null;

        table.addEventListener('click', function (event) {
            var cell = event.target.closest('.cell.editable');
            if (!cell) return;
            activeCell = cell;
            var row = cell.closest('tr');
            form.elements.karyawan_id.value = row.dataset.row;
            form.elements.tanggal.value = cell.dataset.date;
            form.elements.keterangan.value = cell.dataset.ket || '';
            form.querySelectorAll('input[name=kode]').forEach(function (radio) { radio.checked = radio.value === (cell.dataset.kode || ''); });
            document.getElementById('abs-modal-title').textContent = row.querySelector('.c-nama').textContent;
            var date = new Date(cell.dataset.date + 'T00:00:00');
            document.getElementById('abs-modal-sub').textContent = 'NIK ' + row.querySelector('.c-nik').textContent + ' · '
                + date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            error.hidden = true;
            modal.showModal();
            (form.querySelector('input[name=kode]:checked') || form.querySelector('input[name=kode]')).focus();
        });

        modal.querySelectorAll('[data-close]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
        modal.addEventListener('click', function (event) { if (event.target === modal) modal.close(); });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var submit = document.getElementById('abs-submit');
            submit.disabled = true;
            submit.textContent = 'Menyimpan...';

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (body) { return { ok: response.ok, status: response.status, body: body }; });
            }).then(function (result) {
                if (!result.ok) {
                    var message = result.status === 403 ? 'Anda tidak berhak mengisi absensi karyawan ini.'
                        : result.status === 419 ? 'Sesi habis, muat ulang halaman lalu coba lagi.'
                        : (result.body.errors ? Object.values(result.body.errors)[0][0] : (result.body.message || 'Gagal menyimpan.'));
                    error.textContent = message;
                    error.hidden = false;
                    return;
                }
                var kode = result.body.kode || '';
                var ket = result.body.keterangan || '';
                activeCell.dataset.kode = kode;
                activeCell.dataset.ket = ket;
                activeCell.textContent = kode;
                var tanpaKet = !kode && 'tanpaKet' in activeCell.dataset;
                var salahInput = kode && !kodes[kode].hadir && 'hadirMesin' in activeCell.dataset;
                activeCell.className = 'cell editable' + (kode && !salahInput ? ' k k-' + kodes[kode].tone : (tanpaKet ? ' tanpa-ket' : ('worked' in activeCell.dataset || salahInput ? ' present' : '')));
                activeCell.title = salahInput ? 'Hadir di mesin absen · admin input ' + kodes[kode].label + ', cek ulang'
                    : kode ? kodes[kode].label + (ket ? ' · ' + ket : '') : (tanpaKet ? 'Tidak masuk · belum ada keterangan' : 'Masuk');
                if (Number(activeCell.dataset.day) === selectedDay) {
                    activeCell.closest('tr').querySelector('[data-ket-selected]').textContent = ket;
                }
                void activeCell.offsetWidth;
                activeCell.classList.add('saved');
                recalc();
                modal.close();
            }).catch(function () {
                error.textContent = 'Tidak bisa terhubung ke server. Coba lagi.';
                error.hidden = false;
            }).finally(function () {
                submit.disabled = false;
                submit.textContent = 'Simpan';
            });
        });
    })();
</script>
@endpush
