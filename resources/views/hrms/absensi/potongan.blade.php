@extends('layouts.app')

@section('title', 'Potongan Absen')

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
    $rupiah = fn (int $value) => 'Rp '.number_format($value, 0, ',', '.');
    $keep = array_filter(['department' => $department, 'q' => $search, 'semua' => $showAll ? 1 : null]);
    $totalPotongan = $rows->sum('potongan');
@endphp

<div class="pt-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Potongan Absen</h1>
        <p class="muted" style="margin: 2px 0 0;">
            Potongan gaji datang terlambat · {{ $bulan->translatedFormat('F Y') }}
            @if ($lockedDepartments->isNotEmpty())
                · departemen
                @foreach ($lockedDepartments as $lockedDepartment)
                    <span class="badge">{{ $lockedDepartment->name }}</span>
                @endforeach
            @endif
        </p>
    </div>
    <div class="summary">
        <span class="chip">{{ $rows->where('jumlah', '>', 0)->count() }} karyawan terlambat</span>
        <span class="chip">{{ $rows->sum('jumlah') }}x DT · {{ number_format($rows->sum('menit'), 0, ',', '.') }} menit</span>
        <span class="chip total">Total potongan <b>{{ $rupiah($totalPotongan) }}</b></span>
    </div>
</div>

@if ($missingDepartment)
    <div class="alert">Akun Anda adalah Admin Departemen tetapi belum dipilihkan departemen. Hubungi Super Admin.</div>
@endif

<form method="GET" class="filters">
    <span class="month-nav">
        <a href="{{ route('hrms.absensi.potongan', [...$keep, 'bulan' => $bulan->copy()->subMonthNoOverflow()->format('Y-m')]) }}" title="Bulan sebelumnya">&lsaquo;</a>
        <a href="{{ route('hrms.absensi.potongan', $keep) }}">Bulan ini</a>
        <a href="{{ route('hrms.absensi.potongan', [...$keep, 'bulan' => $bulan->copy()->addMonthNoOverflow()->format('Y-m')]) }}" title="Bulan berikutnya">&rsaquo;</a>
    </span>
    <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" onchange="this.form.requestSubmit()" aria-label="Bulan">
    @if ($canFilterDepartment)
        <select name="department" onchange="this.form.requestSubmit()">
            <option value="">Semua departemen</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
            <option value="none" @selected($department === 'none')>Tanpa departemen</option>
        </select>
    @endif
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari NIK / nama...">
    <label class="check"><input type="checkbox" name="semua" value="1" @checked($showAll) onchange="this.form.requestSubmit()"> Tampilkan semua karyawan</label>
    <button type="submit" class="btn btn-ghost">Cari</button>
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th class="nowrap">NIK</th>
                    <th>Nama</th>
                    <th>Dept</th>
                    <th class="num">Potongan / 30 mnt</th>
                    <th class="num">Jumlah DT</th>
                    <th class="num">Total Menit</th>
                    <th class="num" title="Kelipatan 30 menit, dibulatkan ke atas per kejadian">Kelipatan 30 mnt</th>
                    <th class="num">Total Potongan</th>
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
                                <span class="badge badge-warn" title="DT tanpa menit belum ikut dihitung">{{ $row['belumDiisi'] }} DT belum diisi menit</span>
                            @endif
                            @if ($row['pending'])
                                <span class="badge badge-warn" title="Ada pengajuan perubahan yang menunggu approval HRD">{{ $row['pending'] }} menunggu approval</span>
                            @endif
                        </td>
                        <td>{{ $karyawan->department?->name ?? '-' }}</td>
                        <td class="num">{{ $rupiah($karyawan->potongan_absen) }}</td>
                        <td class="num">{{ $row['jumlah'] }}</td>
                        <td class="num">{{ number_format($row['menit'], 0, ',', '.') }}</td>
                        <td class="num">{{ $row['blok'] }}</td>
                        <td class="num money">{{ $rupiah($row['potongan']) }}</td>
                        <td style="text-align: right;">
                            @if ($row['jumlah'])
                                <button type="button" class="btn btn-ghost btn-sm" data-detail="{{ $karyawan->id }}">Detail</button>
                                <template id="detail-{{ $karyawan->id }}">
                                    <p class="muted small" style="margin: 0 0 12px;">
                                        {{ $karyawan->nik }} - {{ $karyawan->nama }} · {{ $karyawan->department?->name ?? '-' }} · {{ $rupiah($karyawan->potongan_absen) }} per 30 menit
                                    </p>
                                    <div class="table-wrap">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Kode</th>
                                                    <th class="num">Menit</th>
                                                    <th class="num">Kelipatan</th>
                                                    <th class="num">Potongan</th>
                                                    <th>Keterangan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($row['items'] as $item)
                                                    <tr>
                                                        <td class="nowrap">{{ $item['tanggal'] }}</td>
                                                        <td class="nowrap"><i class="k k-tag k-orange">{{ $item['kode'] }}</i></td>
                                                        <td class="num">{{ $item['menit'] ?? '-' }}</td>
                                                        <td class="num">{{ $item['blok'] }}</td>
                                                        <td class="num">{{ $rupiah($item['potongan']) }}</td>
                                                        <td class="small">
                                                            {{ $item['keterangan'] ?: '-' }}
                                                            @if ($item['menit'] === null)
                                                                <span class="badge badge-warn">menit belum diisi</span>
                                                            @endif
                                                            @if ($item['pending'])
                                                                <span class="badge badge-warn">menunggu approval</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="2">Total</td>
                                                    <td class="num">{{ $row['menit'] }}</td>
                                                    <td class="num">{{ $row['blok'] }}</td>
                                                    <td class="num money">{{ $rupiah($row['potongan']) }}</td>
                                                    <td></td>
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
                        <td colspan="10" class="muted" style="text-align: center; padding: 32px;">
                            Tidak ada karyawan yang datang terlambat di {{ $bulan->translatedFormat('F Y') }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr>
                        <td colspan="5">Total</td>
                        <td class="num">{{ $rows->sum('jumlah') }}</td>
                        <td class="num">{{ number_format($rows->sum('menit'), 0, ',', '.') }}</td>
                        <td class="num">{{ $rows->sum('blok') }}</td>
                        <td class="num money">{{ $rupiah($totalPotongan) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
<p class="muted small" style="margin-top: 12px;">
    Potongan = kelipatan 30 menit (dibulatkan ke atas per kejadian, mis. 10 menit = 1x, 45 menit = 2x) &times; potongan absen per 30 menit karyawan.
    Nilai potongan per karyawan diatur di menu Karyawan.
</p>

<dialog class="modal wide" id="detail-modal">
    <div class="modal-head">
        <h2>Detail Potongan</h2>
        <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close>Tutup</button>
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
