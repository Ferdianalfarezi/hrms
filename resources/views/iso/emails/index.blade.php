@extends('layouts.app')

@section('title', 'Email Notifikasi')

@section('styles')
    .em-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .em-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .em-filters input[type=search] { width: 280px; border-radius: 999px; }
    .em-filters .total { margin-left: auto; display: inline-flex; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 13px; background: var(--glass-strong); }
    table.em { font-size: 13px; }
    table.em td { vertical-align: middle; }
    tr.inactive td.fade { opacity: .45; }
    .act { display: inline-flex; gap: 4px; justify-content: flex-end; }
    .act-btn { display: inline-grid; place-items: center; width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--line-strong); background: var(--glass-strong); color: var(--muted); cursor: pointer; }
    .act-btn svg { width: 15px; height: 15px; }
    .act-btn:hover { background: #fff; color: var(--primary); border-color: var(--primary); }
    .act-btn.danger:hover { color: var(--danger); border-color: var(--danger); }
    .switch { position: relative; display: inline-flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: var(--muted); cursor: pointer; margin: 0; }
    .switch input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .switch .track { width: 34px; height: 20px; border-radius: 999px; background: var(--line-strong); position: relative; transition: background .15s; flex: none; }
    .switch .track::after { content: ""; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0, 0, 0, .2); transition: transform .15s; }
    .switch input:checked + .track { background: var(--success); }
    .switch input:checked + .track::after { transform: translateX(14px); }
    .switch input:focus-visible + .track { outline: 2px solid var(--primary); outline-offset: 2px; }
    #recipient-modal .modal-body { display: grid; gap: 14px; }
@endsection

@section('content')
<div class="em-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Email Notifikasi</h1>
        <p class="muted" style="margin: 2px 0 0;">Penerima notifikasi pembaruan dokumen ISO. Hanya email yang <b>aktif</b> yang bisa dikirimi.</p>
    </div>
    <button type="button" class="btn" data-create>+ Tambah Email</button>
</div>

