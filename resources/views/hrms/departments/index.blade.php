@extends('layouts.app')

@section('title', 'Departemen')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
    <div>
        <h1 style="font-size: clamp(26px, 4vw, 40px); font-weight: 500; letter-spacing: -.03em;">Departemen</h1>
        <p class="muted" style="margin: 2px 0 0;">{{ $departments->total() }} departemen terdaftar</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <form method="GET" style="display: flex; gap: 8px;">
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari departemen..." style="width: 240px; border-radius: 999px;">
            <button type="submit" class="btn btn-ghost">Cari</button>
        </form>
        @if ($canManage)
            <button type="button" class="btn" data-create>+ Tambah Departemen</button>
        @endif
    </div>
</div>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width: 72px;">No</th>
                    <th>Nama Departemen</th>
                    <th>Jumlah Karyawan</th>
                    @if ($canManage)
                        <th style="text-align: right;">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($departments as $department)
                    <tr>
                        <td class="muted">{{ $departments->firstItem() + $loop->index }}</td>
                        <td style="font-weight: 600;">{{ $department->name }}</td>
                        <td>
                            <a href="{{ route('hrms.karyawans.index', ['department' => $department->id]) }}" class="muted">{{ $department->karyawans_count }} karyawan</a>
                        </td>
                        @if ($canManage)
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-ghost btn-sm"
                                        data-edit="{{ $department->id }}" data-name="{{ $department->name }}"
                                        data-url="{{ route('hrms.departments.update', $department) }}">Edit</button>
                                <button type="button" class="btn btn-danger btn-sm"
                                        data-delete data-name="{{ $department->name }}"
                                        data-url="{{ route('hrms.departments.destroy', $department) }}">Hapus</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="muted" style="text-align: center; padding: 32px;">
                            {{ $search !== '' ? 'Departemen tidak ditemukan.' : 'Belum ada departemen.' }}
                            @if ($canManage && $search === '')
                                <div style="margin-top: 12px;"><button type="button" class="btn btn-sm" data-create>+ Tambah departemen pertama</button></div>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $departments->links() }}

@if ($canManage)
    {{-- Modal tambah / edit --}}
    <dialog class="modal" id="department-modal">
        <form method="POST" action="{{ route('hrms.departments.store') }}" id="department-form">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="create">

            <div class="modal-head">
                <h2 id="department-modal-title">Tambah Departemen</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <label for="department-name">Nama Departemen</label>
                <input id="department-name" type="text" name="name" placeholder="Contoh: Finance" required maxlength="255">
                <div class="field-error" id="department-error" @if (! $errors->has('name')) hidden @endif>{{ $errors->first('name') }}</div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn">Simpan</button>
            </div>
        </form>
    </dialog>

    {{-- Modal konfirmasi hapus --}}
    <dialog class="modal" id="delete-modal">
        <form method="POST" id="delete-form">
            @csrf
            @method('DELETE')
            <div class="modal-head">
                <h2>Hapus Departemen</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                Yakin ingin menghapus departemen <strong id="delete-name"></strong>? Tindakan ini tidak bisa dibatalkan.
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" style="background: var(--danger);">Hapus</button>
            </div>
        </form>
    </dialog>
@endif
@endsection

@if ($canManage)
@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('department-modal');
        var form = document.getElementById('department-form');
        var title = document.getElementById('department-modal-title');
        var nameInput = document.getElementById('department-name');
        var error = document.getElementById('department-error');
        var storeUrl = {{ Js::from(route('hrms.departments.store')) }};

        function openForm(mode, options) {
            form.action = options.url;
            form.elements._method.value = mode === 'create' ? 'POST' : 'PUT';
            form.elements._modal.value = mode === 'create' ? 'create' : 'edit:' + options.id;
            title.textContent = mode === 'create' ? 'Tambah Departemen' : 'Edit Departemen';
            nameInput.value = options.name || '';
            if (!options.keepError) error.hidden = true;
            modal.showModal();
            nameInput.focus();
        }

        document.querySelectorAll('[data-create]').forEach(function (button) {
            button.addEventListener('click', function () { openForm('create', { url: storeUrl }); });
        });

        document.querySelectorAll('[data-edit]').forEach(function (button) {
            button.addEventListener('click', function () {
                openForm('edit', { id: button.dataset.edit, name: button.dataset.name, url: button.dataset.url });
            });
        });

        var deleteModal = document.getElementById('delete-modal');
        document.querySelectorAll('[data-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('delete-form').action = button.dataset.url;
                document.getElementById('delete-name').textContent = button.dataset.name;
                deleteModal.showModal();
            });
        });

        // Tutup modal lewat tombol × / Batal, atau klik area gelap di luar modal.
        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () { dialog.close(); });
            });
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) dialog.close();
            });
        });

        // Validasi gagal: buka lagi modal yang tadi dipakai beserta isiannya.
        var reopen = {{ Js::from($errors->has('name') ? old('_modal') : null) }};
        if (reopen === 'create') {
            openForm('create', { url: storeUrl, name: {{ Js::from(old('name')) }}, keepError: true });
        } else if (reopen && reopen.indexOf('edit:') === 0) {
            var editButton = document.querySelector('[data-edit="' + reopen.slice(5) + '"]');
            if (editButton) {
                openForm('edit', { id: editButton.dataset.edit, name: {{ Js::from(old('name')) }}, url: editButton.dataset.url, keepError: true });
            }
        }
    })();
</script>
@endpush
@endif
