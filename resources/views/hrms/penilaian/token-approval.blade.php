@extends('layouts.app')

@section('title', __('Approval Token Penilaian'))

@section('styles')
    .status-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: var(--glass); border: 1px solid #fff; font-size: 13px; color: var(--muted); }
    .chip b { color: var(--text); font-weight: 600; }
    .chip.active { background: var(--navy); color: rgba(255,255,255,.8); border-color: var(--navy); }
    .chip.active b { color: #fff; }
    .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .filters input[type=search] { width: 260px; border-radius: 999px; }
    td.nowrap { white-space: nowrap; }
    .ap-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .ap-amber { background: #fef3c7; color: #92400e; }
    .ap-green { background: #dcfce7; color: #15803d; }
    .ap-red { background: #fee2e2; color: #b91c1c; }
    .actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: nowrap; }
    .actions form { margin: 0; }
    .btn-success { background: var(--success); }
    .jumlah { font-size: 18px; font-weight: 600; }
    .alasan { white-space: pre-line; font-size: 13px; max-width: 360px; }
@endsection

@section('content')
@php($chips = ['pending' => __('Menunggu'), 'approved' => __('Disetujui'), 'rejected' => __('Ditolak'), '' => __('Semua')])

<div style="margin-bottom: 16px;">
    <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ __('Approval Token Penilaian') }}</h1>
    <p class="muted" style="margin: 2px 0 0;">{{ __('Pengajuan penambahan token dari penilai. Token baru bertambah setelah disetujui.') }}</p>
</div>

@error('approval')
    <div class="alert">{{ $message }}</div>
@enderror
@error('catatan_review')
    <div class="alert">{{ $message }}</div>
@enderror

<div class="status-chips">
    @foreach ($chips as $value => $label)
        <a href="{{ route('hrms.penilaian.token.approval', array_filter(['status' => $value === '' ? 'semua' : $value, 'q' => $search])) }}" @class(['chip', 'active' => $status === $value])>
            {{ $label }} <b>{{ $value === '' ? $statusCounts->sum() : ($statusCounts[$value] ?? 0) }}</b>
        </a>
    @endforeach
</div>

<form method="GET" class="filters">
    <input type="hidden" name="status" value="{{ $status === '' ? 'semua' : $status }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Cari NIK / nama penilai...') }}">
    <button type="submit" class="btn btn-ghost">{{ __('Cari') }}</button>
    @if ($search !== '')
        <a href="{{ route('hrms.penilaian.token.approval', ['status' => $status === '' ? 'semua' : $status]) }}" class="muted small">{{ __('Reset') }}</a>
    @endif
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>{{ __('No') }}</th>
                    <th class="nowrap">{{ __('Diajukan') }}</th>
                    <th>{{ __('Penilai') }}</th>
                    <th class="nowrap">{{ __('Sisa Token') }}</th>
                    <th class="nowrap">{{ __('Jumlah Diajukan') }}</th>
                    <th>{{ __('Alasan') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th style="text-align: right;">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $tokenRequest)
                    @php($penilai = $tokenRequest->user)
                    <tr>
                        <td class="muted">{{ $requests->firstItem() + $loop->index }}</td>
                        <td class="nowrap small">{{ $tokenRequest->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td>
                            <div style="font-weight: 600;">{{ $penilai->name }}</div>
                            <div class="small muted">{{ $penilai->nik }} · {{ \App\Modules\Hrms\Support\JenjangPenilaian::userJabatan($penilai) ?? '-' }} · {{ $penilai->token_kode }}</div>
                        </td>
                        <td class="nowrap">{{ $penilai->token_penilaian }}</td>
                        <td class="nowrap"><span class="jumlah">+{{ $tokenRequest->jumlah }}</span></td>
                        <td>
                            <div class="alasan">{{ $tokenRequest->alasan }}</div>
                            @if ($tokenRequest->reviewed_at)
                                <div class="small muted" style="margin-top: 4px;">
                                    {{ __('Diputuskan :nama · :waktu', ['nama' => $tokenRequest->reviewer?->name ?? '-', 'waktu' => $tokenRequest->reviewed_at->translatedFormat('d M Y H:i')]) }}
                                    @if ($tokenRequest->catatan_review)
                                        <br>{{ $tokenRequest->catatan_review }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td><span class="ap-badge ap-{{ $tokenRequest->statusTone() }}">{{ $tokenRequest->statusLabel() }}</span></td>
                        <td>
                            @if ($tokenRequest->isPending())
                                <div class="actions">
                                    <form method="POST" action="{{ route('hrms.penilaian.token.approve', $tokenRequest) }}" data-confirm="{{ __('Setujui penambahan :jumlah token untuk :nama?', ['jumlah' => $tokenRequest->jumlah, 'nama' => $penilai->name]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">{{ __('Setuju') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('hrms.penilaian.token.reject', $tokenRequest) }}" data-confirm="{{ __('Tolak pengajuan token :nama?', ['nama' => $penilai->name]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger">{{ __('Tidak Setuju') }}</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted" style="text-align: center; padding: 24px;">{{ __('Tidak ada pengajuan token.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $requests->links() }}
@endsection
