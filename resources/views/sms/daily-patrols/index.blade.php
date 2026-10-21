@extends('layouts.app')

@section('title', 'Daily Patrol')

@section('styles')
    @include('sms.partials.patrol-styles')
@endsection

@section('content')
@php
    $resikoTone = collect($resikos)->map(fn ($info) => $info['tone']);
    $fields = ['tanggal', 'finding', 'potensi_bahaya', 'department_id', 'plant', 'rekomendasi', 'resiko', 'kategori', 'target', 'pic', 'status', 'foto', 'foto_perbaikan'];
    $keepFilters = array_filter(['q' => $search, 'status' => $status, 'department' => $department, 'page' => $patrols->currentPage() > 1 ? $patrols->currentPage() : null]);
@endphp

<div class="dp-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Daily Patrol</h1>
        <p class="muted" style="margin: 2px 0 0;">
            Temuan patroli K3 harian
            @if ($lockedDepartment)
                · departemen <span class="badge">{{ $lockedDepartment->name }}</span>
            @endif
        </p>
    </div>
    @if ($canEdit)
        <button type="button" class="btn" data-create>+ Tambah Temuan</button>
    @endif
</div>

@if ($missingDepartment)
    <div class="alert">Akun Anda adalah Admin Departemen SMS tetapi belum dipilihkan departemen. Hubungi Super Admin.</div>
@endif

