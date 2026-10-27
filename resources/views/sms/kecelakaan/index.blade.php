@extends('layouts.app')

@section('title', 'Kecelakaan')

@section('styles')
    @include('sms.partials.patrol-styles')
    input[type=datetime-local] { width: 100%; padding: 11px 14px; font: inherit; font-size: 14px; color: var(--text); background: var(--glass-strong); border: 1px solid var(--line-strong); border-radius: 12px; }
    table.patrol .c-desc { max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); }
    .tipe { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .tipe-orange { background: #ffedd5; color: #c2410c; }
    .tipe-blue { background: #dbeafe; color: #1d4ed8; }
    #kecelakaan-modal { width: min(640px, calc(100% - 32px)); }
    #kecelakaan-modal .modal-body { display: grid; gap: 14px; }
    #kecelakaan-detail { width: min(560px, calc(100% - 32px)); }
@endsection

@section('content')
@php
    $fields = ['tipe', 'kategori', 'waktu', 'nama_korban', 'department_id', 'deskripsi'];
    $listFilters = array_filter(['q' => $search, 'tipe' => $tipe, 'department' => $department]);
    $keepFilters = $listFilters + array_filter(['page' => $kecelakaans->currentPage() > 1 ? $kecelakaans->currentPage() : null]);
@endphp

<div class="dp-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Kecelakaan</h1>
        <p class="muted" style="margin: 2px 0 0;">
            Laporan work accident & traffic accident
            @if ($lockedDepartment)
                · departemen <span class="badge">{{ $lockedDepartment->name }}</span>
            @endif
        </p>
    </div>
    @if ($canManage)
        <button type="button" class="btn" data-create>+ Tambah Kecelakaan</button>
    @endif
</div>

@if ($missingDepartment)
    <div class="alert">Akun Anda adalah Admin Departemen SMS tetapi belum dipilihkan departemen. Hubungi Super Admin.</div>
@endif

<form method="GET" class="filters">
    <select name="tipe" onchange="this.form.requestSubmit()" aria-label="Tipe">
        <option value="">Semua tipe</option>
        @foreach ($tipes as $kode => $info)
            <option value="{{ $kode }}" @selected($tipe === $kode)>{{ $info['label'] }}</option>
        @endforeach
    </select>
    @if ($isAdmin)
        <select name="department" onchange="this.form.requestSubmit()" aria-label="Departemen">
            <option value="">Semua departemen</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    @endif
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama korban / deskripsi...">
    <div class="summary">
        <span class="chip">Total <b>{{ $kecelakaans->total() }}</b></span>
    </div>
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table class="patrol">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tipe</th>
                    <th>Kategori</th>
                    <th>Waktu</th>
                    <th>Nama Korban</th>
                    <th>Departemen</th>
                    <th>Deskripsi</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($kecelakaans as $kecelakaan)
                    <tr>
                        <td class="muted">{{ $kecelakaans->firstItem() + $loop->index }}</td>
                        <td><span class="tipe tipe-{{ $tipes[$kecelakaan->tipe]['tone'] ?? 'orange' }}">{{ $kecelakaan->tipeLabel() }}</span></td>
                        <td class="nowrap">{{ $kecelakaan->kategoriLabel() ?? '-' }}</td>
                        <td class="nowrap">{{ $kecelakaan->waktu->format('d M Y H:i') }}</td>
                        <td style="font-weight: 600;">{{ $kecelakaan->nama_korban }}</td>
                        <td class="nowrap">{{ $kecelakaan->department?->name ?? '-' }}</td>
                        <td class="c-desc" title="{{ $kecelakaan->deskripsi }}">{{ $kecelakaan->deskripsi }}</td>
                        <td style="text-align: right;">
                            <div class="act">
                                <button type="button" class="act-btn" data-detail="{{ $kecelakaan->id }}" title="Detail" aria-label="Detail">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                                @if ($canEdit)
                                    <button type="button" class="act-btn" data-edit="{{ $kecelakaan->id }}" title="Edit" aria-label="Edit"
                                            data-record="{{ json_encode([
                                                'tipe' => $kecelakaan->tipe,
                                                'kategori' => $kecelakaan->kategori,
                                                'waktu' => $kecelakaan->waktu->format('Y-m-d\TH:i'),
                                                'nama_korban' => $kecelakaan->nama_korban,
                                                'department_id' => (string) $kecelakaan->department_id,
                                                'deskripsi' => $kecelakaan->deskripsi,
                                            ]) }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                    </button>
                                @endif
                                @if ($canManage)
                                    <button type="button" class="act-btn act-danger" data-delete="{{ $kecelakaan->id }}" data-name="{{ $kecelakaan->nama_korban }} ({{ $kecelakaan->waktu->format('d/m/Y H:i') }})" title="Hapus" aria-label="Hapus">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>
                                    </button>
                                @endif
                            </div>
                            <template id="detail-{{ $kecelakaan->id }}">
                                <dl class="detail-list">
                                    <div><dt>Tipe</dt><dd>{{ $kecelakaan->tipeLabel() }}</dd></div>
                                    <div><dt>Kategori</dt><dd>{{ $kecelakaan->kategoriLabel() ?? '-' }}</dd></div>
                                    <div><dt>Waktu</dt><dd>{{ $kecelakaan->waktu->translatedFormat('l, d F Y H:i') }}</dd></div>
                                    <div><dt>Departemen</dt><dd>{{ $kecelakaan->department?->name ?? '-' }}</dd></div>
                                    <div class="wide"><dt>Nama Korban</dt><dd>{{ $kecelakaan->nama_korban }}</dd></div>
                                    <div class="wide"><dt>Deskripsi</dt><dd style="white-space: pre-line;">{{ $kecelakaan->deskripsi }}</dd></div>
                                </dl>
                            </template>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted" style="text-align: center; padding: 32px;">
                            {{ $listFilters ? 'Data tidak ditemukan.' : 'Belum ada laporan kecelakaan.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $kecelakaans->links() }}
</div>

{{-- Modal detail --}}
<dialog class="modal" id="kecelakaan-detail">
    <div class="modal-head">
        <h2>Detail Kecelakaan</h2>
        <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close>Tutup</button>
        @if ($canEdit)
            <button type="button" class="btn" data-detail-edit>Edit</button>
        @endif
    </div>
</dialog>

@if ($canEdit)
    {{-- Modal tambah / edit --}}
    <dialog class="modal" id="kecelakaan-modal">
        <form method="POST" action="{{ route('sms.kecelakaan.store') }}" id="kecelakaan-form">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="create">
            @foreach ($keepFilters as $key => $value)
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2 id="kecelakaan-modal-title">Tambah Kecelakaan</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div class="grid-2">
                    <div>
                        <label for="f-tipe">Tipe Kecelakaan <span style="color: var(--danger);">*</span></label>
                        <select id="f-tipe" name="tipe" required>
                            <option value="">-- Pilih Tipe --</option>
                            @foreach ($tipes as $kode => $info)
                                <option value="{{ $kode }}">{{ $info['label'] }}</option>
                            @endforeach
                        </select>
                        @error('tipe') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    {{-- Kategori hanya untuk Traffic Accident. --}}
                    <div id="kategori-field" hidden>
                        <label for="f-kategori">Kategori <span style="color: var(--danger);">*</span></label>
                        <select id="f-kategori" name="kategori">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach ($kategoris as $kode => $label)
                                <option value="{{ $kode }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('kategori') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label for="f-waktu">Waktu <span style="color: var(--danger);">*</span></label>
                        <input id="f-waktu" type="datetime-local" name="waktu" required>
                        @error('waktu') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-department_id">Departemen <span style="color: var(--danger);">*</span></label>
                        @if ($isAdmin)
                            <select id="f-department_id" name="department_id" required>
                                <option value="">-- Pilih Departemen --</option>
                                @foreach ($departments as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="locked-field">{{ $lockedDepartment?->name ?? '-' }}</div>
                        @endif
                        @error('department_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div>
                    <label for="f-nama_korban">Nama Korban <span style="color: var(--danger);">*</span></label>
                    <input id="f-nama_korban" type="text" name="nama_korban" maxlength="255" required>
                    @error('nama_korban') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="f-deskripsi">Deskripsi <span style="color: var(--danger);">*</span></label>
                    <textarea id="f-deskripsi" name="deskripsi" maxlength="5000" rows="4" placeholder="Kronologi singkat kejadian" required></textarea>
                    @error('deskripsi') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn">Simpan</button>
            </div>
        </form>
    </dialog>
@endif

@if ($canManage)
    {{-- Modal hapus --}}
    <dialog class="modal" id="delete-modal">
        <form method="POST" id="delete-form">
            @csrf
            @method('DELETE')
            @foreach ($keepFilters as $key => $value)
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2>Hapus Kecelakaan</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">Hapus laporan kecelakaan <strong id="delete-name"></strong>?</div>
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
        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () { dialog.close(); });
            });
            dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
        });

        // Detail
        var detail = document.getElementById('kecelakaan-detail');
        var detailId = null;
        document.querySelectorAll('[data-detail]').forEach(function (button) {
            button.addEventListener('click', function () {
                detailId = button.dataset.detail;
                var body = document.getElementById('detail-body');
                body.innerHTML = '';
                body.appendChild(document.getElementById('detail-' + detailId).content.cloneNode(true));
                detail.showModal();
            });
        });

        var modal = document.getElementById('kecelakaan-modal');
        if (!modal) return;

        var fields = {{ Js::from($fields) }};
        var form = document.getElementById('kecelakaan-form');
        var storeUrl = {{ Js::from(route('sms.kecelakaan.store')) }};
        var updateUrl = {{ Js::from(route('sms.kecelakaan.update', '__ID__')) }};
        var destroyUrl = {{ Js::from(route('sms.kecelakaan.destroy', '__ID__')) }};
        var kategoriField = document.getElementById('kategori-field');

        // Kategori hanya muncul (dan wajib) untuk Traffic Accident.
        function syncKategori() {
            var traffic = form.elements.tipe.value === 'traffic';
            kategoriField.hidden = !traffic;
            form.elements.kategori.required = traffic;
            if (!traffic) form.elements.kategori.value = '';
        }
        form.elements.tipe.addEventListener('change', syncKategori);

        function nowLocal() {
            var d = new Date();
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            return d.toISOString().slice(0, 16);
        }

        function openForm(id, record, keepErrors) {
            form.reset();
            form.action = id ? updateUrl.replace('__ID__', id) : storeUrl;
            form.elements._method.value = id ? 'PUT' : 'POST';
            form.elements._modal.value = id ? 'edit:' + id : 'create';
            document.getElementById('kecelakaan-modal-title').textContent = id ? 'Edit Kecelakaan' : 'Tambah Kecelakaan';
            fields.forEach(function (name) {
                var el = form.elements[name];
                if (el) el.value = (record && record[name]) || '';
            });
            if (!form.elements.waktu.value) form.elements.waktu.value = nowLocal();
            syncKategori();
            if (record && record.kategori) form.elements.kategori.value = record.kategori;
            if (!keepErrors) modal.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });
            modal.showModal();
            form.elements.tipe.focus();
        }

        function recordOf(id) {
            var button = document.querySelector('[data-edit="' + id + '"]');
            return button ? JSON.parse(button.dataset.record) : null;
        }

        var createButton = document.querySelector('[data-create]');
        if (createButton) createButton.addEventListener('click', function () { openForm(null, null); });
        document.querySelectorAll('[data-edit]').forEach(function (button) {
            button.addEventListener('click', function () { openForm(button.dataset.edit, recordOf(button.dataset.edit)); });
        });
        detail.querySelector('[data-detail-edit]').addEventListener('click', function () {
            detail.close();
            openForm(detailId, recordOf(detailId));
        });
        document.querySelectorAll('[data-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('delete-form').action = destroyUrl.replace('__ID__', button.dataset.delete);
                document.getElementById('delete-name').textContent = button.dataset.name;
                document.getElementById('delete-modal').showModal();
            });
        });

        // Validasi gagal: buka lagi modal yang tadi dipakai beserta isiannya.
        var reopen = {{ Js::from($errors->hasAny($fields) ? old('_modal') : null) }};
        if (reopen) {
            var editId = reopen.indexOf('edit:') === 0 ? reopen.slice(5) : null;
            openForm(editId, Object.assign({}, editId ? recordOf(editId) : {}, {{ Js::from(collect(old())->only($fields)) }}), true);
        }
    })();
</script>
@endpush
