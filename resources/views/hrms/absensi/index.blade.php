@extends('layouts.app')

@section('title', __('Absensi'))

@section('styles')
    .abs-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .abs-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
    /* Semua kontrol filter setinggi 44px supaya sejajar. */
    .abs-toolbar input, .abs-toolbar select, .abs-toolbar .day-nav { height: 44px; }
    .abs-toolbar input[type=date], .abs-toolbar input[type=month] {
        width: auto; padding: 0 14px; font: inherit; font-size: 14px; color: var(--text);
        background: var(--glass-strong); border: 1px solid var(--line-strong); border-radius: 999px; cursor: pointer;
    }
    .abs-toolbar input[type=date]::-webkit-calendar-picker-indicator,
    .abs-toolbar input[type=month]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: .55; }
    .abs-toolbar input[type=search] { width: 220px; border-radius: 999px; }
    .abs-toolbar select { width: auto; min-width: 150px; border-radius: 999px; }
    .day-nav { display: inline-flex; border: 1px solid var(--line-strong); border-radius: 999px; overflow: hidden; background: var(--glass-strong); }
    .day-nav a { display: inline-flex; align-items: center; padding: 0 14px; font-size: 13px; font-weight: 600; color: var(--text); }
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
    // Filter "Tidak masuk" & "Terlambat" bernavigasi per tanggal; tampilan biasa per bulan.
    $tidakMasuk = $status === 'tidak_masuk';
    $terlambat = $status === 'terlambat';
    $perTanggal = $tidakMasuk || $terlambat;
    $prev = $perTanggal ? ['tanggal' => $tanggal->copy()->subDay()->toDateString()] : ['bulan' => $tanggal->copy()->subMonthNoOverflow()->format('Y-m')];
    $next = $perTanggal ? ['tanggal' => $tanggal->copy()->addDay()->toDateString()] : ['bulan' => $tanggal->copy()->addMonthNoOverflow()->format('Y-m')];
    $keepFilters = array_filter(['department' => $department, 'group' => $group, 'status' => $status]);
    // Kelas kolom tanggal dihitung sekali per hari, bukan per sel.
    $days = $days->map(fn (array $day) => [...$day, 'tdClass' => 'day'.($day['weekend'] ? ' sun' : '').($day['selected'] ? ' sel' : '')]);
@endphp

<div class="abs-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ __('Laporan Absensi Harian') }}</h1>
        <p class="muted" style="margin: 2px 0 0;">
            @if ($tidakMasuk)
                {{ $tanggal->translatedFormat('l, d F Y') }} · {{ __(':count karyawan tidak masuk', ['count' => $karyawans->count()]) }}
            @elseif ($terlambat)
                {{ $tanggal->translatedFormat('l, d F Y') }} · {{ __(':count karyawan terlambat', ['count' => $karyawans->count()]) }}
            @else
                {{ $tanggal->translatedFormat('F Y') }} · {{ __(':count karyawan', ['count' => $karyawans->count()]) }}
            @endif
            @if ($lockedDepartments->isNotEmpty())
                {{ __('· departemen') }}
                @foreach ($lockedDepartments as $lockedDepartment)
                    <span class="badge">{{ $lockedDepartment->name }}</span>
                @endforeach
            @endif
        </p>
    </div>
    <div class="abs-hint">
        @if ($canEdit)
            <p class="muted small" style="margin: 0; max-width: 340px; text-align: right;">
                {{ __('Klik sel pada tanggal untuk mengisi keterangan. Sel kosong berarti karyawan') }} <strong>{{ __('masuk') }}</strong>.
                @unless ($isAdmin)
                    {{ __('Mengubah data yang sudah terisi lewat menu') }} <a href="{{ route('hrms.absensi.index') }}" style="text-decoration: underline;">{{ __('Daftar') }}</a> {{ __('(perlu approval HRD).') }}
                @endunless
            </p>
        @endif
        <button type="button" class="btn btn-ghost btn-sm" data-open-legend>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
            {{ __('Keterangan kode') }}
        </button>
    </div>
</div>