<form method="GET" class="em-filters">
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari nama / email...">
    <span class="total">Aktif <b>{{ $activeCount }}</b> dari <b>{{ $recipients->total() }}</b></span>
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table class="em">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recipients as $recipient)
                    <tr @class(['inactive' => ! $recipient->is_active])>
                        <td class="muted fade">{{ $recipients->firstItem() + $loop->index }}</td>
                        <td class="fade" style="font-weight: 600;">{{ $recipient->nama }}</td>
                        <td class="fade">{{ $recipient->email }}</td>
                        <td>
                            <label class="switch">
                                <input type="checkbox" data-status-url="{{ route('iso.emails.status', $recipient) }}" @checked($recipient->is_active) aria-label="Status {{ $recipient->email }}">
                                <span class="track"></span>
                                <span data-status-label>{{ $recipient->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </label>
                        </td>
                        <td style="text-align: right;">
                            <div class="act">
                                <button type="button" class="act-btn" title="Edit" aria-label="Edit" data-edit="{{ $recipient->id }}"
                                        data-record="{{ json_encode(['nama' => $recipient->nama, 'email' => $recipient->email]) }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                </button>
                                <button type="button" class="act-btn danger" title="Hapus" aria-label="Hapus" data-delete="{{ $recipient->id }}" data-name="{{ $recipient->nama }} ({{ $recipient->email }})">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted" style="text-align: center; padding: 32px;">
                            {{ $search !== '' ? 'Email tidak ditemukan.' : 'Belum ada email penerima. Klik + Tambah Email.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $recipients->links() }}
</div>

<dialog class="modal" id="recipient-modal">
    <form method="POST" action="{{ route('iso.emails.store') }}" id="recipient-form">
        @csrf
        <input type="hidden" name="_method" value="POST">
        <input type="hidden" name="_modal" value="create">
        <input type="hidden" name="keep[q]" value="{{ $search }}">
        <div class="modal-head">
            <h2 id="recipient-modal-title">Tambah Email</h2>
            <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <div>
                <label for="f-nama">Nama</label>
                <input id="f-nama" type="text" name="nama" maxlength="255" placeholder="Mis. Budi (QA)" required>
                @error('nama') <div class="field-error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="f-email">Email</label>
                <input id="f-email" type="email" name="email" maxlength="255" placeholder="nama@perusahaan.com" required>
                @error('email') <div class="field-error">{{ $message }}</div> @enderror
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
        <div class="modal-head">
            <h2>Hapus Email</h2>
            <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body">Hapus <strong id="delete-name"></strong> dari daftar penerima?</div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close>Batal</button>
            <button type="submit" class="btn" style="background: var(--danger);">Hapus</button>
        </div>
    </form>
</dialog>
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

        // Toggle aktif / nonaktif, disimpan langsung; dikembalikan kalau gagal.
        document.querySelectorAll('[data-status-url]').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var row = toggle.closest('tr');
                var label = toggle.closest('.switch').querySelector('[data-status-label]');
                var active = toggle.checked;
                var apply = function (value) {
                    toggle.checked = value;
                    row.classList.toggle('inactive', !value);
                    label.textContent = value ? 'Aktif' : 'Nonaktif';
                };
                apply(active);
                toggle.disabled = true;
                fetch(toggle.dataset.statusUrl, {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ is_active: active }),
                }).then(function (response) {
                    if (!response.ok) throw new Error(response.status === 419 ? 'Sesi habis, muat ulang halaman.' : 'Gagal mengubah status.');
                    return response.json();
                }).then(function (body) { apply(body.is_active); })
                    .catch(function (error) { apply(!active); alert(error.message); })
                    .finally(function () { toggle.disabled = false; });
            });
        });

        var modal = document.getElementById('recipient-modal');
        var form = document.getElementById('recipient-form');
        var storeUrl = {{ Js::from(route('iso.emails.store')) }};
        var updateUrl = {{ Js::from(route('iso.emails.update', '__ID__')) }};
        var destroyUrl = {{ Js::from(route('iso.emails.destroy', '__ID__')) }};

        function recordOf(id) {
            var button = document.querySelector('[data-edit="' + id + '"]');
            return button ? JSON.parse(button.dataset.record) : null;
        }

        function openForm(id, record, keepErrors) {
            form.action = id ? updateUrl.replace('__ID__', id) : storeUrl;
            form.elements._method.value = id ? 'PUT' : 'POST';
            form.elements._modal.value = id ? 'edit:' + id : 'create';
            document.getElementById('recipient-modal-title').textContent = id ? 'Edit Email' : 'Tambah Email';
            form.elements.nama.value = (record && record.nama) || '';
            form.elements.email.value = (record && record.email) || '';
            if (!keepErrors) modal.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });
            modal.showModal();
            form.elements.nama.focus();
        }

        document.querySelector('[data-create]').addEventListener('click', function () { openForm(null, null); });
        document.querySelectorAll('[data-edit]').forEach(function (button) {
            button.addEventListener('click', function () { openForm(button.dataset.edit, recordOf(button.dataset.edit)); });
        });
        document.querySelectorAll('[data-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('delete-form').action = destroyUrl.replace('__ID__', button.dataset.delete);
                document.getElementById('delete-name').textContent = button.dataset.name;
                document.getElementById('delete-modal').showModal();
            });
        });

        // Validasi gagal: buka lagi modal beserta isiannya.
        var reopen = {{ Js::from($errors->hasAny(['nama', 'email']) ? old('_modal') : null) }};
        if (reopen) {
            var editId = reopen.indexOf('edit:') === 0 ? reopen.slice(5) : null;
            openForm(editId, {{ Js::from(collect(old())->only(['nama', 'email'])) }}, true);
        }
    })();
</script>
@endpush
