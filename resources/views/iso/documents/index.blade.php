@extends('layouts.app')

@section('title', $judul)

@section('styles')
    .iso-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .iso-head-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin: 4px 0 0; font-size: 14px; color: var(--muted); }
    .crumbs a { color: var(--muted); padding: 2px 6px; border-radius: 8px; }
    .crumbs a:hover { background: var(--soft-2); color: var(--primary); }
    .crumbs .current { color: var(--text); font-weight: 600; padding: 2px 6px; }
    .crumbs .sep { color: var(--faint); }
    .iso-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
    .iso-filters input[type=search] { width: 260px; border-radius: 999px; }
    .iso-filters select { width: auto; min-width: 150px; border-radius: 999px; }
    .iso-filters .total { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 13px; background: var(--glass-strong); }
    .section-title { font-size: 13px; font-weight: 600; color: var(--muted); margin: 0 0 10px; }

    .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; margin-bottom: 24px; }
    .folder-card { position: relative; display: flex; align-items: center; gap: 10px; padding: 10px 8px 10px 14px; border-radius: 14px; background: #fff; border: 1px solid var(--line-strong); transition: box-shadow .15s, border-color .15s; }
    .folder-card:hover { box-shadow: 0 8px 20px rgba(15, 35, 80, .1); border-color: var(--primary); }
    .folder-ico { flex: none; width: 36px; height: 36px; display: grid; place-items: center; border-radius: 10px; background: #fef3c7; color: #b45309; }
    .folder-ico svg { width: 20px; height: 20px; }
    .folder-text { min-width: 0; flex: 1; }
    .folder-name { font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .folder-sub { font-size: 11px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .folder-open { position: absolute; inset: 0; z-index: 1; border-radius: 14px; }
    .folder-card .doc-btn { position: relative; z-index: 2; }

    .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
    .doc-card { display: flex; flex-direction: column; border-radius: 16px; background: #fff; border: 1px solid var(--line-strong); overflow: hidden; transition: box-shadow .15s, transform .15s; }
    .doc-card:hover { box-shadow: 0 10px 24px rgba(15, 35, 80, .12); transform: translateY(-2px); }
    .doc-thumb { position: relative; display: grid; place-items: center; height: 150px; overflow: hidden; }
    /* Thumbnail dari server (gambar / halaman pertama PDF) menutupi ikon setelah termuat. */
    .doc-preview { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; opacity: 0; transition: opacity .2s; }
    .doc-preview.loaded { opacity: 1; }
    .doc-preview-page { object-position: top; background: #fff; }
    .doc-open { position: absolute; inset: 0; z-index: 2; }
    .doc-thumb svg { width: 46px; height: 46px; }
    .doc-ext { z-index: 1; position: absolute; left: 10px; bottom: 10px; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; letter-spacing: .03em; background: rgba(255, 255, 255, .85); }
    .tone-red { background: #fee2e2; color: #b91c1c; }
    .tone-blue { background: #dbeafe; color: #1d4ed8; }
    .tone-green { background: #dcfce7; color: #15803d; }
    .tone-orange { background: #ffedd5; color: #c2410c; }
    .tone-purple { background: #ede9fe; color: #6d28d9; }
    .tone-gray { background: var(--soft-2); color: var(--muted); }
    .doc-body { padding: 10px 12px 4px; flex: 1; }
    .doc-name { font-size: 13px; font-weight: 600; line-height: 1.35; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .doc-meta { margin-top: 6px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px; font-size: 11px; color: var(--muted); }
    .doc-actions { display: flex; justify-content: flex-end; gap: 4px; padding: 6px 8px 8px; }
    .doc-btn { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 8px; border: 1px solid transparent; background: transparent; color: var(--muted); cursor: pointer; }
    .doc-btn svg { width: 15px; height: 15px; }
    .doc-btn:hover { background: var(--soft-2); color: var(--primary); }
    .doc-btn.danger:hover { color: var(--danger); background: #fee2e2; }
    /* Dokumen nonaktif (hanya terlihat Admin ISO): dibuat pudar. */
    .doc-card.inactive .doc-thumb, .doc-card.inactive .doc-body { opacity: .45; filter: grayscale(.6); }
    .status-tag { display: none; padding: 1px 8px; border-radius: 999px; font-size: 10px; font-weight: 700; background: #fee2e2; color: #b91c1c; }
    .doc-card.inactive .status-tag { display: inline-block; }
    /* Kirim notifikasi hanya untuk dokumen aktif. */
    .doc-card.inactive [data-notify] { display: none; }
    #notify-modal { width: min(560px, calc(100% - 32px)); }
    /* Tombol sedang memproses (kirim notifikasi / upload): ikon berputar. */
    .btn.loading { display: inline-flex; align-items: center; gap: 8px; cursor: progress; opacity: .9; }
    .spinner { width: 15px; height: 15px; border-radius: 50%; border: 2px solid rgba(255, 255, 255, .35); border-top-color: #fff; animation: spin .7s linear infinite; flex: none; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .spinner { animation-duration: 2s; } }
    #notify-modal .modal-body { display: grid; gap: 14px; }
    .recipient-list { display: grid; gap: 4px; max-height: 240px; overflow: auto; padding: 4px; border: 1px solid var(--line-strong); border-radius: 12px; }
    .recipient-list label { display: flex; align-items: center; gap: 10px; margin: 0; padding: 6px 8px; border-radius: 8px; font-weight: 400; cursor: pointer; }
    .recipient-list label:hover { background: var(--soft-2); }
    .recipient-list small { color: var(--muted); }
    .doc-actions { align-items: center; }
    .switch { position: relative; display: inline-flex; align-items: center; gap: 6px; margin: 0 auto 0 4px; font-size: 11px; font-weight: 600; color: var(--muted); cursor: pointer; }
    .switch input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .switch .track { width: 32px; height: 18px; border-radius: 999px; background: var(--line-strong); position: relative; transition: background .15s; flex: none; }
    .switch .track::after { content: ""; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .2); transition: transform .15s; }
    .switch input:checked + .track { background: var(--success); }
    .switch input:checked + .track::after { transform: translateX(14px); }
    .switch input:focus-visible + .track { outline: 2px solid var(--primary); outline-offset: 2px; }
    .switch input:disabled + .track { opacity: .5; }
    .doc-empty { padding: 48px 16px; text-align: center; color: var(--muted); border: 2px dashed var(--line-strong); border-radius: 18px; }

    #upload-modal, #edit-modal, #folder-modal { width: min(560px, calc(100% - 32px)); }
    #upload-modal .modal-body, #edit-modal .modal-body, #folder-modal .modal-body { display: grid; gap: 14px; }
    .dropzone { display: grid; place-items: center; gap: 6px; padding: 28px 16px; text-align: center; border: 2px dashed var(--line-strong); border-radius: 16px; background: var(--soft-2); cursor: pointer; font-weight: 400; margin: 0; }
    .dropzone.over { border-color: var(--primary); background: #eef4ff; }
    .dropzone svg { width: 34px; height: 34px; color: var(--primary); }
    .dropzone input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .file-list { display: grid; gap: 6px; max-height: 180px; overflow: auto; }
    .file-list div { display: flex; justify-content: space-between; gap: 10px; padding: 6px 10px; border-radius: 8px; background: var(--soft-2); font-size: 12px; }
    .file-list span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .locked-dept { padding: 11px 14px; border-radius: 12px; background: var(--soft-2); font-size: 14px; }
    .drop-overlay { position: fixed; inset: 0; z-index: 50; display: none; place-items: center; background: rgba(37, 99, 235, .12); border: 3px dashed var(--primary); font-size: 18px; font-weight: 600; color: var(--navy); pointer-events: none; }
    .drop-overlay.show { display: grid; }
    /* Upload folder dari komputer + pindah file dengan drag & drop kartu ke folder / breadcrumb. */
    .upload-picks { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-top: -4px; }
    .upload-summary { font-size: 12px; font-weight: 600; color: var(--muted); }
    .doc-card[draggable=true] { cursor: grab; }
    .doc-card.dragging { opacity: .45; }
    .folder-card.drop-target, .crumbs a.drop-target { border-color: var(--primary); background: #eef4ff; box-shadow: 0 0 0 3px rgba(37, 99, 235, .25); }
    .move-hint { font-weight: 400; color: var(--faint); }
    /* Panel loading di modal upload: saat membaca folder & selama upload. */
    .upload-progress { display: grid; gap: 10px; padding: 14px 16px; border-radius: 14px; background: #eef4ff; border: 1px solid #c7d7fe; }
    .upload-progress[hidden] { display: none; }
    .upload-progress-head { display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 600; color: var(--navy); }
    .spinner.spinner-lg { width: 22px; height: 22px; border-width: 3px; border-color: rgba(37, 99, 235, .2); border-top-color: var(--primary); }
    .progress-bar { height: 6px; border-radius: 999px; background: rgba(37, 99, 235, .15); overflow: hidden; }
    .progress-bar span { display: block; height: 100%; width: 0; border-radius: inherit; background: var(--primary); transition: width .3s; }
    #upload-modal[data-busy] .dropzone, #upload-modal[data-busy] .upload-picks { pointer-events: none; opacity: .5; }
@endsection

@section('content')
@php
    $listFilters = array_filter(['q' => $search, 'jenis' => $jenis, 'department' => $department, 'status' => $status]);
    $here = array_filter(['folder' => $currentFolder?->id]);
    $keepFilters = $here + $listFilters + array_filter(['page' => $documents->currentPage() > 1 ? $documents->currentPage() : null]);
    $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg>';
    $folderIcon = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8z"/></svg>';
    $editIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>';
    $trashIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>';
    $lokasi = $currentFolder ? 'folder '.$currentFolder->nama : $judul;
@endphp

<div class="iso-head">
    <div style="min-width: 0;">
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ $judul }}</h1>
        @if ($currentFolder)
            <nav class="crumbs" aria-label="Lokasi folder">
                {{-- Breadcrumb juga jadi tujuan drop untuk memindahkan file keluar ke folder induk. --}}
                <a href="{{ route("iso.{$kategori}.index") }}" @if ($canManageHere) data-drop-folder="" @endif>{{ $judul }}</a>
                @foreach ($breadcrumbs as $crumb)
                    <span class="sep">/</span>
                    @if ($loop->last)
                        <span class="current">{{ $crumb->nama }}</span>
                    @else
                        <a href="{{ route("iso.{$kategori}.index", ['folder' => $crumb->id]) }}" @if ($canManageHere) data-drop-folder="{{ $crumb->id }}" @endif>{{ $crumb->nama }}</a>
                    @endif
                @endforeach
                <span class="badge" style="margin-left: 6px;">{{ $currentFolder->department?->name ?? 'Umum' }}</span>
            </nav>
        @else
            <p class="muted" style="margin: 2px 0 0;">
                Dokumen ISO
                @if ($lockedDepartments->isNotEmpty())
                    · departemen
                    @foreach ($lockedDepartments as $lockedDepartment)
                        <span class="badge">{{ $lockedDepartment->name }}</span>
                    @endforeach
                    + dokumen umum
                @elseif (! $isAdmin)
                    · hanya dokumen umum
                @endif
                @unless ($canManageKategori)
                    · <span class="badge">Hanya lihat</span>
                @endunless
            </p>
        @endif
    </div>
    @if ($canManageHere)
        <div class="iso-head-actions">
            <button type="button" class="btn btn-ghost" data-create-folder>+ Folder</button>
            <button type="button" class="btn" data-upload>+ Upload File</button>
        </div>
    @endif
</div>

@if ($missingDepartment)
    <div class="alert">Akun Anda adalah Admin Departemen ISO tetapi belum dipilihkan departemen. Hubungi Super Admin.</div>
@endif
@if ($errors->has('notify'))
    <div class="alert">{{ $errors->first('notify') }}</div>
@endif

<form method="GET" class="iso-filters">
    @if ($currentFolder)
        <input type="hidden" name="folder" value="{{ $currentFolder->id }}">
    @endif
    <input type="search" name="q" value="{{ $search }}" placeholder="{{ $currentFolder ? 'Cari di folder ini...' : 'Cari folder / dokumen...' }}">
    <select name="jenis" onchange="this.form.requestSubmit()" aria-label="Jenis file">
        <option value="">Semua jenis</option>
        @foreach (\App\Modules\Iso\Models\IsoDocument::JENIS as $kode => $info)
            <option value="{{ $kode }}" @selected($jenis === $kode)>{{ $info['label'] }}</option>
        @endforeach
        <option value="lainnya" @selected($jenis === 'lainnya')>Lainnya</option>
    </select>
    {{-- Guest hanya melihat dokumen umum, jadi filter departemen tidak ditampilkan. --}}
    @if ($isAdmin || $departments->isNotEmpty())
        <select name="department" onchange="this.form.requestSubmit()" aria-label="Departemen">
            <option value="">Semua departemen</option>
            <option value="umum" @selected($department === 'umum')>Umum</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    @endif
    @if ($isAdmin)
        <select name="status" onchange="this.form.requestSubmit()" aria-label="Status dokumen">
            <option value="">Semua status</option>
            <option value="aktif" @selected($status === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
        </select>
    @endif
    <span class="total">
        @if ($folders->isNotEmpty())<b>{{ $folders->count() }}</b> folder ·@endif
        <b>{{ $documents->total() }}</b> file
    </span>
</form>

@if ($folders->isEmpty() && $documents->isEmpty())
    <div class="doc-empty">
        @if ($filtering)
            Folder / dokumen tidak ditemukan.
        @else
            {{ $currentFolder ? 'Folder ini masih kosong.' : 'Belum ada folder atau dokumen di '.$judul.'.' }}
            @if ($canManageHere)
                <div class="small" style="margin-top: 6px;">Klik <b>+ Folder</b> / <b>+ Upload File</b>, atau tarik file / folder dari komputer ke halaman ini.</div>
            @endif
        @endif
    </div>
@endif

@if ($folders->isNotEmpty())
    <h2 class="section-title">
        Folder
        @if ($canManageKategori && $documents->isNotEmpty())
            <span class="move-hint">· tarik kartu file ke folder untuk memindahkannya</span>
        @endif
    </h2>
    <div class="folder-grid">
        @foreach ($folders as $folder)
            @php
                $isi = collect([$folder->children_count ? $folder->children_count.' folder' : null, $folder->documents_count ? $folder->documents_count.' file' : null])->filter()->join(', ') ?: 'Kosong';
                $canManageFolder = $isAdmin || in_array($folder->department_id, $managedDepartmentIds, true);
            @endphp
            <div class="folder-card" @if ($canManageFolder) data-drop-folder="{{ $folder->id }}" @endif>
                <span class="folder-ico">{!! $folderIcon !!}</span>
                <div class="folder-text">
                    <div class="folder-name" title="{{ $folder->nama }}">{{ $folder->nama }}</div>
                    <div class="folder-sub">{{ $folder->department?->name ?? 'Umum' }} · {{ $isi }}</div>
                </div>
                <a href="{{ route("iso.{$kategori}.index", ['folder' => $folder->id]) }}" class="folder-open" aria-label="Buka folder {{ $folder->nama }}"></a>
                @if ($canManageFolder)
                    <button type="button" class="doc-btn" title="Edit folder" aria-label="Edit folder" data-edit-folder="{{ $folder->id }}"
                            data-record="{{ json_encode(['nama' => $folder->nama, 'department_id' => (string) $folder->department_id, 'root' => $folder->parent_id === null]) }}">{!! $editIcon !!}</button>
                    <button type="button" class="doc-btn danger" title="Hapus folder" aria-label="Hapus folder" data-delete-folder="{{ $folder->id }}"
                            data-name="{{ $folder->nama }}" data-isi="{{ $isi }}">{!! $trashIcon !!}</button>
                @endif
            </div>
        @endforeach
    </div>
@endif

@if ($documents->isNotEmpty())
    @if ($folders->isNotEmpty())
        <h2 class="section-title">File</h2>
    @endif
    <div class="doc-grid">
        @foreach ($documents as $document)
            @php
                $jenisFile = $document->jenis();
                $tone = \App\Modules\Iso\Models\IsoDocument::JENIS[$jenisFile]['tone'] ?? 'gray';
                $fileUrl = route("iso.{$kategori}.file", $document);
                $canManage = $isAdmin || in_array($document->department_id, $managedDepartmentIds, true);
            @endphp
            {{-- Kartu yang boleh dikelola bisa ditarik ke kartu folder / breadcrumb untuk dipindahkan. --}}
            <div @class(['doc-card', 'inactive' => ! $document->is_active])
                 @if ($canManage) draggable="true" data-move-url="{{ route("iso.{$kategori}.move", $document) }}" data-folder-id="{{ $document->folder_id }}" @endif>
                <div class="doc-thumb tone-{{ $tone }}">
                    {!! $icon !!}
                    {{-- Thumbnail JPG kecil (bukan file asli) supaya halaman ringan; kalau gagal, ikon tetap terlihat. --}}
                    @if ($document->hasThumbnail())
                        <img @class(['doc-preview', 'doc-preview-page' => $jenisFile === 'pdf']) alt="" loading="lazy" decoding="async" draggable="false"
                             src="{{ route("iso.{$kategori}.thumbnail", [$document, 'v' => $document->updated_at?->timestamp]) }}"
                             onload="this.classList.add('loaded')" onerror="this.remove()">
                    @endif
                    <span class="doc-ext">{{ strtoupper($document->extension ?? 'FILE') }}</span>
                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="doc-open" title="Buka {{ $document->nama }}" aria-label="Buka {{ $document->nama }}" draggable="false"></a>
                </div>
                <div class="doc-body">
                    <div class="doc-name" title="{{ $document->nama }}">{{ $document->nama }}</div>
                    @if ($isAdmin)
                        <span class="status-tag" title="Tidak terlihat oleh Admin Departemen">Nonaktif</span>
                    @endif
                    <div class="doc-meta">
                        <span class="badge">{{ $document->department?->name ?? 'Umum' }}</span>
                        <span style="white-space: nowrap;">{{ $document->sizeLabel() }} · {{ $document->created_at->translatedFormat('d M Y') }}</span>
                    </div>
                    {{-- Hasil pencarian bisa dari folder mana saja, jadi lokasinya ditampilkan. --}}
                    @if ($filtering && $document->folder)
                        <div class="doc-meta">
                            <a href="{{ route("iso.{$kategori}.index", ['folder' => $document->folder_id]) }}" style="color: inherit; text-decoration: underline;">di {{ $document->folder->nama }}</a>
                        </div>
                    @endif
                    @if ($document->creator)
                        <div class="doc-meta" title="Diupload oleh">oleh {{ $document->creator->name }}</div>
                    @endif
                    @if ($isAdmin && $document->notified_at)
                        <div class="doc-meta" title="Notifikasi email terakhir">✉ {{ $document->notified_at->translatedFormat('d M Y H:i') }}</div>
                    @endif
                </div>
                <div class="doc-actions">
                    @if ($isAdmin)
                        {{-- Toggle status: nonaktif = disembunyikan dari Admin Departemen. --}}
                        <label class="switch" title="Aktif: terlihat oleh Admin Departemen. Nonaktif: hanya Admin ISO.">
                            <input type="checkbox" data-status-url="{{ route("iso.{$kategori}.status", $document) }}" @checked($document->is_active)
                                   aria-label="Status {{ $document->nama }}">
                            <span class="track"></span>
                            <span data-status-label>{{ $document->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </label>
                    @endif
                    @if ($isAdmin)
                        <button type="button" class="doc-btn" title="Kirim notifikasi pembaruan ke email" aria-label="Kirim notifikasi"
                                data-notify="{{ $document->id }}" data-name="{{ $document->nama }}" data-big="{{ $document->size > 10 * 1024 * 1024 ? 1 : 0 }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                        </button>
                    @endif
                    <a href="{{ $fileUrl }}?download=1" class="doc-btn" title="Unduh" aria-label="Unduh" draggable="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                    </a>
                    @if ($canManage)
                        <button type="button" class="doc-btn" title="Edit" aria-label="Edit" data-edit="{{ $document->id }}"
                                data-record="{{ json_encode(['nama' => $document->nama, 'department_id' => (string) $document->department_id, 'folder_id' => (string) $document->folder_id]) }}">{!! $editIcon !!}</button>
                        <button type="button" class="doc-btn danger" title="Hapus" aria-label="Hapus" data-delete="{{ $document->id }}" data-name="{{ $document->nama }}">{!! $trashIcon !!}</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top: 16px;">{{ $documents->links() }}</div>
@endif

@if ($canManageHere)
    <div class="drop-overlay" id="drop-overlay">Lepaskan file / folder untuk upload ke {{ $lokasi }}</div>

    {{-- Modal upload: bisa beberapa file sekaligus, semua jenis file, ke folder yang sedang dibuka. --}}
    <dialog class="modal" id="upload-modal">
        <form method="POST" action="{{ route("iso.{$kategori}.store") }}" enctype="multipart/form-data" id="upload-form">
            @csrf
            <input type="hidden" name="_modal" value="upload">
            <input type="hidden" name="folder_id" value="{{ $currentFolder?->id }}">
            @foreach ($keepFilters as $key => $value)
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2>Upload ke {{ $lokasi }}</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <label class="dropzone" id="dropzone">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>
                    <strong>Klik untuk pilih file, atau tarik file / folder ke sini</strong>
                    <span class="muted small">PDF, Word, Excel, PowerPoint, gambar, dll · maks. {{ \App\Modules\Iso\Models\IsoDocument::MAX_KB / 1024 }} MB per file. Folder diupload beserta isi & subfolder-nya.</span>
                    <input type="file" id="upload-input" multiple>
                </label>
                <div class="upload-picks">
                    <button type="button" class="btn btn-ghost btn-sm" id="upload-folder-pick">Pilih folder...</button>
                    <input type="file" id="upload-folder-input" webkitdirectory multiple hidden>
                </div>
                <div class="upload-progress" id="upload-progress" role="status" aria-live="polite" hidden>
                    <div class="upload-progress-head">
                        <span class="spinner spinner-lg" aria-hidden="true"></span>
                        <span id="upload-progress-text">Mengupload...</span>
                    </div>
                    <div class="progress-bar" id="upload-progress-bar" hidden><span></span></div>
                </div>
                <div class="upload-summary" id="upload-summary" hidden></div>
                <div class="file-list" id="file-list"></div>
                <div class="field-error" id="upload-error" hidden></div>
                @if ($errors->has('files') || $errors->has('files.*') || $errors->has('folder_id'))
                    <div class="field-error">{{ $errors->first('files') ?: ($errors->first('files.*') ?: $errors->first('folder_id')) }}</div>
                @endif
                <div>
                    <label for="upload-department">Departemen</label>
                    @include('iso.documents.partials.department-field', ['id' => 'upload-department', 'inFolder' => $currentFolder])
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" id="upload-submit">Upload</button>
            </div>
        </form>
    </dialog>
@endif

@if ($canManageKategori)
    {{-- Modal folder: buat baru (di folder yang sedang dibuka) atau ganti nama. --}}
    <dialog class="modal" id="folder-modal">
        <form method="POST" id="folder-form">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="">
            <input type="hidden" name="parent_id" value="{{ $currentFolder?->id }}">
            @foreach ($keepFilters as $key => $value)
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2 id="folder-modal-title">Folder Baru</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div>
                    <label for="folder-nama">Nama folder</label>
                    <input id="folder-nama" type="text" name="nama" maxlength="255" placeholder="Mis. Produksi, Revisi 2026" required>
                    @error('nama') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                {{-- Departemen hanya dipilih di folder paling atas; subfolder ikut folder induknya. --}}
                <div id="folder-department-field">
                    <label for="folder-department">Departemen</label>
                    @include('iso.documents.partials.department-field', ['id' => 'folder-department', 'inFolder' => null])
                    <div class="muted small" style="margin-top: 4px;">Semua subfolder & file di dalam folder ini ikut departemen ini.</div>
                </div>
                <div id="folder-department-note" class="muted small" hidden>Departemen ikut folder induk.</div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn">Simpan</button>
            </div>
        </form>
    </dialog>

    {{-- Modal edit dokumen: ganti nama, pindah folder/departemen, atau ganti file. --}}
    <dialog class="modal" id="edit-modal">
        <form method="POST" enctype="multipart/form-data" id="edit-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="_modal" value="">
            @foreach ($keepFilters as $key => $value)
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2>Edit Dokumen</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div>
                    <label for="edit-nama">Nama dokumen</label>
                    <input id="edit-nama" type="text" name="nama" maxlength="255" required>
                    @error('nama') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="edit-folder">Folder</label>
                    <select id="edit-folder" name="folder_id">
                        <option value="">— Di luar folder ({{ $judul }}) —</option>
                        @foreach ($moveTargets as $target)
                            <option value="{{ $target['id'] }}">{{ $target['label'] }}</option>
                        @endforeach
                    </select>
                    @error('folder_id') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div id="edit-department-field">
                    <label for="edit-department">Departemen</label>
                    @include('iso.documents.partials.department-field', ['id' => 'edit-department', 'inFolder' => null])
                </div>
                <div id="edit-department-note" class="muted small" hidden>Departemen ikut folder tujuan.</div>
                <div>
                    <label for="edit-file">Ganti file <span class="muted">(opsional)</span></label>
                    <input id="edit-file" type="file" name="file">
                    @error('file') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn">Simpan</button>
            </div>
        </form>
    </dialog>

    <dialog class="modal" id="delete-modal">
        <form method="POST" id="delete-form">
            @csrf
            @method('DELETE')
            @foreach ($keepFilters as $key => $value)
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2 id="delete-title">Hapus Dokumen</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body" id="delete-body"></div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" style="background: var(--danger);">Hapus</button>
            </div>
        </form>
    </dialog>
@endif

@if ($isAdmin)
    {{-- Modal kirim notifikasi pembaruan: pilih penerima aktif dari menu Email. --}}
    <dialog class="modal" id="notify-modal">
        <form method="POST" id="notify-form">
            @csrf
            <input type="hidden" name="_modal" value="">
            @foreach ($keepFilters as $key => $value)
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <div>
                    <h2>Kirim Notifikasi Pembaruan</h2>
                    <div class="muted small" id="notify-doc"></div>
                </div>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                @if ($recipients->isEmpty())
                    <div class="alert" style="margin: 0;">
                        Belum ada email penerima yang aktif. Tambahkan dulu di menu <a href="{{ route('iso.emails.index') }}" style="text-decoration: underline;">Email</a>.
                    </div>
                @else
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label style="margin: 0;">Penerima <span class="muted">({{ $recipients->count() }} email aktif)</span></label>
                            <button type="button" class="btn btn-ghost btn-sm" data-toggle-all>Pilih semua / tidak</button>
                        </div>
                        <div class="recipient-list">
                            @foreach ($recipients as $recipient)
                                <label>
                                    <input type="checkbox" name="recipients[]" value="{{ $recipient->id }}" checked>
                                    <span>{{ $recipient->nama }} <small>· {{ $recipient->email }}</small></span>
                                </label>
                            @endforeach
                        </div>
                        @error('recipients') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="notify-pesan">Catatan <span class="muted">(opsional)</span></label>
                        <textarea id="notify-pesan" name="pesan" rows="3" maxlength="1000" placeholder="Mis. Revisi 2: perubahan langkah 4 dan 5.">{{ old('pesan') }}</textarea>
                        @error('pesan') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <label class="check" id="notify-attach">
                        <input type="checkbox" name="lampirkan" value="1">
                        Lampirkan file ke email <span class="muted small">(maks. 10 MB)</span>
                    </label>
                    @error('lampirkan') <div class="field-error">{{ $message }}</div> @enderror
                @endif
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                @if ($recipients->isNotEmpty())
                    <button type="submit" class="btn" id="notify-submit">Kirim</button>
                @endif
            </div>
        </form>
    </dialog>
@endif
@endsection

@push('scripts')
<script>
    (function () {
        // Modal yang sedang memproses (data-busy) tidak bisa ditutup lewat Batal, ×, klik di luar, atau Esc.
        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () { if (!dialog.dataset.busy) dialog.close(); });
            });
            dialog.addEventListener('click', function (event) { if (event.target === dialog && !dialog.dataset.busy) dialog.close(); });
            dialog.addEventListener('cancel', function (event) { if (dialog.dataset.busy) event.preventDefault(); });
        });

        // Tombol submit jadi "sedang memproses": ikon berputar + teks, tombol lain di modal ikut nonaktif.
        function setLoading(button, text) {
            button.disabled = true;
            button.classList.add('loading');
            button.innerHTML = '<span class="spinner" aria-hidden="true"></span>';
            button.appendChild(document.createTextNode(text));
            button.setAttribute('aria-busy', 'true');
            var dialog = button.closest('dialog');
            if (dialog) dialog.querySelectorAll('[data-close]').forEach(function (close) { close.disabled = true; });
        }

        function clearErrors(modal) { modal.querySelectorAll('.field-error').forEach(function (el) { el.remove(); }); }

        // ===== Toggle aktif / nonaktif (Admin ISO): disimpan langsung tanpa reload; dikembalikan kalau gagal =====
        document.querySelectorAll('[data-status-url]').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var card = toggle.closest('.doc-card');
                var label = toggle.closest('.switch').querySelector('[data-status-label]');
                var active = toggle.checked;
                var apply = function (value) {
                    toggle.checked = value;
                    card.classList.toggle('inactive', !value);
                    label.textContent = value ? 'Aktif' : 'Nonaktif';
                };
                apply(active);
                toggle.disabled = true;

                fetch(toggle.dataset.statusUrl, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ is_active: active }),
                }).then(function (response) {
                    if (!response.ok) throw new Error(response.status === 419 ? 'Sesi habis, muat ulang halaman.' : 'Gagal mengubah status.');
                    return response.json();
                }).then(function (body) {
                    apply(body.is_active);
                }).catch(function (error) {
                    apply(!active);
                    alert(error.message);
                }).finally(function () {
                    toggle.disabled = false;
                });
            });
        });

        // ===== Kirim notifikasi pembaruan (Admin ISO) =====
        var notifyModal = document.getElementById('notify-modal');
        if (notifyModal) {
            var notifyForm = document.getElementById('notify-form');
            var notifyUrl = {{ Js::from(route("iso.{$kategori}.notify", '__ID__')) }};
            var attach = document.getElementById('notify-attach');

            var openNotify = function (button, keepErrors) {
                notifyForm.action = notifyUrl.replace('__ID__', button.dataset.notify);
                notifyForm.elements._modal.value = 'notify:' + button.dataset.notify;
                document.getElementById('notify-doc').textContent = button.dataset.name;
                // File di atas 10 MB tidak bisa dilampirkan.
                if (attach) {
                    var big = button.dataset.big === '1';
                    attach.querySelector('input').disabled = big;
                    attach.querySelector('input').checked = attach.querySelector('input').checked && !big;
                    attach.style.opacity = big ? .5 : 1;
                    attach.title = big ? 'File lebih dari 10 MB, kirim tanpa lampiran.' : '';
                }
                if (!keepErrors) clearErrors(notifyModal);
                notifyModal.showModal();
            };

            document.querySelectorAll('[data-notify]').forEach(function (button) {
                button.addEventListener('click', function () { openNotify(button); });
            });
            var toggleAll = notifyModal.querySelector('[data-toggle-all]');
            if (toggleAll) {
                toggleAll.addEventListener('click', function () {
                    var boxes = notifyModal.querySelectorAll('input[name="recipients[]"]');
                    var allChecked = Array.prototype.every.call(boxes, function (box) { return box.checked; });
                    boxes.forEach(function (box) { box.checked = !allChecked; });
                });
            }
            notifyForm.addEventListener('submit', function () {
                var submit = document.getElementById('notify-submit');
                if (submit) { setLoading(submit, 'Mengirim...'); }
                notifyModal.dataset.busy = '1';
            });

            var reopenNotify = {{ Js::from($errors->hasAny(['recipients', 'pesan', 'lampirkan']) ? old('_modal') : null) }};
            if (reopenNotify && reopenNotify.indexOf('notify:') === 0) {
                var notifyButton = document.querySelector('[data-notify="' + reopenNotify.slice(7) + '"]');
                if (notifyButton) openNotify(notifyButton, true);
            }
        }

        // ===== Upload file & folder: pilih / tarik dari komputer, lalu dikirim bertahap (maks. 20 file per kiriman) =====
        // Tiap file membawa jalur foldernya (mis. "Produksi/Revisi"), server membuat struktur folder yang sama.
        var uploadModal = document.getElementById('upload-modal');
        if (uploadModal) {
            var uploadForm = document.getElementById('upload-form');
            var input = document.getElementById('upload-input');
            var folderInput = document.getElementById('upload-folder-input');
            var list = document.getElementById('file-list');
            var summary = document.getElementById('upload-summary');
            var uploadError = document.getElementById('upload-error');
            var uploadSubmit = document.getElementById('upload-submit');
            var dropzone = document.getElementById('dropzone');
            var overlay = document.getElementById('drop-overlay');
            var maxBytes = {{ \App\Modules\Iso\Models\IsoDocument::MAX_KB }} * 1024;
            var maxFilesPerRequest = {{ \App\Modules\Iso\Models\IsoDocument::MAX_FILES }};
            var maxBytesPerRequest = 64 * 1048576;
            var maxFoldersPerRequest = 500;
            var selection = { items: [], folders: [] };
            var reloadOnClose = false;

            var sizeLabel = function (bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / 1048576).toFixed(1) + ' MB';
            };

            var showUploadError = function (message) {
                uploadError.textContent = message || '';
                uploadError.hidden = !message;
            };

            // Panel loading: spinner + teks, dan progress bar kalau ratio diisi (0-1).
            var progress = document.getElementById('upload-progress');
            var progressText = document.getElementById('upload-progress-text');
            var progressBar = document.getElementById('upload-progress-bar');
            var showProgress = function (message, ratio) {
                progress.hidden = false;
                progressText.textContent = message;
                progressBar.hidden = ratio === undefined;
                if (ratio !== undefined) progressBar.firstElementChild.style.width = Math.round(Math.min(ratio, 1) * 100) + '%';
            };
            var hideProgress = function () { progress.hidden = true; };

            var renderFiles = function () {
                list.innerHTML = '';
                selection.items.forEach(function (item) {
                    var row = document.createElement('div');
                    var name = document.createElement('span');
                    var size = document.createElement('span');
                    name.textContent = item.dir ? item.dir + '/' + item.file.name : item.file.name;
                    name.title = name.textContent;
                    size.textContent = sizeLabel(item.file.size);
                    if (item.file.size > maxBytes) size.style.color = 'var(--danger)';
                    row.appendChild(name);
                    row.appendChild(size);
                    list.appendChild(row);
                });
                var folderCount = selection.folders.length;
                summary.hidden = !selection.items.length && !folderCount;
                summary.textContent = selection.items.length + ' file' + (folderCount ? ' dalam ' + folderCount + ' folder' : '') + ' dipilih';
            };

            var setSelection = function (items, folders) {
                selection = { items: items, folders: folders || [] };
                showUploadError('');
                renderFiles();
            };

            // File biasa: tanpa folder. Hasil "Pilih folder": jalur diambil dari webkitRelativePath.
            var itemsFromFiles = function (files) {
                return Array.prototype.map.call(files, function (file) {
                    var parts = (file.webkitRelativePath || '').split('/');
                    parts.pop();
                    return { file: file, dir: parts.join('/') };
                });
            };

            var foldersOf = function (items) {
                var folders = {};
                items.forEach(function (item) {
                    var parts = item.dir ? item.dir.split('/') : [];
                    for (var i = 1; i <= parts.length; i++) folders[parts.slice(0, i).join('/')] = true;
                });
                return Object.keys(folders);
            };

            // Isi drop dari File Explorer: folder dibaca isinya (termasuk subfolder & folder kosong).
            var readDrop = function (dataTransfer) {
                var entries = Array.prototype.map.call(dataTransfer.items || [], function (item) {
                    return item.kind === 'file' && item.webkitGetAsEntry ? item.webkitGetAsEntry() : null;
                }).filter(Boolean);
                if (!entries.some(function (entry) { return entry.isDirectory; })) {
                    var items = itemsFromFiles(dataTransfer.files);
                    return Promise.resolve({ items: items, folders: [] });
                }

                var result = { items: [], folders: [] };
                var walk = function (entry, dir) {
                    if (entry.isFile) {
                        return new Promise(function (resolve, reject) {
                            entry.file(function (file) { result.items.push({ file: file, dir: dir }); resolve(); }, reject);
                        });
                    }
                    var path = dir ? dir + '/' + entry.name : entry.name;
                    result.folders.push(path);
                    var reader = entry.createReader();
                    // readEntries mengembalikan isi folder sedikit demi sedikit; dipanggil sampai kosong.
                    var readAll = function (collected) {
                        return new Promise(function (resolve, reject) { reader.readEntries(resolve, reject); }).then(function (batch) {
                            return batch.length ? readAll(collected.concat(batch)) : collected;
                        });
                    };
                    return readAll([]).then(function (children) {
                        return children.reduce(function (chain, child) {
                            return chain.then(function () { return walk(child, path); });
                        }, Promise.resolve());
                    });
                };

                return entries.reduce(function (chain, entry) {
                    return chain.then(function () { return walk(entry, ''); });
                }, Promise.resolve()).then(function () { return result; });
            };

            var openUpload = function () {
                if (!uploadModal.open) uploadModal.showModal();
            };

            var useDrop = function (dataTransfer) {
                openUpload();
                showUploadError('');
                showProgress('Membaca isi folder...');
                readDrop(dataTransfer).then(function (result) {
                    hideProgress();
                    setSelection(result.items, result.folders);
                }).catch(function () {
                    hideProgress();
                    showUploadError('Folder tidak bisa dibaca. Coba pakai tombol "Pilih folder...".');
                });
            };

            input.addEventListener('change', function () { setSelection(itemsFromFiles(input.files), []); });
            folderInput.addEventListener('change', function () {
                var items = itemsFromFiles(folderInput.files);
                setSelection(items, foldersOf(items));
            });
            document.getElementById('upload-folder-pick').addEventListener('click', function () {
                folderInput.value = '';
                folderInput.click();
            });

            document.querySelector('[data-upload]').addEventListener('click', function () {
                input.value = '';
                setSelection([], []);
                openUpload();
            });

            ['dragenter', 'dragover'].forEach(function (type) {
                dropzone.addEventListener(type, function (event) { event.preventDefault(); dropzone.classList.add('over'); });
            });
            ['dragleave', 'drop'].forEach(function (type) {
                dropzone.addEventListener(type, function () { dropzone.classList.remove('over'); });
            });
            dropzone.addEventListener('drop', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (!uploadModal.dataset.busy) useDrop(event.dataTransfer);
            });

            var dragDepth = 0;
            var hasFiles = function (event) { return event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') !== -1; };
            window.addEventListener('dragenter', function (event) {
                if (!hasFiles(event) || uploadModal.open) return;
                dragDepth++;
                overlay.classList.add('show');
            });
            window.addEventListener('dragleave', function () {
                dragDepth = Math.max(dragDepth - 1, 0);
                if (!dragDepth) overlay.classList.remove('show');
            });
            window.addEventListener('dragover', function (event) { if (hasFiles(event)) event.preventDefault(); });
            window.addEventListener('drop', function (event) {
                if (!hasFiles(event)) return;
                event.preventDefault();
                dragDepth = 0;
                overlay.classList.remove('show');
                if (!uploadModal.dataset.busy) useDrop(event.dataTransfer);
            });

            // Folder kosong dikirim terpisah; folder yang berisi file sudah ikut terbentuk dari jalur file-nya.
            var emptyFolders = function () {
                var used = foldersOf(selection.items);
                return selection.folders.filter(function (folder) {
                    return used.indexOf(folder) === -1 && !selection.folders.some(function (other) { return other.indexOf(folder + '/') === 0; });
                });
            };

            var buildBatches = function () {
                var batches = [];
                var current = null;
                selection.items.forEach(function (item) {
                    if (!current || current.items.length >= maxFilesPerRequest || (current.items.length && current.bytes + item.file.size > maxBytesPerRequest)) {
                        current = { items: [], folders: [], bytes: 0 };
                        batches.push(current);
                    }
                    current.items.push(item);
                    current.bytes += item.file.size;
                });
                var folders = emptyFolders();
                for (var i = 0; i < folders.length; i += maxFoldersPerRequest) {
                    var chunk = folders.slice(i, i + maxFoldersPerRequest);
                    if (i === 0 && batches.length) batches[0].folders = chunk;
                    else batches.push({ items: [], folders: chunk, bytes: 0 });
                }
                return batches;
            };

            var errorOf = function (xhr) {
                if (xhr.status === 0) return 'Koneksi terputus, coba lagi.';
                if (xhr.status === 419) return 'Sesi habis, muat ulang halaman.';
                if (xhr.status === 413) return 'Ukuran upload terlalu besar untuk server.';
                try {
                    var body = JSON.parse(xhr.responseText);
                    var errors = body.errors ? Object.values(body.errors) : [];
                    return errors.length ? errors[0][0] : (body.message || 'Upload gagal, coba lagi.');
                } catch (error) {
                    return 'Upload gagal, coba lagi.';
                }
            };

            // XHR (bukan fetch) supaya progress upload per byte bisa ditampilkan.
            var postBatch = function (body, onProgress) {
                return new Promise(function (resolve, reject) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', uploadForm.action);
                    xhr.setRequestHeader('Accept', 'application/json');
                    xhr.upload.addEventListener('progress', function (event) {
                        if (event.lengthComputable) onProgress(event.loaded / event.total);
                    });
                    xhr.addEventListener('load', function () {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try { resolve(JSON.parse(xhr.responseText)); } catch (error) { reject(new Error('Upload gagal, coba lagi.')); }
                        } else {
                            reject(new Error(errorOf(xhr)));
                        }
                    });
                    xhr.addEventListener('error', function () { reject(new Error(errorOf(xhr))); });
                    xhr.send(body);
                });
            };

            var resetSubmit = function () {
                delete uploadModal.dataset.busy;
                uploadSubmit.disabled = false;
                uploadSubmit.classList.remove('loading');
                uploadSubmit.removeAttribute('aria-busy');
                uploadSubmit.textContent = 'Upload';
                uploadModal.querySelectorAll('[data-close]').forEach(function (close) { close.disabled = false; });
            };

            uploadModal.addEventListener('close', function () { if (reloadOnClose) window.location.reload(); });

            uploadForm.addEventListener('submit', function (event) {
                event.preventDefault();
                if (!selection.items.length && !selection.folders.length) {
                    showUploadError('Pilih minimal satu file atau folder.');
                    return;
                }
                var tooBig = selection.items.filter(function (item) { return item.file.size > maxBytes; });
                if (tooBig.length) {
                    showUploadError('Ukuran tiap file maksimal ' + (maxBytes / 1048576) + ' MB: ' + tooBig.map(function (item) { return item.file.name; }).join(', '));
                    return;
                }

                showUploadError('');
                var batches = buildBatches();
                var total = selection.items.length;
                var totalBytes = selection.items.reduce(function (sum, item) { return sum + item.file.size; }, 0) || 1;
                var uploaded = 0;
                var uploadedBytes = 0;
                var foldersCreated = 0;
                setLoading(uploadSubmit, 'Mengupload...');
                uploadModal.dataset.busy = '1';

                var report = function (batchRatio, batch) {
                    if (!total) {
                        showProgress('Membuat folder...');
                        return;
                    }
                    var ratio = (uploadedBytes + batch.bytes * batchRatio) / totalBytes;
                    var message = batchRatio >= 1
                        ? 'Menyimpan ' + Math.min(uploaded + batch.items.length, total) + ' dari ' + total + ' file...'
                        : 'Mengupload ' + uploaded + ' dari ' + total + ' file... ' + Math.round(ratio * 100) + '%';
                    showProgress(message, ratio);
                    uploadSubmit.lastChild.textContent = 'Mengupload ' + Math.round(ratio * 100) + '%';
                };

                var send = function (index) {
                    if (index >= batches.length) {
                        showProgress('Selesai! Memuat ulang halaman...', 1);
                        window.location.reload();
                        return;
                    }
                    var batch = batches[index];
                    report(0, batch);

                    var body = new FormData(uploadForm);
                    batch.items.forEach(function (item, i) {
                        body.append('files[' + i + ']', item.file, item.file.name);
                        body.append('folder_paths[' + i + ']', item.dir);
                    });
                    batch.folders.forEach(function (folder) { body.append('folders[]', folder); });
                    body.append('sudah_diupload', uploaded);
                    body.append('folder_dibuat', foldersCreated);

                    postBatch(body, function (batchRatio) { report(batchRatio, batch); }).then(function (result) {
                        uploaded += result.uploaded;
                        uploadedBytes += batch.bytes;
                        foldersCreated += result.folders_created;
                        send(index + 1);
                    }).catch(function (error) {
                        hideProgress();
                        resetSubmit();
                        // Kiriman sebelumnya sudah tersimpan: halaman dimuat ulang saat modal ditutup.
                        if (uploaded || foldersCreated) {
                            reloadOnClose = true;
                            selection.items = selection.items.slice(uploaded);
                            renderFiles();
                        }
                        showUploadError(error.message + (uploaded ? ' (' + uploaded + ' file sebelumnya sudah tersimpan.)' : ''));
                    });
                };
                send(0);
            });
        }

        // ===== Pindah file: tarik kartu file ke kartu folder / breadcrumb =====
        var draggedCard = null;
        var clearDropTargets = function () {
            document.querySelectorAll('.drop-target').forEach(function (target) { target.classList.remove('drop-target'); });
        };
        document.querySelectorAll('[data-move-url]').forEach(function (card) {
            card.addEventListener('dragstart', function (event) {
                draggedCard = card;
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('application/x-iso-document', card.dataset.moveUrl);
                card.classList.add('dragging');
            });
            card.addEventListener('dragend', function () {
                draggedCard = null;
                card.classList.remove('dragging');
                clearDropTargets();
            });
        });
        document.querySelectorAll('[data-drop-folder]').forEach(function (target) {
            var accepts = function () { return draggedCard && draggedCard.dataset.folderId !== target.dataset.dropFolder; };
            target.addEventListener('dragover', function (event) {
                if (!accepts()) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                target.classList.add('drop-target');
            });
            target.addEventListener('dragleave', function (event) {
                if (!target.contains(event.relatedTarget)) target.classList.remove('drop-target');
            });
            target.addEventListener('drop', function (event) {
                if (!accepts()) return;
                event.preventDefault();
                event.stopPropagation();
                var card = draggedCard;
                clearDropTargets();
                card.style.pointerEvents = 'none';
                fetch(card.dataset.moveUrl, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ folder_id: target.dataset.dropFolder || null }),
                }).then(function (response) {
                    if (response.ok) { window.location.reload(); return; }
                    return response.json().catch(function () { return {}; }).then(function (body) {
                        var errors = body.errors ? Object.values(body.errors) : [];
                        throw new Error(response.status === 419 ? 'Sesi habis, muat ulang halaman.' : (errors.length ? errors[0][0] : (body.message || 'Gagal memindahkan file.')));
                    });
                }).catch(function (error) {
                    card.style.pointerEvents = '';
                    alert(error.message);
                });
            });
        });

        var folderModal = document.getElementById('folder-modal');
        if (!folderModal) return;

        var routes = {
            folderStore: {{ Js::from(route("iso.{$kategori}.folders.store")) }},
            folderUpdate: {{ Js::from(route("iso.{$kategori}.folders.update", '__ID__')) }},
            folderDestroy: {{ Js::from(route("iso.{$kategori}.folders.destroy", '__ID__')) }},
            update: {{ Js::from(route("iso.{$kategori}.update", '__ID__')) }},
            destroy: {{ Js::from(route("iso.{$kategori}.destroy", '__ID__')) }},
        };
        var insideFolder = {{ Js::from($currentFolder !== null) }};

        function recordOf(attribute, id) {
            var button = document.querySelector('[' + attribute + '="' + id + '"]');
            return button ? JSON.parse(button.dataset.record) : null;
        }

        // ===== Folder: buat & ganti nama =====
        var folderForm = document.getElementById('folder-form');

        function setDepartmentVisible(field, note, visible) {
            document.getElementById(field).hidden = !visible;
            document.getElementById(note).hidden = visible;
            var select = document.getElementById(field).querySelector('select');
            if (select) select.disabled = !visible;
        }

        function openFolder(id, record, keepErrors) {
            folderForm.action = id ? routes.folderUpdate.replace('__ID__', id) : routes.folderStore;
            folderForm.elements._method.value = id ? 'PUT' : 'POST';
            folderForm.elements._modal.value = id ? 'folder-edit:' + id : 'folder-create';
            folderForm.elements.parent_id.disabled = !!id;
            document.getElementById('folder-modal-title').textContent = id ? 'Edit Folder' : 'Folder Baru';
            folderForm.elements.nama.value = (record && record.nama) || '';
            // Departemen dipilih di folder paling atas saja (folder baru di halaman utama, atau edit folder paling atas).
            var root = id ? !!(record && record.root) : !insideFolder;
            setDepartmentVisible('folder-department-field', 'folder-department-note', root);
            var department = folderForm.querySelector('#folder-department');
            if (department && record) department.value = record.department_id || '';
            if (!keepErrors) clearErrors(folderModal);
            folderModal.showModal();
            folderForm.elements.nama.focus();
        }

        var createFolder = document.querySelector('[data-create-folder]');
        if (createFolder) createFolder.addEventListener('click', function () { openFolder(null, null); });
        document.querySelectorAll('[data-edit-folder]').forEach(function (button) {
            button.addEventListener('click', function () { openFolder(button.dataset.editFolder, recordOf('data-edit-folder', button.dataset.editFolder)); });
        });

        // ===== Dokumen: edit (nama, folder, departemen, ganti file) =====
        var editModal = document.getElementById('edit-modal');
        var editForm = document.getElementById('edit-form');
        var folderSelect = editForm.elements.folder_id;

        // Di dalam folder, departemen ikut folder; pilihan departemen hanya untuk file di luar folder.
        function syncEditDepartment() {
            setDepartmentVisible('edit-department-field', 'edit-department-note', folderSelect.value === '');
        }
        folderSelect.addEventListener('change', syncEditDepartment);

        function openEdit(id, record, keepErrors) {
            editForm.action = routes.update.replace('__ID__', id);
            editForm.elements._modal.value = 'edit:' + id;
            editForm.elements.nama.value = record.nama || '';
            folderSelect.value = record.folder_id || '';
            var department = editForm.querySelector('#edit-department');
            if (department) department.value = record.department_id || '';
            editForm.elements.file.value = '';
            syncEditDepartment();
            if (!keepErrors) clearErrors(editModal);
            editModal.showModal();
            editForm.elements.nama.focus();
        }

        document.querySelectorAll('[data-edit]').forEach(function (button) {
            button.addEventListener('click', function () { openEdit(button.dataset.edit, recordOf('data-edit', button.dataset.edit)); });
        });

        // ===== Hapus dokumen / folder =====
        var deleteModal = document.getElementById('delete-modal');
        function openDelete(action, title, message) {
            document.getElementById('delete-form').action = action;
            document.getElementById('delete-title').textContent = title;
            document.getElementById('delete-body').innerHTML = message;
            deleteModal.showModal();
        }
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        document.querySelectorAll('[data-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                openDelete(routes.destroy.replace('__ID__', button.dataset.delete), 'Hapus Dokumen',
                    'Hapus <strong>' + escapeHtml(button.dataset.name) + '</strong>? File akan dihapus permanen.');
            });
        });
        document.querySelectorAll('[data-delete-folder]').forEach(function (button) {
            button.addEventListener('click', function () {
                openDelete(routes.folderDestroy.replace('__ID__', button.dataset.deleteFolder), 'Hapus Folder',
                    'Hapus folder <strong>' + escapeHtml(button.dataset.name) + '</strong> (' + escapeHtml(button.dataset.isi) + ')?'
                    + '<br><span class="muted small">Semua subfolder & file di dalamnya ikut terhapus permanen.</span>');
            });
        });

        // ===== Validasi gagal: buka lagi modal yang tadi dipakai =====
        var reopen = {{ Js::from($errors->any() ? old('_modal') : null) }};
        var old = {{ Js::from(collect(old())->only(['nama', 'department_id', 'folder_id'])) }};
        if (reopen === 'upload' && uploadModal) {
            uploadModal.showModal();
        } else if (reopen === 'folder-create') {
            openFolder(null, old, true);
        } else if (reopen && reopen.indexOf('folder-edit:') === 0 && recordOf('data-edit-folder', reopen.slice(12))) {
            var folderId = reopen.slice(12);
            openFolder(folderId, Object.assign({}, recordOf('data-edit-folder', folderId), old), true);
        } else if (reopen && reopen.indexOf('edit:') === 0 && recordOf('data-edit', reopen.slice(5))) {
            var editId = reopen.slice(5);
            openEdit(editId, Object.assign({}, recordOf('data-edit', editId), old), true);
        }
    })();
</script>
@endpush