@if ($missingDepartment)
    <div class="alert">{{ __('Akun Anda adalah Admin Departemen tetapi belum dipilihkan departemen. Hubungi Super Admin.') }}</div>
@endif

<div class="abs-toolbar-row">
<form method="GET" class="abs-toolbar" id="abs-filter">
    <span class="day-nav">
        <a href="{{ route($routeName, [...$prev, ...$keepFilters]) }}" title="{{ $perTanggal ? __('Hari sebelumnya') : __('Bulan sebelumnya') }}">&lsaquo;</a>
        <a href="{{ route($routeName, $keepFilters) }}">{{ $perTanggal ? __('Hari ini') : __('Bulan ini') }}</a>
        <a href="{{ route($routeName, [...$next, ...$keepFilters]) }}" title="{{ $perTanggal ? __('Hari berikutnya') : __('Bulan berikutnya') }}">&rsaquo;</a>
    </span>
    @if ($perTanggal)
        <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.requestSubmit()" aria-label="{{ $terlambat ? __('Tanggal terlambat') : __('Tanggal tidak masuk') }}">
    @else
        <input type="month" name="bulan" value="{{ $tanggal->format('Y-m') }}" onchange="this.form.requestSubmit()" aria-label="{{ __('Bulan') }}">
    @endif
    <select name="status" onchange="this.form.requestSubmit()" aria-label="{{ __('Status') }}">
        <option value="">{{ __('Semua karyawan') }}</option>
        <option value="tidak_masuk" @selected($tidakMasuk)>{{ __('Tidak masuk') }}</option>
        <option value="terlambat" @selected($terlambat)>{{ __('Terlambat') }}</option>
    </select>
    @if ($canFilterDepartment)
        <select name="department" onchange="this.form.requestSubmit()">
            <option value="">{{ __('Semua departemen') }}</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
            <option value="none" @selected($department === 'none')>{{ __('Tanpa departemen') }}</option>
        </select>
    @endif
    <select name="group" onchange="this.form.requestSubmit()" style="min-width: 130px;">
        <option value="">{{ __('Semua group') }}</option>
        @foreach ($groupOptions as $option)
            <option value="{{ $option }}" @selected($group === $option)>{{ __('Group') }} {{ $option }}</option>
        @endforeach
        <option value="none" @selected($group === 'none')>{{ __('Tanpa group') }}</option>
    </select>
    <input type="search" id="abs-search" placeholder="{{ __('Cari NIK / nama...') }}" autocomplete="off">
</form>

{{-- Ringkasan tanggal terpilih, diisi oleh JS (ikut berubah saat sel disimpan). --}}
<div class="day-summary" id="day-summary" aria-live="polite"></div>
</div>

