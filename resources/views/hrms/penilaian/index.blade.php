@extends('layouts.app')

@section('title', 'Penilaian Akhir Tahun')

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
@endsection

@section('content')
@php
    $statusLabel = ['belum' => 'Belum dinilai'] + \App\Modules\Hrms\Models\PenilaianTahunan::STATUS;
    $keep = array_filter(['tahun' => $tahun, 'department' => $department, 'jenjang' => $jenjang, 'q' => $search]);
@endphp

<div class="pn-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Penilaian Akhir Tahun {{ $tahun }}</h1>
        <p class="muted" style="margin: 2px 0 0;">
            @if ($isAdmin)
                Admin HRMS · melihat semua departemen
            @else
                Anda menilai sebagai <b>{{ \App\Modules\Hrms\Support\JenjangPenilaian::label($userLevel) }}</b>
                @foreach ($departments as $option)
                    <span class="badge">{{ $option->name }}</span>
                @endforeach
            @endif
        </p>
    </div>
</div>

@if (! $isAdmin && $departments->isEmpty())
    <div class="alert">Akun Anda belum terhubung ke departemen mana pun. Hubungi Admin HRMS.</div>
@endif

<div class="status-chips">
    <a class="chip todo {{ $status === 'saya' ? 'active' : '' }}" href="{{ route('hrms.penilaian.index', [...$keep, 'status' => 'saya']) }}">Perlu tindakan saya <b>{{ $perluTindakan }}</b></a>
    <a class="chip {{ $status === '' ? 'active' : '' }}" href="{{ route('hrms.penilaian.index', $keep) }}">Semua <b>{{ $statusCounts->sum() }}</b></a>
    @foreach ($statusLabel as $key => $label)
        <a class="chip {{ $status === $key ? 'active' : '' }}" href="{{ route('hrms.penilaian.index', [...$keep, 'status' => $key]) }}">{{ $label }} <b>{{ $statusCounts[$key] ?? 0 }}</b></a>
    @endforeach
</div>

<form method="GET" class="filters">
    <input type="hidden" name="status" value="{{ $status }}">
    <input type="number" name="tahun" value="{{ $tahun }}" min="2000" max="2100" aria-label="Tahun" onchange="this.form.requestSubmit()">
    @if ($isAdmin || $departments->count() > 1)
        <select name="department" onchange="this.form.requestSubmit()">
            <option value="">Semua departemen</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    @endif
    @if ($jenjangOptions->count() > 1)
        <select name="jenjang" onchange="this.form.requestSubmit()">
            <option value="">Semua jabatan</option>
            @foreach ($jenjangOptions as $option)
                <option value="{{ $option }}" @selected($jenjang === $option)>{{ \App\Modules\Hrms\Support\JenjangPenilaian::label($option) }}</option>
            @endforeach
        </select>
    @endif
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari NIK / nama...">
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
                    <th>Jabatan</th>
                    <th>Dept</th>
                    <th class="num">Nilai Absen</th>
                    <th class="num">Nilai Akhir</th>
                    <th>Status</th>
                    <th>Posisi</th>
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
                                <span class="badge badge-warn">Belum ada akun penilai</span>
                            @elseif ($row['status'] === 'disetujui')
                                Selesai
                            @else
                                {{ $row['status'] === 'menunggu' ? 'Approval' : 'Diisi' }}
                                <b>{{ \App\Modules\Hrms\Support\JenjangPenilaian::label($row['posisi']) }}</b>
                            @endif
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            @if ($row['perlu_tindakan'])
                                <a class="btn btn-sm" href="{{ $url }}">{{ $row['status'] === 'menunggu' ? 'Review' : 'Nilai' }}</a>
                            @else
                                <a class="btn btn-ghost btn-sm" href="{{ $url }}">Lihat</a>
                                @if ($row['status'] === 'disetujui')
                                    <a class="btn btn-ghost btn-sm" href="{{ route('hrms.penilaian.print', [$karyawan, 'tahun' => $tahun]) }}" target="_blank" rel="noopener" title="Cetak form penilaian (A4)">Print</a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="muted" style="text-align: center; padding: 32px;">Tidak ada karyawan yang bisa ditampilkan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $rows->links() }}
<p class="muted small" style="margin-top: 12px;">
    Nilai absen dihitung otomatis dari data absensi 1 Januari - 31 Desember {{ $tahun }}. Selama penilaian belum dikirim, nilainya ikut berubah kalau data absensi berubah.
</p>
@endsection
