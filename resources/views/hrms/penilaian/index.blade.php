@extends('layouts.app')

@section('title', __('Penilaian Akhir Tahun'))

@section('styles')
    .pn-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .status-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: var(--glass); border: 1px solid #fff; font-size: 13px; color: var(--muted); }
    .chip b { color: var(--text); font-weight: 600; }
    .chip.active { background: var(--navy); color: rgba(255,255,255,.8); border-color: var(--navy); }
    .chip.active b { color: #fff; }
    .chip.todo b { color: #b91c1c; }
    .chip.todo.active b { color: #fff; }
    .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .filters input[type=search] { width: 220px; border-radius: 999px; }
    .filters input[type=number] { width: 110px; border-radius: 999px; padding: 8px 14px; border: 1px solid var(--line-strong); font: inherit; font-size: 14px; }
    .filters select { width: auto; min-width: 170px; border-radius: 999px; }
    td.nowrap, th.nowrap { white-space: nowrap; }
    td.num, th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .st { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .st-belum { background: #eef0f4; color: var(--muted); }
    .st-draft { background: #dbeafe; color: #1d4ed8; }
    .st-menunggu { background: #fef3c7; color: #92400e; }
    .st-disetujui { background: #dcfce7; color: #15803d; }
    .grade { display: inline-block; min-width: 28px; padding: 3px 8px; border-radius: 6px; text-align: center; font-weight: 700; background: var(--soft-2); }
    .flow { font-size: 12px; color: var(--muted); white-space: nowrap; }
    .flow b { color: var(--text); font-weight: 600; }
    tr.todo td { background: #fffbeb; }
    dialog.print-modal { width: min(920px, calc(100% - 32px)); max-height: calc(100vh - 32px); }
    .print-preview { position: relative; height: min(62vh, 820px); margin-top: 14px; border: 1px solid var(--line-strong); border-radius: 12px; overflow: hidden; background: #e5e7eb; }
    .print-preview iframe { width: 100%; height: 100%; border: 0; }
    .print-preview-loading { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 14px; background: #e5e7eb; }    .switch-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin: 0; padding: 12px 14px; border-radius: 12px; background: var(--soft-2); cursor: pointer; font-weight: 400; }
    .switch { position: relative; flex: none; width: 46px; height: 26px; }
    .switch input { position: absolute; inset: 0; width: 100%; height: 100%; margin: 0; opacity: 0; cursor: pointer; }
    .switch-slider { position: absolute; inset: 0; border-radius: 999px; background: var(--line-strong); transition: background .2s; pointer-events: none; }
    .switch-slider::after { content: ''; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .2s; }
    .switch input:checked + .switch-slider { background: var(--primary); }
    .switch input:checked + .switch-slider::after { transform: translateX(20px); }
    .switch input:focus-visible + .switch-slider { outline: 2px solid var(--primary); outline-offset: 2px; }
@endsection

@section('content')
@php
    $statusLabel = ['belum' => 'Belum dinilai'] + \App\Modules\Hrms\Models\PenilaianTahunan::STATUS;
    $keep = array_filter(['tahun' => $tahun, 'department' => $department, 'jenjang' => $jenjang, 'q' => $search]);
@endphp

<div class="pn-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ __('Penilaian Akhir Tahun') }} {{ $tahun }}</h1>
        <p class="muted" style="margin: 2px 0 0;">
            @if ($isAdmin)
                {{ __('Admin HRMS · melihat semua departemen') }}
            @else
                {{ __('Anda menilai sebagai') }} <b>{{ \App\Modules\Hrms\Support\JenjangPenilaian::label($userLevel) }}</b>
                @foreach ($departments as $option)
                    <span class="badge">{{ $option->name }}</span>
                @endforeach
            @endif
        </p>
    </div>
</div>

@if (! $isAdmin && $departments->isEmpty())
    <div class="alert">{{ __('Akun Anda belum terhubung ke departemen mana pun. Hubungi Admin HRMS.') }}</div>
@endif

<div class="status-chips">
    <a class="chip todo {{ $status === 'saya' ? 'active' : '' }}" href="{{ route('hrms.penilaian.index', [...$keep, 'status' => 'saya']) }}">{{ __('Perlu tindakan saya') }} <b>{{ $perluTindakan }}</b></a>
    <a class="chip {{ $status === '' ? 'active' : '' }}" href="{{ route('hrms.penilaian.index', $keep) }}">{{ __('Semua') }} <b>{{ $statusCounts->sum() }}</b></a>
    @foreach ($statusLabel as $key => $label)
        <a class="chip {{ $status === $key ? 'active' : '' }}" href="{{ route('hrms.penilaian.index', [...$keep, 'status' => $key]) }}">{{ $label }} <b>{{ $statusCounts[$key] ?? 0 }}</b></a>
    @endforeach
</div>

<form method="GET" class="filters">
    <input type="hidden" name="status" value="{{ $status }}">
    <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2100" aria-label="{{ __('Tahun') }}" onchange="this.form.requestSubmit()">
    @if ($isAdmin || $departments->count() > 1)
        <select name="department" onchange="this.form.requestSubmit()">
            <option value="">{{ __('Semua departemen') }}</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    @endif
    @if ($jenjangOptions->count() > 1)
        <select name="jenjang" onchange="this.form.requestSubmit()">
            <option value="">{{ __('Semua jabatan') }}</option>
            @foreach ($jenjangOptions as $option)
                <option value="{{ $option }}" @selected($jenjang === $option)>{{ \App\Modules\Hrms\Support\JenjangPenilaian::label($option) }}</option>
            @endforeach
        </select>
    @endif
    <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Cari NIK / nama...') }}">
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
                    <th>{{ __('Jabatan') }}</th>
                    <th>{{ __('Dept') }}</th>
                    <th class="num">{{ __('Nilai Absen') }}</th>
                    <th class="num">{{ __('Nilai Akhir') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Posisi') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php($karyawan = $row['karyawan'])
                    @php($penilaian = $row['penilaian'])
                    @php($url = route('hrms.penilaian.show', [$karyawan, 'tahun' => $tahun]))
                    <tr class="{{ $row['perlu_tindakan'] ? 'todo' : '' }}">
                        <td class="muted">{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="nowrap">{{ $karyawan->nik }}</td>
                        <td style="font-weight: 600;">{{ $karyawan->nama }}</td>
                        <td class="nowrap">{{ $karyawan->jabatan }}</td>
                        <td>{{ $karyawan->department?->name ?? '-' }}</td>
                        <td class="num">{{ number_format($row['nilai_absen'], 2, ',', '.') }}</td>
                        <td class="num">
                            @if ($penilaian && $penilaian->status !== 'draft')
                                {{ number_format($penilaian->nilai_akhir, 2, ',', '.') }} <span class="grade">{{ $penilaian->huruf }}</span>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td><span class="st st-{{ $row['status'] }}">{{ $statusLabel[$row['status']] }}</span></td>
                        <td class="flow">
                            @if ($row['tahapan'] === [])
                                <span class="badge badge-warn">{{ __('Belum ada akun penilai') }}</span>
                            @elseif ($row['status'] === 'disetujui')
                                {{ __('Selesai') }}
                            @else
                                {{ $row['status'] === 'menunggu' ? __('Approval') : __('Diisi') }}
                                <b>{{ \App\Modules\Hrms\Support\JenjangPenilaian::label($row['posisi']) }}</b>
                            @endif
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            @if ($row['perlu_tindakan'])
                                <a class="btn btn-sm" href="{{ $url }}">{{ $row['status'] === 'menunggu' ? __('Review') : __('Nilai') }}</a>
                            @else
                                <a class="btn btn-ghost btn-sm" href="{{ $url }}">{{ __('Lihat') }}</a>
                            @endif
                            {{-- Bisa dicetak setelah dikirim (minimal satu tanda tangan). --}}
                            @if (in_array($row['status'], ['menunggu', 'disetujui'], true))
                                <button type="button" class="btn btn-ghost btn-sm" title="{{ __('Cetak form penilaian (A4)') }}"
                                        data-print-url="{{ route('hrms.penilaian.print', [$karyawan, 'tahun' => $tahun]) }}" data-print-nama="{{ $karyawan->nik }} - {{ $karyawan->nama }}">{{ __('Print') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="muted" style="text-align: center; padding: 32px;">{{ __('Tidak ada karyawan yang bisa ditampilkan.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $rows->links() }}
<p class="muted small" style="margin-top: 12px;">
    {{ __('Nilai absen dihitung otomatis dari data absensi 1 Januari - 31 Desember :tahun. Selama penilaian belum dikirim, nilainya ikut berubah kalau data absensi berubah.', ['tahun' => $tahun]) }}
</p>

{{-- Pilihan sebelum cetak: default hanya centang, angka nilai baru tampil kalau toggle diaktifkan. --}}
<dialog class="modal print-modal" id="print-modal" aria-labelledby="print-modal-title">
    <div class="modal-head">
        <h2 id="print-modal-title">{{ __('Print Form Penilaian') }}</h2>
        <button type="button" class="modal-close" data-print-tutup aria-label="{{ __('Tutup') }}">&times;</button>
    </div>
    <div class="modal-body">
        <p class="muted" style="margin: 0 0 16px;" data-print-target></p>
        <label class="switch-row">
            <span>
                <b>{{ __('Tampilkan nilai') }}</b>
                <span class="muted small" style="display: block;">{{ __('Nonaktif: hanya centang penilai yang dicetak, angka nilai & huruf dikosongkan.') }}</span>
            </span>
            <span class="switch">
                <input type="checkbox" id="print-nilai" role="switch">
                <span class="switch-slider" aria-hidden="true"></span>
            </span>
        </label>
        {{-- Preview form cetak; ikut berubah saat toggle diubah, tombol Print mencetak isi preview ini. --}}
        <div class="print-preview">
            <div class="print-preview-loading" data-print-loading>{{ __('Memuat preview...') }}</div>
            <iframe title="{{ __('Preview Form Penilaian') }}" data-print-iframe></iframe>
        </div>
    </div>
    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-print-tutup>{{ __('Batal') }}</button>
        <button type="button" class="btn" data-print-buka>{{ __('Print') }}</button>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('print-modal');
        if (!modal) return;
        var toggle = document.getElementById('print-nilai');
        var iframe = modal.querySelector('[data-print-iframe]');
        var loading = modal.querySelector('[data-print-loading]');
        var tombolPrint = modal.querySelector('[data-print-buka]');
        var url = null;

        function muatPreview() {
            loading.hidden = false;
            tombolPrint.disabled = true;
            iframe.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'modal=1&nilai=' + (toggle.checked ? 1 : 0);
        }

        iframe.addEventListener('load', function () {
            if (!iframe.getAttribute('src')) return;
            loading.hidden = true;
            tombolPrint.disabled = false;
        });

        document.querySelectorAll('[data-print-url]').forEach(function (button) {
            button.addEventListener('click', function () {
                url = button.dataset.printUrl;
                modal.querySelector('[data-print-target]').textContent = button.dataset.printNama;
                toggle.checked = false;
                modal.showModal();
                muatPreview();
            });
        });

        toggle.addEventListener('change', muatPreview);

        modal.querySelectorAll('[data-print-tutup]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
        modal.addEventListener('close', function () { iframe.removeAttribute('src'); });

        // Cetak persis isi preview (dialog print browser untuk halaman di dalam preview).
        tombolPrint.addEventListener('click', function () {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
        });
    })();
</script>
@endpush