{{-- Daftar arti kode, dibuka lewat tombol "Keterangan kode". --}}
<dialog class="modal" id="legend-modal" style="width: min(520px, calc(100% - 32px));">
    <div class="modal-head">
        <h2>{{ __('Keterangan Kode Absensi') }}</h2>
        <button type="button" class="modal-close" data-close aria-label="{{ __('Tutup') }}">&times;</button>
    </div>
    <div class="modal-body">
        <div class="legend-list">
            <div class="legend-item">
                <span class="k k-tag" style="background: #86efac; color: #14532d;">{{ __('hijau') }}</span>
                <span>{{ __('Masuk') }}</span>
            </div>
            <div class="legend-item">
                <span class="k k-tag" style="background: #86efac; color: #14532d;">I</span>
                <span>{{ __('Hijau berkode: hadir di mesin absen, tapi admin input tidak hadir (cek ulang)') }}</span>
            </div>
            <div class="legend-item">
                <span class="k k-tag k-red">{{ __('merah') }}</span>
                <span>{{ __('Tidak masuk di mesin absen, belum ada keterangan') }}</span>
            </div>
            @foreach ($kodes as $kode => $info)
                <div class="legend-item">
                    <span class="k k-tag k-{{ $info['tone'] }}">{{ $kode }}</span>
                    <span>{{ $info['label'] }}</span>
                    @if ($info['hadir'])
                        <span class="badge" style="margin-left: auto;">{{ __('dihitung hadir') }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</dialog>

<div class="abs-wrap" id="abs-wrap">
    <table class="abs" id="abs-table" data-month="{{ $tanggal->format('Y-m') }}">
        <thead>
            <tr>
                <th class="c-no" rowspan="2">{{ __('No') }}</th>
                <th class="c-nik" rowspan="2">NIK</th>
                <th class="c-nama" rowspan="2">{{ __('Nama Karyawan') }}</th>
                <th class="c-group" rowspan="2">{{ __('Group') }}</th>
                <th class="c-bagian" rowspan="2">{{ __('Bagian') }}</th>
                <th colspan="{{ $days->count() }}">{{ $tanggal->translatedFormat('F Y') }}</th>
                <th colspan="{{ count($kodes) + 1 }}">{{ __('Rekap bulan ini') }}</th>
                <th class="c-ket" rowspan="2">{{ __('Keterangan') }} {{ $tanggal->translatedFormat('d M') }}</th>
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
                <th class="sum">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($karyawans as $karyawan)
                <tr data-row="{{ $karyawan->id }}" data-search="{{ \Illuminate\Support\Str::lower($karyawan->nik.' '.$karyawan->nama) }}">
                    <td class="c-no muted">{{ $loop->iteration }}</td>
                    <td class="c-nik">{{ $karyawan->nik }}</td>
                    <td class="c-nama" title="{{ $karyawan->nama }}">{{ $karyawan->nama }}</td>
                    <td class="c-group">{{ $karyawan->group ?? '-' }}</td>
                    <td class="c-bagian">{{ $karyawan->department?->name ?? '-' }}</td>
                    @php
                        // Sel tanggal dirakit sebagai string ringkas (tanpa spasi & atribut kosong): ratusan karyawan x 31 hari
                        // membuat HTML belasan MB kalau ditulis dengan Blade biasa, dan itu yang bikin halaman lama terbuka.
                        // Tanggal lengkap sel dihitung di JS dari data-month tabel + data-day.
                        $rowMarks = $marks[$karyawan->id] ?? [];
                        $selectedMark = $rowMarks[$tanggal->day] ?? null;
                        $cells = '';
                        foreach ($days as $day) {
                            $date = $day['date'];
                            $mark = $rowMarks[$day['day']] ?? null;
                            $hadirDiMesin = isset($hadirMesin[$karyawan->id][$date]);
                            $mesinTanpaKet = isset($tanpaKeterangan[$karyawan->id][$date]);
                            $salahInput = $mark && $hadirDiMesin && ! ($kodes[$mark['kode']]['hadir'] ?? false);
                            $tone = $mark && ! $salahInput ? ($kodes[$mark['kode']]['tone'] ?? null) : null;
                            $tanpaKet = ! $mark && $mesinTanpaKet;
                            $label = $mark ? ($kodes[$mark['kode']]['label'] ?? $mark['kode']) : null;
                            $title = match (true) {
                                $salahInput => __('Hadir di mesin absen · admin input :kode, cek ulang', ['kode' => $label]),
                                $mark !== null => $label.($mark['menit'] ? ' · '.$mark['menit'].' menit' : '').($mark['keterangan'] ? ' · '.$mark['keterangan'] : ''),
                                $tanpaKet => __('Tidak masuk · belum ada keterangan'),
                                default => __('Masuk'),
                            };
                            // Admin Departemen hanya mengisi hari kerja (Senin - Jumat) yang masih kosong;
                            // mengubah data lama lewat menu Daftar karena perlu approval HRD.
                            $cellEditable = $canEdit && ($isAdmin || (! $day['weekend'] && $mark === null));
                            $cellClass = 'cell'.($cellEditable ? ' editable' : '').($tone !== null ? ' k k-'.$tone : '')
                                .($tanpaKet ? ' tanpa-ket' : '').($tone === null && ! $tanpaKet && ($day['worked'] || $salahInput) ? ' present' : '');

                            $cells .= '<td class="'.$day['tdClass'].'"><button type="button" class="'.$cellClass.'" data-day="'.$day['day'].'"'
                                .($day['worked'] ? ' data-worked' : '').($mesinTanpaKet ? ' data-tanpa-ket' : '').($hadirDiMesin ? ' data-hadir-mesin' : '')
                                .($mark ? ' data-kode="'.e($mark['kode']).'"'.($mark['menit'] ? ' data-menit="'.$mark['menit'].'"' : '').($mark['keterangan'] ? ' data-ket="'.e($mark['keterangan']).'"' : '') : '')
                                .' title="'.e($title).'"'.($cellEditable ? '' : ' disabled').'>'.e($mark['kode'] ?? '').'</button></td>';
                        }
                    @endphp
                    {!! $cells !!}
                    @foreach ($kodes as $kode => $info)
                        <td class="sum zero" data-sum="{{ $kode }}">0</td>
                    @endforeach
                    <td class="sum zero" data-sum="total">0</td>
                    <td class="c-ket" data-ket-selected>{{ $selectedMark['keterangan'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 6 + $days->count() + count($kodes) + 1 }}" class="muted" style="text-align: left; padding: 32px;">
                        {{ $tidakMasuk ? __('Tidak ada karyawan yang tidak masuk di tanggal ini.') : ($terlambat ? __('Tidak ada karyawan yang terlambat di tanggal ini.') : __('Tidak ada karyawan untuk ditampilkan.')) }}
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($karyawans->isNotEmpty())
            <tfoot>
                @foreach ($kodes as $kode => $info)
                    <tr>
                        <td class="foot-label" colspan="5">
                            @if ($loop->first)<strong style="color: var(--text);">{{ __('DETAIL ABSENSI') }}</strong> · @endif
                            <i class="k k-tag k-{{ $info['tone'] }}">{{ $kode }}</i>
                        </td>
                        @foreach ($days as $day)
                            <td @class(['day', 'zero', 'sun' => $day['weekend'], 'sel' => $day['selected']]) data-foot="{{ $kode }}" data-day="{{ $day['day'] }}">0</td>
                        @endforeach
                        <td colspan="{{ count($kodes) + 2 }}"></td>
                    </tr>
                @endforeach
                <tr class="foot-total">
                    <td class="foot-label" colspan="5"><strong style="color: var(--text);">{{ __('Total tidak hadir') }}</strong></td>
                    @foreach ($days as $day)
                        <td @class(['day', 'sun' => $day['weekend'], 'sel' => $day['selected']]) data-foot="absen" data-day="{{ $day['day'] }}">0</td>
                    @endforeach
                    <td colspan="{{ count($kodes) + 2 }}"></td>
                </tr>
                <tr class="foot-total">
                    <td class="foot-label" colspan="5"><strong style="color: var(--text);">{{ __('% tidak hadir') }}</strong> <span class="small">{{ __('dari :count karyawan', ['count' => $karyawans->count()]) }}</span></td>
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
                    <h2 id="abs-modal-title">{{ __('Absensi') }}</h2>
                    <div class="muted small" id="abs-modal-sub"></div>
                </div>
                <button type="button" class="modal-close" data-close aria-label="{{ __('Tutup') }}">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert" id="abs-error" hidden></div>
                <div style="margin-bottom: 16px;">
                    <label for="abs-alasan">{{ __('Alasan') }}</label>
                    <select id="abs-alasan" name="pilihan_alasan">
                        <option value="">{{ __('Pilih alasan (kode terisi otomatis)...') }}</option>
                        @foreach (\App\Modules\Hrms\Models\Absensi::ALASAN as $kategoriAlasan => $alasans)
                            <optgroup label="{{ __($kategoriAlasan) }}">
                                @foreach ($alasans as $alasan => $kodeAlasan)
                                    <option value="{{ $alasan }}" data-alasan-kode="{{ $kodeAlasan }}">{{ $alasan }} ({{ $kodeAlasan }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                        <option value="{{ \App\Modules\Hrms\Models\Absensi::ALASAN_LAIN }}">{{ __(\App\Modules\Hrms\Models\Absensi::ALASAN_LAIN) }} {{ __('(pilih kode sendiri, keterangan wajib)') }}</option>
                    </select>
                </div>
                <div class="kode-grid">
                    <label class="kode-opt">
                        <input type="radio" name="kode" value="">
                        <span><i class="k k-tag" style="background: var(--success-bg); color: var(--success);">✓</i>{{ __('Masuk') }}</span>
                    </label>
                    @foreach ($kodes as $kode => $info)
                        <label class="kode-opt">
                            <input type="radio" name="kode" value="{{ $kode }}">
                            <span><i class="k k-tag k-{{ $info['tone'] }}">{{ $kode }}</i>{{ $info['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                <div style="margin-top: 16px;" id="abs-menit-wrap" hidden>
                    <label for="abs-menit" id="abs-menit-label">{{ __('Terlambat berapa menit?') }}</label>
                    <input id="abs-menit" type="number" name="menit_terlambat" min="1" max="720" placeholder="{{ __('Mis. 45') }}" inputmode="numeric" disabled>
                    <div class="muted small" style="margin-top: 4px;">{{ __('Isi menit sebenarnya. DT< / PC< (maks. 30 menit) boleh dikosongkan, otomatis 30 menit. Potongan dihitung dibulatkan ke atas per 30 menit: 1 - 30 menit = 30 menit, 31 - 60 menit = 1 jam, dst.') }}</div>
                </div>
                <div style="margin-top: 16px;">
                    <label for="abs-ket">{{ __('Keterangan') }} <span class="muted" id="abs-ket-hint">{{ __('(opsional)') }}</span></label>
                    <input id="abs-ket" type="text" name="keterangan" maxlength="255" placeholder="{{ __('Mis. PC 13:00, DT 07:27, surat dokter menyusul') }}">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>{{ __('Batal') }}</button>
                <button type="submit" class="btn" id="abs-submit">{{ __('Simpan') }}</button>
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
        // Ringkasan Hadir / Tidak hadir dari seluruh karyawan yang bisa dilihat user (tidak ikut filter), per hari kerja:
        // grup pagi + NS di tanggal terpilih, grup malam di tanggal shift malamnya (sama seperti kartu per shift di dashboard).
        var ringkasan = {{ Js::from($ringkasanHarian) }};
        var hariKerja = {{ Js::from($jadwalHariKerja) }};

        // Sel ini ikut dihitung di ringkasan hari kerja? (Tanpa rotasi: semua karyawan di tanggal terpilih.)
        function ikutRingkasan(cell) {
            var date = table.dataset.month + '-' + String(cell.dataset.day).padStart(2, '0');
            var group = cell.closest('tr').querySelector('.c-group').textContent.trim();
            if (hariKerja.pagi === null && hariKerja.malam === null) return date === hariKerja.tanggal;
            return (date === hariKerja.tanggal && (group === 'NS' || group === hariKerja.pagi))
                || (date === hariKerja.tanggalMalam && group === hariKerja.malam);
        }

        function setNumber(cell, value) {
            cell.textContent = value;
            cell.classList.toggle('zero', !value || value === '0');
        }

        function isTidakHadir(cell) {
            var kode = cell.dataset.kode;
            return kode ? !!(kodes[kode] && !kodes[kode].hadir) : 'tanpaKet' in cell.dataset;
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

            // Ringkasan tanggal terpilih: hadir & tidak hadir dari keseluruhan karyawan. Tidak hadir = kode tidak hadir
            // + tidak masuk di mesin absen yang belum ada keterangan (sel merah).
            var tidakHadir = ringkasan.tidakHadir;
            var hadir = ringkasan.total - tidakHadir;
            var pct = function (value) {
                return ringkasan.total ? (Math.round(value * 1000 / ringkasan.total) / 10).toString().replace('.', ',') + '%' : '0%';
            };
            document.getElementById('day-summary').innerHTML =
                '<span class="chip hadir">' + __t('Hadir') + ' <b>' + hadir + '</b><span class="pct">' + pct(hadir) + '</span></span>'
                + '<span class="chip absen">' + __t('Tidak hadir') + ' <b>' + tidakHadir + '</b><span class="pct">' + pct(tidakHadir) + '</span></span>';
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
        // Kolom menit muncul untuk semua kode DT / PC: wajib untuk DT> / PC>; DT< / PC< opsional (maks. 30, kosong = 30 menit).
        var kodeWajibMenit = {{ Js::from(\App\Modules\Hrms\Models\Absensi::KODE_WAJIB_MENIT) }};
        var kodeMenitOtomatis = {{ Js::from(\App\Modules\Hrms\Models\Absensi::KODE_MENIT_OTOMATIS) }};
        // Label kolom menit mengikuti jenis kodenya: datang terlambat (DT) atau pulang cepat (PC).
        var kodePulangCepat = {{ Js::from(\App\Modules\Hrms\Models\Absensi::KODE_PULANG_CEPAT) }};
        var labelMenit = { terlambat: @json(__('Terlambat berapa menit?')), pulangCepat: @json(__('Pulang cepat berapa menit?')), opsional: @json(__('(opsional, kosong = 30 menit)')), contoh: @json(__('Mis. 45')) };

        // Atur kolom menit untuk kode yang dipilih; mengembalikan true kalau kolomnya tampil.
        function aturKolomMenit(kode, label, input) {
            var wajib = kodeWajibMenit.indexOf(kode) !== -1;
            var otomatis = kodeMenitOtomatis.indexOf(kode) !== -1;
            label.textContent = (kodePulangCepat.indexOf(kode) !== -1 ? labelMenit.pulangCepat : labelMenit.terlambat) + (otomatis ? ' ' + labelMenit.opsional : '');
            input.disabled = !(wajib || otomatis);
            input.required = wajib;
            input.max = otomatis ? 30 : 720;
            input.placeholder = otomatis ? '30' : labelMenit.contoh;
            return wajib || otomatis;
        }

        function syncMenit() {
            var checked = form.querySelector('input[name=kode]:checked');
            document.getElementById('abs-menit-wrap').hidden = !aturKolomMenit(checked ? checked.value : '', document.getElementById('abs-menit-label'), form.elements.menit_terlambat);
        }
        form.querySelectorAll('input[name=kode]').forEach(function (radio) { radio.addEventListener('change', syncMenit); });

        // Pilih alasan: isi keterangan dan pilih kode bawaannya, kecuali kode yang dipilih sudah satu kategori (mis. SD untuk sakit).
        var alasanSelect = document.getElementById('abs-alasan');
        var kategoriKode = {{ Js::from(array_values(\App\Modules\Hrms\Models\Absensi::KATEGORI)) }};
        var alasanLain = {{ Js::from(\App\Modules\Hrms\Models\Absensi::ALASAN_LAIN) }};

        function isAlasanDaftar(ket) {
            return Array.prototype.some.call(alasanSelect.options, function (option) { return option.value && option.value !== alasanLain && option.value === ket; });
        }

        // Alasan "Lain-lain": keterangan wajib diisi.
        function syncKeteranganWajib() {
            var wajib = alasanSelect.value === alasanLain;
            form.elements.keterangan.required = wajib;
            document.getElementById('abs-ket-hint').textContent = wajib ? '(wajib)' : '(opsional)';
        }

        alasanSelect.addEventListener('change', function () {
            var option = alasanSelect.selectedOptions[0];
            syncKeteranganWajib();
            if (option && option.value === alasanLain) {
                // Keterangan bawaan alasan lain dikosongkan supaya admin menulis sendiri.
                if (isAlasanDaftar(form.elements.keterangan.value)) form.elements.keterangan.value = '';
                form.elements.keterangan.focus();
                return;
            }
            if (!option || !option.value) return;
            form.elements.keterangan.value = option.value;
            var kode = option.dataset.alasanKode;
            var checked = form.querySelector('input[name=kode]:checked');
            var sameKategori = checked && kategoriKode.some(function (kodes) {
                return kodes.indexOf(kode) !== -1 && kodes.indexOf(checked.value) !== -1;
            });
            if (!sameKategori) {
                form.querySelectorAll('input[name=kode]').forEach(function (radio) { radio.checked = radio.value === kode; });
                syncMenit();
            }
        });

        table.addEventListener('click', function (event) {
            var cell = event.target.closest('.cell.editable');
            if (!cell) return;
            activeCell = cell;
            var row = cell.closest('tr');
            form.elements.karyawan_id.value = row.dataset.row;
            var isoDate = table.dataset.month + '-' + String(cell.dataset.day).padStart(2, '0');
            form.elements.tanggal.value = isoDate;
            form.elements.keterangan.value = cell.dataset.ket || '';
            alasanSelect.value = isAlasanDaftar(cell.dataset.ket) ? cell.dataset.ket : '';
            syncKeteranganWajib();
            form.querySelectorAll('input[name=kode]').forEach(function (radio) { radio.checked = radio.value === (cell.dataset.kode || ''); });
            form.elements.menit_terlambat.value = cell.dataset.menit || '';
            syncMenit();
            document.getElementById('abs-modal-title').textContent = row.querySelector('.c-nama').textContent;
            var date = new Date(isoDate + 'T00:00:00');
            document.getElementById('abs-modal-sub').textContent = 'NIK ' + row.querySelector('.c-nik').textContent + ' · '
                + date.toLocaleDateString(@json(__('id-ID')), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
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
            submit.textContent = __t('Menyimpan...');

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (body) { return { ok: response.ok, status: response.status, body: body }; });
            }).then(function (result) {
                if (!result.ok) {
                    var message = result.status === 403 ? __t('Anda tidak berhak mengisi absensi karyawan ini.')
                        : result.status === 419 ? __t('Sesi habis, muat ulang halaman lalu coba lagi.')
                        : (result.body.errors ? Object.values(result.body.errors)[0][0] : (result.body.message || __t('Gagal menyimpan.')));
                    error.textContent = message;
                    error.hidden = false;
                    return;
                }
                var kode = result.body.kode || '';
                var ket = result.body.keterangan || '';
                var menit = result.body.menit_terlambat || '';
                var sebelumnyaTidakHadir = isTidakHadir(activeCell);
                activeCell.dataset.kode = kode;
                if (menit) activeCell.dataset.menit = menit; else delete activeCell.dataset.menit;
                activeCell.dataset.ket = ket;
                activeCell.textContent = kode;
                var tanpaKet = !kode && 'tanpaKet' in activeCell.dataset;
                var salahInput = kode && !kodes[kode].hadir && 'hadirMesin' in activeCell.dataset;
                activeCell.className = 'cell editable' + (kode && !salahInput ? ' k k-' + kodes[kode].tone : (tanpaKet ? ' tanpa-ket' : ('worked' in activeCell.dataset || salahInput ? ' present' : '')));
                activeCell.title = salahInput ? __t('Hadir di mesin absen · admin input :kode, cek ulang', { kode: kodes[kode].label })
                    : kode ? kodes[kode].label + (menit ? ' · ' + __t(':menit menit', { menit: menit }) : '') + (ket ? ' · ' + ket : '') : (tanpaKet ? __t('Tidak masuk · belum ada keterangan') : __t('Masuk'));
                if (Number(activeCell.dataset.day) === selectedDay) {
                    activeCell.closest('tr').querySelector('[data-ket-selected]').textContent = ket;
                }
                if (ikutRingkasan(activeCell)) {
                    ringkasan.tidakHadir += (isTidakHadir(activeCell) ? 1 : 0) - (sebelumnyaTidakHadir ? 1 : 0);
                }
                void activeCell.offsetWidth;
                activeCell.classList.add('saved');
                recalc();
                modal.close();
            }).catch(function () {
                error.textContent = __t('Tidak bisa terhubung ke server. Coba lagi.');
                error.hidden = false;
            }).finally(function () {
                submit.disabled = false;
                submit.textContent = __t('Simpan');
            });
        });
    })();
</script>
@endpush
