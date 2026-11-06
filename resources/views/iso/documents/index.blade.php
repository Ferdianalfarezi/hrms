@extends('layouts.app')

@section('title', $judul)

@section('styles')
    .iso-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .iso-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
    .iso-filters input[type=search] { width: 260px; border-radius: 999px; }
    .iso-filters select { width: auto; min-width: 150px; border-radius: 999px; }
    .iso-filters .total { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 13px; background: var(--glass-strong); }

    .doc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; }
    .doc-card { display: flex; flex-direction: column; border-radius: 16px; background: #fff; border: 1px solid var(--line-strong); overflow: hidden; transition: box-shadow .15s, transform .15s; }
    .doc-card:hover { box-shadow: 0 10px 24px rgba(15, 35, 80, .12); transform: translateY(-2px); }
    .doc-thumb { position: relative; display: grid; place-items: center; height: 150px; overflow: hidden; }
    .doc-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    /* Preview PDF: halaman pertama dirender browser; scrollbar didorong keluar area kartu. */
    .doc-pdf { position: absolute; top: 0; left: 0; width: calc(100% + 20px); height: calc(100% + 20px); border: 0; background: #fff; pointer-events: none; }
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
    .doc-empty { padding: 48px 16px; text-align: center; color: var(--muted); border: 2px dashed var(--line-strong); border-radius: 18px; }

    #upload-modal, #edit-modal { width: min(560px, calc(100% - 32px)); }
    #upload-modal .modal-body, #edit-modal .modal-body { display: grid; gap: 14px; }
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
@endsection

@section('content')
@php
    $listFilters = array_filter(['q' => $search, 'jenis' => $jenis, 'department' => $department]);
    $keepFilters = $listFilters + array_filter(['page' => $documents->currentPage() > 1 ? $documents->currentPage() : null]);
    $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg>';
@endphp

<div class="iso-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">{{ $judul }}</h1>
        <p class="muted" style="margin: 2px 0 0;">
            Dokumen ISO
            @if ($lockedDepartments->isNotEmpty())
                · departemen
                @foreach ($lockedDepartments as $lockedDepartment)
                    <span class="badge">{{ $lockedDepartment->name }}</span>
                @endforeach
                + dokumen umum
            @endif
        </p>
    </div>
    @if ($canUpload)
        <button type="button" class="btn" data-upload>+ Upload File</button>
    @endif
</div>

@if ($missingDepartment)
    <div class="alert">Akun Anda adalah Admin Departemen ISO tetapi belum dipilihkan departemen. Hubungi Super Admin.</div>
@endif

<form method="GET" class="iso-filters">
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama dokumen...">
    <select name="jenis" onchange="this.form.requestSubmit()" aria-label="Jenis file">
        <option value="">Semua jenis</option>
        @foreach (\App\Modules\Iso\Models\IsoDocument::JENIS as $kode => $info)
            <option value="{{ $kode }}" @selected($jenis === $kode)>{{ $info['label'] }}</option>
        @endforeach
        <option value="lainnya" @selected($jenis === 'lainnya')>Lainnya</option>
    </select>
    <select name="department" onchange="this.form.requestSubmit()" aria-label="Departemen">
        <option value="">Semua departemen</option>
        <option value="umum" @selected($department === 'umum')>Umum</option>
        @foreach ($departments as $option)
            <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
        @endforeach
    </select>
    <span class="total">Total <b>{{ $documents->total() }}</b> file</span>
</form>

@if ($documents->isEmpty())
    <div class="doc-empty">
        {{ $listFilters ? 'Dokumen tidak ditemukan.' : 'Belum ada dokumen di '.$judul.'.' }}
        @if ($canUpload && ! $listFilters)
            <div class="small" style="margin-top: 6px;">Klik <b>+ Upload File</b> atau tarik file ke halaman ini.</div>
        @endif
    </div>
@else
    <div class="doc-grid">
        @foreach ($documents as $document)
            @php
                $jenisFile = $document->jenis();
                $tone = \App\Modules\Iso\Models\IsoDocument::JENIS[$jenisFile]['tone'] ?? 'gray';
                $fileUrl = route("iso.{$kategori}.file", $document);
                $canManage = $isAdmin || in_array($document->department_id, $managedDepartmentIds, true);
            @endphp
            <div class="doc-card">
                <div class="doc-thumb tone-{{ $tone }}">
                    @if ($jenisFile === 'gambar')
                        <img src="{{ $fileUrl }}" alt="" loading="lazy">
                    @elseif ($jenisFile === 'pdf')
                        {!! $icon !!}
                        <iframe class="doc-pdf" src="{{ $fileUrl }}#toolbar=0&navpanes=0&scrollbar=0&view=FitH" loading="lazy" tabindex="-1" title="Preview {{ $document->nama }}"></iframe>
                    @else
                        {!! $icon !!}
                    @endif
                    <span class="doc-ext">{{ strtoupper($document->extension ?? 'FILE') }}</span>
                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="doc-open" title="Buka {{ $document->nama }}" aria-label="Buka {{ $document->nama }}"></a>
                </div>
                <div class="doc-body">
                    <div class="doc-name" title="{{ $document->nama }}">{{ $document->nama }}</div>
                    <div class="doc-meta">
                        <span class="badge">{{ $document->department?->name ?? 'Umum' }}</span>
                        <span style="white-space: nowrap;">{{ $document->sizeLabel() }} · {{ $document->created_at->translatedFormat('d M Y') }}</span>
                    </div>
                    @if ($document->creator)
                        <div class="doc-meta" title="Diupload oleh">oleh {{ $document->creator->name }}</div>
                    @endif
                </div>
                <div class="doc-actions">
                    <a href="{{ $fileUrl }}?download=1" class="doc-btn" title="Unduh" aria-label="Unduh">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>
                    </a>
                    @if ($canManage)
                        <button type="button" class="doc-btn" title="Edit" aria-label="Edit" data-edit="{{ $document->id }}"
                                data-record="{{ json_encode(['nama' => $document->nama, 'department_id' => (string) $document->department_id]) }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                        </button>
                        <button type="button" class="doc-btn danger" title="Hapus" aria-label="Hapus" data-delete="{{ $document->id }}" data-name="{{ $document->nama }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top: 16px;">{{ $documents->links() }}</div>
@endif

@if ($canUpload)
    <div class="drop-overlay" id="drop-overlay">Lepaskan file untuk upload ke {{ $judul }}</div>

    {{-- Modal upload: bisa beberapa file sekaligus, semua jenis file. --}}
    <dialog class="modal" id="upload-modal">
        <form method="POST" action="{{ route("iso.{$kategori}.store") }}" enctype="multipart/form-data" id="upload-form">
            @csrf
            <input type="hidden" name="_modal" value="upload">
            @foreach ($keepFilters as $key => $value)
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2>Upload ke {{ $judul }}</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <label class="dropzone" id="dropzone">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>
                    <strong>Klik untuk pilih file atau tarik ke sini</strong>
                    <span class="muted small">PDF, Word, Excel, PowerPoint, gambar, dll · maks. {{ \App\Modules\Iso\Models\IsoDocument::MAX_KB / 1024 }} MB per file, {{ \App\Modules\Iso\Models\IsoDocument::MAX_FILES }} file sekali upload</span>
                    <input type="file" name="files[]" id="upload-input" multiple required>
                </label>
                <div class="file-list" id="file-list"></div>
                @if ($errors->has('files') || $errors->has('files.*'))
                    <div class="field-error">{{ $errors->first('files') ?: $errors->first('files.*') }}</div>
                @endif
                <div>
                    <label for="upload-department">Departemen</label>
                    @if ($isAdmin)
                        <select id="upload-department" name="department_id">
                            <option value="">Umum (semua departemen)</option>
                            @foreach ($departments as $option)
                                <option value="{{ $option->id }}" @selected(old('department_id') == $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    @elseif ($departments->count() > 1)
                        <select id="upload-department" name="department_id" required>
                            @foreach ($departments as $option)
                                <option value="{{ $option->id }}" @selected(old('department_id') == $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="locked-dept">{{ $departments->first()?->name ?? '-' }}</div>
                    @endif
                    @error('department_id') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" id="upload-submit">Upload</button>
            </div>
        </form>
    </dialog>

    {{-- Modal edit: ganti nama, departemen, atau file. --}}
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
                    <label for="edit-department">Departemen</label>
                    @if ($isAdmin)
                        <select id="edit-department" name="department_id">
                            <option value="">Umum (semua departemen)</option>
                            @foreach ($departments as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    @elseif ($departments->count() > 1)
                        <select id="edit-department" name="department_id" required>
                            @foreach ($departments as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="locked-dept">{{ $departments->first()?->name ?? '-' }}</div>
                    @endif
                    @error('department_id') <div class="field-error">{{ $message }}</div> @enderror
                </div>
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
                <h2>Hapus Dokumen</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">Hapus <strong id="delete-name"></strong>? File akan dihapus permanen.</div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" style="background: var(--danger);">Hapus</button>
            </div>
        </form>
    </dialog>
@endif
@endsection

@push('scripts')
<script>
    (function () {
        var uploadModal = document.getElementById('upload-modal');
        if (!uploadModal) return;

        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () { dialog.close(); });
            });
            dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
        });

        // Upload: daftar file terpilih + drag & drop (di kotak upload maupun di seluruh halaman).
        var input = document.getElementById('upload-input');
        var list = document.getElementById('file-list');
        var dropzone = document.getElementById('dropzone');
        var overlay = document.getElementById('drop-overlay');
        var maxBytes = {{ \App\Modules\Iso\Models\IsoDocument::MAX_KB }} * 1024;

        function sizeLabel(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function renderFiles() {
            list.innerHTML = '';
            Array.prototype.forEach.call(input.files, function (file) {
                var row = document.createElement('div');
                var name = document.createElement('span');
                var size = document.createElement('span');
                name.textContent = file.name;
                size.textContent = sizeLabel(file.size);
                if (file.size > maxBytes) size.style.color = 'var(--danger)';
                row.appendChild(name);
                row.appendChild(size);
                list.appendChild(row);
            });
        }
        input.addEventListener('change', renderFiles);

        function useFiles(files) {
            if (!files || !files.length) return;
            input.files = files;
            renderFiles();
        }

        function openUpload(files) {
            if (!uploadModal.open) uploadModal.showModal();
            useFiles(files);
        }

        document.querySelector('[data-upload]').addEventListener('click', function () {
            input.value = '';
            renderFiles();
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
            useFiles(event.dataTransfer.files);
        });

        var dragDepth = 0;
        function hasFiles(event) { return event.dataTransfer && Array.prototype.indexOf.call(event.dataTransfer.types, 'Files') !== -1; }
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
            openUpload(event.dataTransfer.files);
        });

        document.getElementById('upload-form').addEventListener('submit', function () {
            var submit = document.getElementById('upload-submit');
            submit.disabled = true;
            submit.textContent = 'Mengupload...';
        });

        // Edit & hapus
        var editModal = document.getElementById('edit-modal');
        var editForm = document.getElementById('edit-form');
        var updateUrl = {{ Js::from(route("iso.{$kategori}.update", '__ID__')) }};
        var destroyUrl = {{ Js::from(route("iso.{$kategori}.destroy", '__ID__')) }};

        function openEdit(id, record, keepErrors) {
            editForm.action = updateUrl.replace('__ID__', id);
            editForm.elements._modal.value = 'edit:' + id;
            editForm.elements.nama.value = record.nama || '';
            if (editForm.elements.department_id) editForm.elements.department_id.value = record.department_id || '';
            editForm.elements.file.value = '';
            if (!keepErrors) editModal.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });
            editModal.showModal();
            editForm.elements.nama.focus();
        }

        function recordOf(id) {
            var button = document.querySelector('[data-edit="' + id + '"]');
            return button ? JSON.parse(button.dataset.record) : null;
        }

        document.querySelectorAll('[data-edit]').forEach(function (button) {
            button.addEventListener('click', function () { openEdit(button.dataset.edit, recordOf(button.dataset.edit)); });
        });
        document.querySelectorAll('[data-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('delete-form').action = destroyUrl.replace('__ID__', button.dataset.delete);
                document.getElementById('delete-name').textContent = button.dataset.name;
                document.getElementById('delete-modal').showModal();
            });
        });

        // Validasi gagal: buka lagi modal yang tadi dipakai.
        var reopen = {{ Js::from($errors->any() ? old('_modal') : null) }};
        if (reopen === 'upload') {
            uploadModal.showModal();
        } else if (reopen && reopen.indexOf('edit:') === 0 && recordOf(reopen.slice(5))) {
            var editId = reopen.slice(5);
            openEdit(editId, Object.assign({}, recordOf(editId), {{ Js::from(collect(old())->only(['nama', 'department_id'])) }}), true);
        }
    })();
</script>
@endpush