<form method="GET" class="filters">
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari finding / potensi bahaya / PIC...">
    <select name="status" onchange="this.form.requestSubmit()" aria-label="Status">
        <option value="">Semua status</option>
        <option value="open" @selected($status === 'open')>Open</option>
        <option value="closed" @selected($status === 'closed')>Close</option>
    </select>
    @if ($isAdmin)
        <select name="department" onchange="this.form.requestSubmit()" aria-label="Area">
            <option value="">Semua area</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    @endif
    <div class="summary">
        <span class="chip">Total <b>{{ $patrols->total() }}</b></span>
    </div>
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table class="patrol">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Foto</th>
                    <th>Finding</th>
                    <th>Resiko</th>
                    <th>Target</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($patrols as $patrol)
                    <tr>
                        <td class="muted">{{ $patrols->firstItem() + $loop->index }}</td>
                        <td class="nowrap">{{ $patrol->tanggal->format('d M Y') }}</td>
                        <td>
                            <button type="button" class="thumb" data-photo="{{ $patrol->foto_url }}" title="Lihat foto temuan">
                                <img src="{{ $patrol->foto_url }}" alt="Foto temuan" loading="lazy">
                            </button>
                        </td>
                        <td class="c-finding" style="max-width: 360px;">
                            {{ $patrol->finding }}
                            <span class="sub">{{ $patrol->department?->name ?? '-' }} · Plant {{ $patrol->plant }}</span>
                        </td>
                        <td>
                            <span class="risk risk-{{ $resikoTone[$patrol->resiko] ?? 'orange' }}" title="{{ $resikos[$patrol->resiko]['label'] ?? '' }}">{{ $patrol->resiko }}</span>
                        </td>
                        <td class="nowrap">{{ $patrol->target->format('d M Y') }}</td>
                        <td>
                            @if ($patrol->isClosed())
                                <span class="status-pill status-closed" title="Ditutup {{ $patrol->closed_at?->format('d/m/Y H:i') }}">Close</span>
                            @else
                                <span class="status-pill status-open">Open</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            @include('sms.partials.patrol-actions', ['printable' => false, 'record' => [
                                'tanggal' => $patrol->tanggal->toDateString(),
                                'finding' => $patrol->finding,
                                'potensi_bahaya' => $patrol->potensi_bahaya,
                                'department_id' => (string) $patrol->department_id,
                                'plant' => $patrol->plant,
                                'rekomendasi' => $patrol->rekomendasi,
                                'resiko' => $patrol->resiko,
                                'kategori' => $patrol->kategori,
                                'target' => $patrol->target->toDateString(),
                                'pic' => $patrol->pic,
                                'status' => $patrol->status,
                                'foto_url' => $patrol->foto_url,
                                'foto_perbaikan_url' => $patrol->foto_perbaikan_url,
                            ]])
                            @include('sms.partials.patrol-detail', ['items' => [
                                ['Finding', $patrol->finding, true],
                                ['Potensi Bahaya', $patrol->potensi_bahaya, true],
                                ['Area', $patrol->department?->name],
                                ['Plant', $patrol->plant],
                                ['Rekomendasi', $patrol->rekomendasi, true],
                                ['Resiko', $patrol->resiko.' - '.($resikos[$patrol->resiko]['label'] ?? '')],
                                ['Kategori', $patrol->kategoriLabel()],
                                ['Target', $patrol->target->translatedFormat('d F Y')],
                                ['PIC', $patrol->pic],
                            ]])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted" style="text-align: center; padding: 32px;">
                            {{ $search !== '' || $status !== '' || $department !== '' ? 'Data tidak ditemukan.' : 'Belum ada temuan Daily Patrol.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $patrols->links() }}
</div>

@include('sms.partials.patrol-modals')

@if ($canEdit)
    {{-- Modal tambah / edit --}}
    <dialog class="modal" id="patrol-modal">
        <form method="POST" action="{{ route('sms.daily-patrols.store') }}" id="patrol-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="create">
            @foreach ($keepFilters as $key => $value)
                {{-- Filter & halaman daftar dipertahankan setelah simpan. --}}
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2 id="patrol-modal-title">Tambah Temuan</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div class="grid-2">
                    <div>
                        <label for="f-tanggal">Tanggal</label>
                        <input id="f-tanggal" type="date" name="tanggal" required>
                        @error('tanggal') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-target">Target</label>
                        <input id="f-target" type="date" name="target" required>
                        @error('target') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label for="f-department_id">Area</label>
                        @if ($isAdmin)
                            <select id="f-department_id" name="department_id" required>
                                <option value="">-- Pilih Area --</option>
                                @foreach ($departments as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="locked-field">{{ $lockedDepartment?->name ?? '-' }}</div>
                        @endif
                        @error('department_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-plant">Plant</label>
                        <input id="f-plant" type="text" name="plant" maxlength="50" placeholder="Mis. 1" required>
                        @error('plant') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div>
                    <label for="f-finding">Finding</label>
                    <textarea id="f-finding" name="finding" maxlength="2000" rows="2" placeholder="Mis. Tutup samping mesh troley tidak di tutup" required></textarea>
                    @error('finding') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="f-foto">Foto temuan <span class="muted" data-edit-only>(kosongkan kalau tidak diganti)</span></label>
                    <div class="photo-field">
                        <img class="photo-preview" id="preview-foto" alt="" hidden>
                        <input id="f-foto" type="file" name="foto" accept="image/*" data-photo-input data-preview="preview-foto">
                    </div>
                    @error('foto') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="f-potensi_bahaya">Potensi Bahaya</label>
                    <textarea id="f-potensi_bahaya" name="potensi_bahaya" maxlength="2000" rows="2" placeholder="Mis. Tergores part" required></textarea>
                    @error('potensi_bahaya') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="f-rekomendasi">Rekomendasi</label>
                    <textarea id="f-rekomendasi" name="rekomendasi" maxlength="2000" rows="2" placeholder="Mis. Tutup cover samping troley" required></textarea>
                    @error('rekomendasi') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="grid-2">
                    <div>
                        <label for="f-resiko">Resiko</label>
                        <select id="f-resiko" name="resiko" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($resikos as $kode => $info)
                                <option value="{{ $kode }}">{{ $kode }} - {{ $info['label'] }}</option>
                            @endforeach
                        </select>
                        @error('resiko') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-kategori">Kategori</label>
                        <select id="f-kategori" name="kategori" required>
                            <option value="">-- Pilih Kategori --</option>
                            @foreach ($kategoris as $group => $options)
                                <optgroup label="── {{ $group }} ──">
                                    @foreach ($options as $kode => $label)
                                        <option value="{{ $kode }}">{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('kategori') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div>
                    <label for="f-pic">PIC</label>
                    <input id="f-pic" type="text" name="pic" maxlength="255" placeholder="Mis. Hartono, Dedi, Aminudin, Danar" required>
                    @error('pic') <div class="field-error">{{ $message }}</div> @enderror
                </div>

                @include('sms.partials.patrol-fix-fields')
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" id="patrol-submit">Simpan</button>
            </div>
        </form>
    </dialog>
@endif
@endsection

@push('scripts')
    @include('sms.partials.patrol-script', ['routePrefix' => 'sms.daily-patrols'])
@endpush
