@extends('layouts.app')

@section('title', __('Potongan Absen'))

@section('styles')
    .pt-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .filters input[type=search] { width: 220px; border-radius: 999px; }
    .filters input[type=month] { width: auto; border-radius: 999px; padding: 8px 14px; }
    .filters select { width: auto; min-width: 170px; border-radius: 999px; }
    .filters .check { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); cursor: pointer; }
    .filters .check input { width: auto; }
    .month-nav { display: inline-flex; border: 1px solid var(--line-strong); border-radius: 999px; overflow: hidden; background: var(--glass-strong); }
    .month-nav a { padding: 9px 14px; font-size: 13px; font-weight: 600; color: var(--text); }
    .month-nav a + a { border-left: 1px solid var(--line-strong); }
    .month-nav a:hover { background: #fff; }
    .summary { display: flex; gap: 8px; flex-wrap: wrap; }
    .summary .chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 13px; white-space: nowrap; background: var(--glass-strong); }
    .summary .chip.total { background: #fee2e2; color: #b91c1c; }
    td.nowrap, th.nowrap { white-space: nowrap; }
    td.num, th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    td.money { font-weight: 700; color: #b91c1c; }
    tfoot td { font-weight: 700; border-top: 2px solid var(--line-strong); }
    .k { font-weight: 700; border-radius: 6px; }
    .k-orange { background: #ffedd5; color: #c2410c; }
    .k-tag { display: inline-block; min-width: 40px; padding: 3px 8px; text-align: center; font-size: 12px; font-style: normal; }
    dialog.modal.wide { width: min(760px, calc(100% - 32px)); }
@endsection

@section('content')
@php
    $keep = array_filter(['department' => $department, 'q' => $search, 'semua' => $showAll ? 1 : null]);
    $menitPerBlok = \App\Modules\Hrms\Models\Absensi::MENIT_PER_POTONGAN;
    $durasi = fn (int $menit) => $menit >= 60 ? intdiv($menit, 60).' jam'.($menit % 60 ? ' '.($menit % 60).' mnt' : '') : $menit.' mnt';
@endphp

<div class="pt-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ __('Potongan Absen') }}</h1>
        <p class="muted" style="margin: 2px 0 0;">
            {{ __('Durasi datang terlambat') }} · {{ $bulan->translatedFormat('F Y') }}
            @if ($lockedDepartments->isNotEmpty())
                {{ __('· departemen') }}
                @foreach ($lockedDepartments as $lockedDepartment)
                    <span class="badge">{{ $lockedDepartment->name }}</span>
                @endforeach
            @endif
        </p>
    </div>
    <div class="summary">
        <span class="chip">{{ __(':count karyawan terlambat', ['count' => $rows->where('jumlah', '>', 0)->count()]) }}</span>
        <span class="chip">{{ $rows->sum('jumlah') }}x DT · {{ __(':countx kelipatan 30 mnt', ['count' => $rows->sum('blok')]) }}</span>
        <span class="chip total">{{ __('Total dihitung') }} <b>{{ $durasi($rows->sum('blok') * $menitPerBlok) }}</b></span>
    </div>
</div>

@if ($missingDepartment)
    <div class="alert">{{ __('Akun Anda adalah Admin Departemen tetapi belum dipilihkan departemen. Hubungi Super Admin.') }}</div>
@endif

<form method="GET" class="filters">
    <span class="month-nav">
        <a href="{{ route('hrms.absensi.potongan', [...$keep, 'bulan' => $bulan->copy()->subMonthNoOverflow()->format('Y-m')]) }}" title="{{ __('Bulan sebelumnya') }}">&lsaquo;</a>
        <a href="{{ route('hrms.absensi.potongan', $keep) }}">{{ __('Bulan ini') }}</a>
        <a href="{{ route('hrms.absensi.potongan', [...$keep, 'bulan' => $bulan->copy()->addMonthNoOverflow()->format('Y-m')]) }}" title="{{ __('Bulan berikutnya') }}">&rsaquo;</a>
    </span>
    <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" onchange="this.form.requestSubmit()" aria-label="{{ __('Bulan') }}">
    @if ($canFilterDepartment)
        <select name="department" onchange="this.form.requestSubmit()">
            <option value="">{{ __('Semua departemen') }}</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
            <option value="none" @selected($department === 'none')>{{ __('Tanpa departemen') }}</option>
        </select>
    @endif
    <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Cari NIK / nama...') }}">
    <label class="check"><input type="checkbox" name="semua" value="1" @checked($showAll) onchange="this.form.requestSubmit()"> {{ __('Tampilkan semua karyawan') }}</label>
    <button type="submit" class="btn btn-ghost">{{ __('Cari') }}</button>
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>{{ __('No') }}</th>
                    <th class="nowrap">NIK</th>
                    <th>{{ __('Nama') }}</th>
                    <th>{{ __('Dept') }}</th>
                    <th class="num">{{ __('Jumlah DT') }}</th>
                    <th class="num">{{ __('Total Menit') }}</th>
                    <th class="num" title="{{ __('Kelipatan 30 menit, dibulatkan ke atas per kejadian') }}">{{ __('Kelipatan 30 mnt') }}</th>
                    <th class="num" title="{{ __('Kelipatan 30 menit x 30, mis. telat 1 menit = 30 menit, 31 menit = 1 jam') }}">{{ __('Durasi Dihitung') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($karyawan = $row['karyawan'])
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td class="nowrap">{{ $karyawan->nik }}</td>
                        <td>
                            <span style="font-weight: 600;">{{ $karyawan->nama }}</span>
                            @if ($row['belumDiisi'])
                                <span class="badge badge-warn" title="{{ __('DT tanpa menit belum ikut dihitung') }}">{{ __(':count DT belum diisi menit', ['count' => $row['belumDiisi']]) }}</span>
                            @endif
                            @if ($row['pending'])
                                <span class="badge badge-warn" title="{{ __('Ada pengajuan perubahan yang menunggu approval HRD') }}">{{ __(':count menunggu approval', ['count' => $row['pending']]) }}</span>
                            @endif
                        </td>
                        <td>{{ $karyawan->department?->name ?? '-' }}</td>
                        <td class="num">{{ $row['jumlah'] }}</td>
                        <td class="num">{{ number_format($row['menit'], 0, ',', '.') }}</td>
                        <td class="num">{{ $row['blok'] }}</td>
                        <td class="num money">{{ $durasi($row['blok'] * $menitPerBlok) }}</td>
                        <td style="text-align: right;">
                            @if ($row['jumlah'])
                                <button type="button" class="btn btn-ghost btn-sm" data-detail="{{ $karyawan->id }}">{{ __('Detail') }}</button>
                                <template id="detail-{{ $karyawan->id }}">
                                    <p class="muted small" style="margin: 0 0 12px;">
                                        {{ $karyawan->nik }} - {{ $karyawan->nama }} · {{ $karyawan->department?->name ?? '-' }}
                                    </p>
                                    <div class="table-wrap">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>{{ __('Tanggal') }}</th>
                                                    <th>{{ __('Kode') }}</th>
                                                    <th class="num">{{ __('Menit') }}</th>
                                                    <th class="num">{{ __('Kelipatan') }}</th>
                                                    <th>{{ __('Keterangan') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($row['items'] as $item)
                                                    <tr>
                                                        <td class="nowrap">{{ $item['tanggal'] }}</td>
                                                        <td class="nowrap"><i class="k k-tag k-orange">{{ $item['kode'] }}</i></td>
                                                        <td class="num">{{ $item['menit'] ?? '-' }}</td>
                                                        <td class="num">{{ $item['blok'] }}</td>
                                                        <td class="small">
                                                            {{ $item['keterangan'] ?: '-' }}
                                                            @if ($item['menit'] === null)
                                                                <span class="badge badge-warn">{{ __('menit belum diisi') }}</span>
                                                            @endif
                                                            @if ($item['pending'])
                                                                <span class="badge badge-warn">{{ __('menunggu approval') }}</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="2">{{ __('Total') }}</td>
                                                    <td class="num">{{ $row['menit'] }}</td>
                                                    <td class="num">{{ $row['blok'] }}</td>
                                                    <td class="money">{{ $durasi($row['blok'] * $menitPerBlok) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </template>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted" style="text-align: center; padding: 32px;">
                            {{ __('Tidak ada karyawan yang datang terlambat di :bulan.', ['bulan' => $bulan->translatedFormat('F Y')]) }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="4">{{ __('Total') }}</td>
                        <td class="num">{{ $rows->sum('jumlah') }}</td>
                        <td class="num">{{ number_format($rows->sum('menit'), 0, ',', '.') }}</td>
                        <td class="num">{{ $rows->sum('blok') }}</td>
                        <td class="num money">{{ $durasi($rows->sum('blok') * $menitPerBlok) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
<p class="muted small" style="margin-top: 12px;">
    {{ __('Kelipatan 30 menit dibulatkan ke atas per kejadian, mis. telat 1 - 30 menit = 1x (30 menit), 31 - 60 menit = 2x (1 jam). DT< yang diinput manual otomatis 30 menit. DT dari upload Komparasi Mesin dihitung dari Time In (acuan pagi 07:00, malam 20:05).') }}
</p>

<dialog class="modal wide" id="detail-modal">
    <div class="modal-head">
        <h2>{{ __('Detail Keterlambatan') }}</h2>
        <button type="button" class="modal-close" data-close aria-label="{{ __('Tutup') }}">&times;</button>
    </div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close>{{ __('Tutup') }}</button>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('detail-modal');
        var body = document.getElementById('detail-body');
        document.querySelectorAll('[data-detail]').forEach(function (button) {
            button.addEventListener('click', function () {
                body.innerHTML = '';
                body.appendChild(document.getElementById('detail-' + button.dataset.detail).content.cloneNode(true));
                modal.showModal();
            });
        });
        modal.querySelectorAll('[data-close]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
        modal.addEventListener('click', function (event) { if (event.target === modal) modal.close(); });
    })();
</script>
@endpush
