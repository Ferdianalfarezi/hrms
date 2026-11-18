@extends('layouts.base')
@use('App\Modules\Hrms\Models\PenilaianTahunan')
@use('App\Modules\Hrms\Models\PenilaianTahunanLog')
@use('App\Modules\Hrms\Support\JenjangPenilaian')

@section('title', __('Verifikasi Tanda Tangan'))

@section('styles')
    .verif-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
    .verif-card { width: min(520px, 100%); }
    .verif-status { display: flex; gap: 14px; align-items: center; padding: 16px; border-radius: 14px; margin-bottom: 18px; }
    .verif-status.sah { background: #dcfce7; color: #14532d; }
    .verif-status.tidak { background: #fee2e2; color: #7f1d1d; }
    .verif-status svg { flex: none; }
    .verif-status h1 { font-size: 18px; font-weight: 600; margin: 0; }
    .verif-status p { margin: 2px 0 0; font-size: 13px; }
    .verif-list { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 8px 16px; margin: 0; font-size: 14px; }
    .verif-list dt { color: var(--muted); }
    .verif-list dd { margin: 0; font-weight: 500; overflow-wrap: anywhere; }
    .verif-foot { margin-top: 18px; font-size: 12px; color: var(--muted); text-align: center; }
@endsection

@section('body')
<div class="verif-wrap">
    <div class="card verif-card">
        @if ($log)
            @php
                $penilaian = $log->penilaian;
                $karyawan = $penilaian->karyawan;
                $tahapLevel = $penilaian->tahapan[$log->tahap] ?? $log->level;
            @endphp
            <div class="verif-status sah">
                <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m22 4-10 10.01-3-3"/></svg>
                <div>
                    <h1>{{ __('Tanda tangan elektronik sah') }}</h1>
                    <p>{{ __('Ditandatangani oleh :nama pada :waktu.', ['nama' => $log->user?->name ?? '-', 'waktu' => $log->created_at->translatedFormat('d F Y H:i')]) }}</p>
                </div>
            </div>

            <dl class="verif-list">
                <dt>{{ __('Dokumen') }}</dt>
                <dd>{{ __('Penilaian Akhir Tahun :tahun', ['tahun' => $penilaian->tahun]) }}</dd>
                <dt>{{ __('Karyawan dinilai') }}</dt>
                <dd>{{ $karyawan?->nama ?? '-' }} ({{ $karyawan?->nik ?? '-' }})</dd>
                <dt>{{ __('Departemen') }}</dt>
                <dd>{{ $penilaian->department?->name ?? '-' }}</dd>
                <dt>{{ __('Ditandatangani oleh') }}</dt>
                <dd>{{ $log->user?->name ?? '-' }} @if ($log->user)<span class="muted">({{ $log->user->nik }})</span>@endif</dd>
                <dt>{{ __('Jabatan') }}</dt>
                <dd>{{ JenjangPenilaian::label($log->level) }}</dd>
                <dt>{{ __('Sebagai') }}</dt>
                <dd>
                    {{ __(PenilaianTahunanLog::AKSI[$log->aksi]) }} · {{ __('tahap :tahap', ['tahap' => JenjangPenilaian::label($tahapLevel)]) }}
                </dd>
                <dt>{{ __('Tanggal') }}</dt>
                <dd>{{ $log->created_at->translatedFormat('l, d F Y · H:i') }}</dd>
                <dt>{{ __('Kode token penilai') }}</dt>
                <dd><code>{{ $log->user?->token_kode ?? '-' }}</code></dd>
                <dt>{{ __('Status penilaian') }}</dt>
                <dd>{{ $penilaian->status === PenilaianTahunan::STATUS_DISETUJUI ? __('Selesai, disetujui semua tahap') : __('Masih dalam proses approval') }}</dd>
            </dl>
        @else
            <div class="verif-status tidak">
                <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                <div>
                    <h1>{{ __('Tanda tangan tidak dikenali') }}</h1>
                    <p>{{ __('Kode verifikasi ini tidak terdaftar di HR System. Dokumen mungkin tidak asli.') }}</p>
                </div>
            </div>
        @endif

        <div class="verif-foot">{{ __('Diverifikasi oleh HR System · :waktu', ['waktu' => now()->translatedFormat('d M Y H:i')]) }}</div>
    </div>
</div>
@endsection
