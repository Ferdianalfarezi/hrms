@extends('layouts.app')

@section('title', 'Approval Absensi')

@section('styles')
    .ap-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .status-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: var(--glass); border: 1px solid #fff; font-size: 13px; color: var(--muted); }
    .chip b { color: var(--text); font-weight: 600; }
    .chip.active { background: var(--navy); color: rgba(255,255,255,.8); border-color: var(--navy); }
    .chip.active b { color: #fff; }
    .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .filters input[type=search] { width: 260px; border-radius: 999px; }
    td.nowrap { white-space: nowrap; }
    .k { font-weight: 700; border-radius: 6px; }
    .k-red { background: #fee2e2; color: #b91c1c; }
    .k-orange { background: #ffedd5; color: #c2410c; }
    .k-blue { background: #dbeafe; color: #1d4ed8; }
    .k-green { background: #dcfce7; color: #15803d; }
    .k-gray { background: #eef0f4; color: var(--muted); }
    .k-tag { display: inline-block; min-width: 40px; padding: 3px 8px; text-align: center; font-size: 12px; font-style: normal; }
    .arrow { color: var(--muted); margin: 0 4px; }
    .ap-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .ap-amber { background: #fef3c7; color: #92400e; }
    .ap-green { background: #dcfce7; color: #15803d; }
    .ap-red { background: #fee2e2; color: #b91c1c; }
    .actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: nowrap; }
    .actions form { margin: 0; }
    .btn-success { background: var(--success); }
    dialog.modal.wide { width: min(760px, calc(100% - 32px)); }
    .compare { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .compare > div { padding: 12px 14px; border-radius: 12px; background: var(--soft-2); }
    .compare .after { background: #eef4ff; }
    .compare h4 { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
    .compare dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; font-size: 13px; }
    .compare dt { color: var(--muted); }
    .compare dd { margin: 0; font-weight: 500; }
    .detail-section { margin-top: 16px; }
    .detail-section h4 { margin: 0 0 6px; font-size: 13px; }
    .detail-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px 16px; font-size: 13px; margin-bottom: 16px; }
    .detail-meta span { display: block; color: var(--muted); font-size: 12px; }
    .alasan { white-space: pre-line; padding: 10px 12px; border-radius: 10px; background: var(--soft-2); font-size: 13px; }
    .evidence-frame { width: 100%; height: 420px; border: 1px solid var(--line-strong); border-radius: 12px; background: #fff; }
    .evidence-img { display: block; max-width: 100%; max-height: 420px; margin: 0 auto; border-radius: 12px; border: 1px solid var(--line-strong); }
    .evidence-empty { padding: 18px; text-align: center; border: 1px dashed var(--line-strong); border-radius: 12px; color: var(--muted); font-size: 13px; }
    textarea { width: 100%; padding: 11px 14px; font: inherit; font-size: 14px; border: 1px solid var(--line-strong); border-radius: 12px; resize: vertical; }
    @media (max-width: 640px) { .compare { grid-template-columns: 1fr; } }
@endsection

@section('content')
@php
    $tag = function (?string $kode) use ($kodes) {
        if ($kode === null) {
            return '<i class="k k-tag k-gray">Hapus</i>';
        }

        return '<i class="k k-tag k-'.e($kodes[$kode]['tone'] ?? 'blue').'" title="'.e($kodes[$kode]['label'] ?? $kode).'">'.e($kode).'</i>';
    };
    $chips = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', '' => 'Semua'];
@endphp

<div class="ap-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Approval Absensi</h1>
        <p class="muted" style="margin: 2px 0 0;">Pengajuan perubahan absensi dari Admin Departemen. Absensi baru berubah setelah disetujui.</p>
    </div>
</div>

@error('approval')
    <div class="alert">{{ $message }}</div>
@enderror
@error('catatan_review')
    <div class="alert">{{ $message }}</div>
@enderror

<div class="status-chips">
    @foreach ($chips as $value => $label)
        <a href="{{ route('hrms.absensi.approval', array_filter(['status' => $value === '' ? 'semua' : $value, 'q' => $search])) }}" @class(['chip', 'active' => $status === $value])>
            {{ $label }} <b>{{ $value === '' ? $statusCounts->sum() : ($statusCounts[$value] ?? 0) }}</b>
        </a>
    @endforeach
</div>

<form method="GET" class="filters">
    <input type="hidden" name="status" value="{{ $status === '' ? 'semua' : $status }}">
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari NIK / nama karyawan...">
    <button type="submit" class="btn btn-ghost">Cari</button>
    @if ($search !== '')
        <a href="{{ route('hrms.absensi.approval', ['status' => $status === '' ? 'semua' : $status]) }}" class="muted small">Reset</a>
    @endif
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th class="nowrap">Diajukan</th>
                    <th>Karyawan</th>
                    <th class="nowrap">Tgl Absensi</th>
                    <th>Perubahan</th>
                    <th>Evidence</th>
                    <th class="nowrap">Diajukan oleh</th>
                    <th>Status</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($approvals as $approval)
                    @php($karyawan = $approval->karyawan)
                    <tr>
                        <td class="muted">{{ $approvals->firstItem() + $loop->index }}</td>
                        <td class="nowrap small">{{ $approval->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td>
                            <div style="font-weight: 600;">{{ $karyawan->nama }}</div>
                            <div class="small muted">{{ $karyawan->nik }} · {{ $karyawan->department?->name ?? 'Tanpa departemen' }}</div>
                        </td>
                        <td class="nowrap">
                            {{ $approval->tanggal_lama->translatedFormat('D, d M Y') }}
                            @if (! $approval->tanggal_lama->isSameDay($approval->tanggal_baru))
                                <div class="small muted">&rarr; {{ $approval->tanggal_baru->translatedFormat('D, d M Y') }}</div>
                            @endif
                        </td>
                        <td class="nowrap">
                            {!! $tag($approval->kode_lama) !!}@if ($approval->menit_lama)<span class="small muted"> {{ $approval->menit_lama }}m</span>@endif
                            <span class="arrow">&rarr;</span>
                            {!! $tag($approval->kode_baru) !!}@if ($approval->menit_baru)<span class="small muted"> {{ $approval->menit_baru }}m</span>@endif
                        </td>
                        <td class="nowrap small">
                            @if ($approval->evidence_path)
                                <span class="badge">Ada lampiran</span>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                        <td class="small muted">{{ $approval->requester?->name ?? '-' }}</td>
                        <td><span class="ap-badge ap-{{ $approval->statusTone() }}">{{ $approval->statusLabel() }}</span></td>
                        <td>
                            <div class="actions">
                                <button type="button" class="btn btn-ghost btn-sm" data-detail="{{ $approval->id }}">Detail</button>
                                @if ($approval->isPending())
                                    <form method="POST" action="{{ route('hrms.absensi.approval.approve', $approval) }}" data-confirm="Setujui perubahan absensi {{ $karyawan->nama }}?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">Setuju</button>
                                    </form>
                                    <form method="POST" action="{{ route('hrms.absensi.approval.reject', $approval) }}" data-confirm="Tolak perubahan absensi {{ $karyawan->nama }}?">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger">Tidak Setuju</button>
                                    </form>
                                @endif
                            </div>

                            {{-- Isi modal detail; disalin ke modal saat tombol Detail diklik. --}}
                            <template id="detail-{{ $approval->id }}">
                                <div class="detail-meta">
                                    <div><span>Karyawan</span>{{ $karyawan->nik }} - {{ $karyawan->nama }}</div>
                                    <div><span>Departemen / Group</span>{{ $karyawan->department?->name ?? '-' }} / {{ $karyawan->group ?? '-' }}</div>
                                    <div><span>Diajukan oleh</span>{{ $approval->requester?->name ?? '-' }} · {{ $approval->created_at->translatedFormat('d M Y H:i') }}</div>
                                    <div><span>Status</span><span class="ap-badge ap-{{ $approval->statusTone() }}" style="display: inline-block; color: inherit;">{{ $approval->statusLabel() }}</span></div>
                                </div>
                                <div class="compare">
                                    <div>
                                        <h4>Sebelum</h4>
                                        <dl>
                                            <dt>Tanggal</dt><dd>{{ $approval->tanggal_lama->translatedFormat('l, d M Y') }}</dd>
                                            <dt>Kode</dt><dd>{!! $tag($approval->kode_lama) !!} {{ $kodes[$approval->kode_lama]['label'] ?? '' }}</dd>
                                            @if ($approval->menit_lama)
                                                <dt>Terlambat</dt><dd>{{ $approval->menit_lama }} menit</dd>
                                            @endif
                                            <dt>Keterangan</dt><dd>{{ $approval->keterangan_lama ?: '-' }}</dd>
                                        </dl>
                                    </div>
                                    <div class="after">
                                        <h4>Diajukan</h4>
                                        @if ($approval->isDeletion())
                                            <div style="font-size: 13px;">Data absensi <strong>dihapus</strong> — karyawan dianggap <strong>masuk</strong>.</div>
                                        @else
                                            <dl>
                                                <dt>Tanggal</dt><dd>{{ $approval->tanggal_baru->translatedFormat('l, d M Y') }}</dd>
                                                <dt>Kode</dt><dd>{!! $tag($approval->kode_baru) !!} {{ $kodes[$approval->kode_baru]['label'] ?? '' }}</dd>
                                                @if ($approval->menit_baru)
                                                    <dt>Terlambat</dt><dd>{{ $approval->menit_baru }} menit</dd>
                                                @endif
                                                <dt>Keterangan</dt><dd>{{ $approval->keterangan_baru ?: '-' }}</dd>
                                            </dl>
                                        @endif
                                    </div>
                                </div>
                                <div class="detail-section">
                                    <h4>Alasan</h4>
                                    <div class="alasan">{{ $approval->alasan }}</div>
                                </div>
                                <div class="detail-section">
                                    <h4>Evidence @if ($approval->evidence_name)<span class="muted small" style="font-weight: 400;">· {{ $approval->evidence_name }}</span>@endif</h4>
                                    @if ($approval->evidence_path)
                                        @php($evidenceUrl = route('hrms.absensi.approval.evidence', $approval))
                                        @if ($approval->evidenceIsImage())
                                            <a href="{{ $evidenceUrl }}" target="_blank" data-turbo="false"><img src="{{ $evidenceUrl }}" alt="Evidence" class="evidence-img" loading="lazy"></a>
                                        @else
                                            <iframe src="{{ $evidenceUrl }}" class="evidence-frame" title="Evidence"></iframe>
                                        @endif
                                        <div style="margin-top: 6px;"><a href="{{ $evidenceUrl }}" target="_blank" data-turbo="false" class="small" style="color: var(--primary); font-weight: 600;">Buka di tab baru</a></div>
                                    @else
                                        <div class="evidence-empty">Tidak ada lampiran.</div>
                                    @endif
                                </div>
                                @unless ($approval->isPending())
                                    <div class="detail-section">
                                        <h4>Keputusan HRD</h4>
                                        <div class="small">{{ $approval->statusLabel() }} oleh {{ $approval->reviewer?->name ?? '-' }} · {{ $approval->reviewed_at?->translatedFormat('d M Y H:i') }}</div>
                                        @if ($approval->catatan_review)
                                            <div class="alasan" style="margin-top: 6px;">{{ $approval->catatan_review }}</div>
                                        @endif
                                    </div>
                                @endunless
                            </template>
                            <template id="detail-actions-{{ $approval->id }}" data-pending="{{ $approval->isPending() ? '1' : '' }}"
                                      data-approve="{{ route('hrms.absensi.approval.approve', $approval) }}"
                                      data-reject="{{ route('hrms.absensi.approval.reject', $approval) }}"></template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted" style="text-align: center; padding: 32px;">
                            {{ $status === 'pending' ? 'Tidak ada pengajuan yang menunggu approval.' : 'Belum ada pengajuan.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $approvals->links() }}

<dialog class="modal wide" id="detail-modal">
    <form method="POST" id="review-form">
        @csrf
        <div class="modal-head">
            <h2>Detail Pengajuan</h2>
            <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <div id="detail-body"></div>
            <div class="detail-section" id="review-fields">
                <label for="catatan_review">Catatan HRD <span class="muted">(opsional)</span></label>
                <textarea id="catatan_review" name="catatan_review" rows="2" maxlength="255" placeholder="Mis. Surat dokter tidak terbaca, mohon upload ulang"></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close>Tutup</button>
            <button type="submit" class="btn btn-danger" id="review-reject">Tidak Setuju</button>
            <button type="submit" class="btn btn-success" id="review-approve">Setuju</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('detail-modal');
        var body = document.getElementById('detail-body');
        var approve = document.getElementById('review-approve');
        var reject = document.getElementById('review-reject');

        document.querySelectorAll('[data-detail]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.dataset.detail;
                var actions = document.getElementById('detail-actions-' + id);
                var pending = actions.dataset.pending === '1';
                body.innerHTML = '';
                body.appendChild(document.getElementById('detail-' + id).content.cloneNode(true));
                document.getElementById('review-fields').hidden = !pending;
                document.getElementById('catatan_review').value = '';
                approve.hidden = reject.hidden = !pending;
                approve.formAction = actions.dataset.approve;
                reject.formAction = actions.dataset.reject;
                modal.showModal();
            });
        });

        modal.querySelectorAll('[data-close]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
        modal.addEventListener('click', function (event) { if (event.target === modal) modal.close(); });
        // Hentikan PDF/gambar saat modal ditutup.
        modal.addEventListener('close', function () { body.innerHTML = ''; });

        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.dataset.confirm)) event.preventDefault();
            });
        });
    })();
</script>
@endpush
