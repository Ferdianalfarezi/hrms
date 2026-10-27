@extends('layouts.app')

@section('title', 'Karyawan')

@section('styles')
    .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .filters input[type=search] { width: 240px; border-radius: 999px; }
    .filters select { width: auto; min-width: 160px; border-radius: 999px; }
    .status-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; background: var(--glass); border: 1px solid #fff; font-size: 13px; color: var(--muted); }
    .chip b { color: var(--text); font-weight: 600; }
    .chip.active { background: var(--navy); color: rgba(255,255,255,.8); border-color: var(--navy); }
    .chip.active b { color: #fff; }
    .st-Tetap { background: var(--success-bg); color: var(--success); }
    .st-Pemagangan { background: #fff4d6; color: #8a5a00; }
    td.nowrap, th.nowrap { white-space: nowrap; }
    .import-result { margin-bottom: 16px; }
    .import-result summary { cursor: pointer; font-weight: 600; margin-top: 10px; }
    .import-result ul { margin: 8px 0 0; padding-left: 18px; max-height: 260px; overflow: auto; }
    .import-result li { margin-bottom: 4px; }
    .result-stats { display: flex; gap: 24px; flex-wrap: wrap; }
    .result-stats div b { display: block; font-size: 26px; font-weight: 500; letter-spacing: -.02em; }
    dialog.modal.wide { width: min(720px, calc(100% - 32px)); }
    .dropzone { border: 2px dashed var(--line-strong); border-radius: var(--radius-sm); padding: 28px 16px; text-align: center; background: var(--soft-2); position: relative; }
    .dropzone input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .dropzone.has-file { border-color: var(--primary); background: #eef4ff; }
@endsection

@section('content')
<div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px;">
    <div>
        <h1 style="font-size: clamp(26px, 4vw, 40px); font-weight: 500; letter-spacing: -.03em;">Karyawan</h1>
        <p class="muted" style="margin: 2px 0 0;">
            {{ $statusCounts->sum() }} karyawan
            @if ($lockedDepartment)
                di departemen <span class="badge">{{ $lockedDepartment->name }}</span>
            @else
                terdaftar
            @endif
        </p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        @can('import', \App\Modules\Hrms\Models\Karyawan::class)
            <button type="button" class="btn btn-ghost" data-open="import-modal">Import Excel</button>
        @endcan
        @can('create', \App\Modules\Hrms\Models\Karyawan::class)
            <button type="button" class="btn" data-create>+ Tambah Karyawan</button>
        @endcan
    </div>
</div>

@if ($missingDepartment)
    <div class="alert">Akun Anda adalah Admin Departemen tetapi belum dipilihkan departemen, jadi belum ada karyawan yang bisa ditampilkan. Hubungi Super Admin.</div>
@endif

@error('file')
    <div class="alert">{{ $message }}</div>
@enderror

@if ($result = session('import_result'))
    <div class="card import-result">
        <div class="card-title" style="margin-bottom: 12px;">Hasil import</div>
        <div class="result-stats">
            <div><b>{{ $result['created'] }}</b><span class="muted small">baru</span></div>
            <div><b>{{ $result['updated'] }}</b><span class="muted small">diperbarui</span></div>
            <div><b>{{ $result['unchanged'] }}</b><span class="muted small">tidak berubah</span></div>
            <div><b style="{{ count($result['failed']) ? 'color: var(--danger);' : '' }}">{{ count($result['failed']) }}</b><span class="muted small">gagal</span></div>
            <div><b style="{{ count($result['warnings']) ? 'color: #8a5a00;' : '' }}">{{ count($result['warnings']) }}</b><span class="muted small">perlu dicek</span></div>
        </div>
        @if (! empty($result['unknown_departments']))
            <div class="alert" style="background: #fff4d6; color: #8a5a00; margin: 14px 0 0;">
                Departemen belum terdaftar, karyawannya diimport tanpa departemen:
                @foreach ($result['unknown_departments'] as $name => $total)
                    <strong>{{ $name }}</strong> ({{ $total }} karyawan){{ $loop->last ? '.' : ',' }}
                @endforeach
                Tambahkan di menu <a href="{{ route('hrms.departments.index') }}" style="text-decoration: underline;">Departemen</a>, lalu import ulang file yang sama.
            </div>
        @endif
        @if (count($result['failed']))
            <details open>
                <summary style="color: var(--danger);">Baris yang gagal diimport ({{ count($result['failed']) }})</summary>
                <ul>
                    @foreach ($result['failed'] as $failure)
                        <li>Baris {{ $failure['row'] }}@if ($failure['nik']) · NIK {{ $failure['nik'] }}@endif @if ($failure['nama']) · {{ $failure['nama'] }}@endif: {{ implode(' ', $failure['errors']) }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
        @if (count($result['warnings']))
            <details>
                <summary style="color: #8a5a00;">Data yang perlu dicek ({{ count($result['warnings']) }})</summary>
                <ul>
                    @foreach ($result['warnings'] as $warning)
                        <li>Baris {{ $warning['row'] }}: {{ $warning['message'] }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
@endif

<div class="status-chips">
    <a href="{{ route('hrms.karyawans.index', array_filter(['q' => $search, 'jabatan' => $jabatan, 'department' => $department, 'group' => $group])) }}" @class(['chip', 'active' => $status === ''])>
        Semua <b>{{ $statusCounts->sum() }}</b>
    </a>
    @foreach (\App\Modules\Hrms\Models\Karyawan::STATUS as $option)
        <a href="{{ route('hrms.karyawans.index', array_filter(['q' => $search, 'jabatan' => $jabatan, 'department' => $department, 'group' => $group, 'status' => $option])) }}" @class(['chip', 'active' => $status === $option])>
            {{ $option }} <b>{{ $statusCounts[$option] ?? 0 }}</b>
        </a>
    @endforeach
</div>

<form method="GET" class="filters">
    @if ($status !== '')
        <input type="hidden" name="status" value="{{ $status }}">
    @endif
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari NIK atau nama...">
    @if ($isAdmin)
    <select name="department" onchange="this.form.submit()">
        <option value="">Semua departemen</option>
        @foreach ($departments as $option)
            <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
        @endforeach
        <option value="none" @selected($department === 'none')>Tanpa departemen ({{ $withoutDepartment }})</option>
    </select>
    @endif
    <select name="group" onchange="this.form.submit()" style="min-width: 130px;">
        <option value="">Semua group</option>
        @foreach ($groupOptions as $option)
            <option value="{{ $option }}" @selected($group === $option)>Group {{ $option }}</option>
        @endforeach
        <option value="none" @selected($group === 'none')>Tanpa group</option>
    </select>
    <select name="jabatan" onchange="this.form.submit()">
        <option value="">Semua jabatan</option>
        @foreach ($jabatanOptions as $option)
            <option value="{{ $option }}" @selected($jabatan === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-ghost">Cari</button>
    @if ($search !== '' || $status !== '' || $jabatan !== '' || $department !== '' || $group !== '')
        <a href="{{ route('hrms.karyawans.index') }}" class="muted small">Reset filter</a>
    @endif
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="nowrap">NIK</th>
                    <th>Nama</th>
                    <th>Dept</th>
                    <th>Group</th>
                    <th class="nowrap">Jenis Kelamin</th>
                    <th class="nowrap">Tempat Lahir</th>
                    <th class="nowrap">Tgl Lahir</th>
                    <th class="nowrap">Umur</th>
                    <th>Jabatan</th>
                    <th class="nowrap">Tanggal Masuk</th>
                    <th>Status</th>
                    @if ($isAdmin)
                        <th class="nowrap" title="Potongan gaji setiap kelipatan 30 menit datang terlambat">Potongan Absen / 30 mnt</th>
                    @endif
                    @can('create', \App\Modules\Hrms\Models\Karyawan::class)
                        <th style="text-align: right;">Aksi</th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                @forelse ($karyawans as $karyawan)
                    <tr>
                        <td class="nowrap" style="font-weight: 600;">{{ $karyawan->nik }}</td>
                        <td>{{ $karyawan->nama }}</td>
                        <td>
                            @if ($karyawan->department)
                                <span class="badge">{{ $karyawan->department->name }}</span>
                            @else
                                <span class="badge badge-off">-</span>
                            @endif
                        </td>
                        <td class="nowrap">{{ $karyawan->group ?? '-' }}</td>
                        <td class="nowrap">{{ $karyawan->jenis_kelamin }}</td>
                        <td>{{ $karyawan->tempat_lahir ?? '-' }}</td>
                        <td class="nowrap">{{ $karyawan->tgl_lahir?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td class="nowrap">{{ $karyawan->umur !== null ? $karyawan->umur.' th' : '-' }}</td>
                        <td>{{ $karyawan->jabatan ?? '-' }}</td>
                        <td class="nowrap">{{ $karyawan->tanggal_masuk?->translatedFormat('d M Y') ?? '-' }}</td>
                        <td><span class="badge st-{{ str_replace(' ', '', $karyawan->status) }}">{{ $karyawan->status }}</span></td>
                        @if ($isAdmin)
                            <td class="nowrap" style="text-align: right;">Rp {{ number_format($karyawan->potongan_absen, 0, ',', '.') }}</td>
                        @endif
                        @can('create', \App\Modules\Hrms\Models\Karyawan::class)
                            <td style="text-align: right; white-space: nowrap;">
                                @can('update', $karyawan)
                                <button type="button" class="btn btn-ghost btn-sm" data-edit="{{ $karyawan->id }}"
                                        data-record="{{ json_encode([
                                            'nik' => $karyawan->nik,
                                            'nama' => $karyawan->nama,
                                            'department_id' => $karyawan->department_id,
                                            'jenis_kelamin' => $karyawan->jenis_kelamin,
                                            'tempat_lahir' => $karyawan->tempat_lahir,
                                            'tgl_lahir' => $karyawan->tgl_lahir?->format('Y-m-d'),
                                            'jabatan' => $karyawan->jabatan,
                                            'tanggal_masuk' => $karyawan->tanggal_masuk?->format('Y-m-d'),
                                            'status' => $karyawan->status,
                                            'group' => $karyawan->group,
                                            'potongan_absen' => $isAdmin ? $karyawan->potongan_absen : null,
                                        ]) }}">Edit</button>
                                <button type="button" class="btn btn-danger btn-sm" data-delete="{{ $karyawan->id }}"
                                        data-name="{{ $karyawan->nik }} - {{ $karyawan->nama }}">Hapus</button>
                                @endcan
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="muted" style="text-align: center; padding: 32px;">
                            @if ($search !== '' || $status !== '' || $jabatan !== '' || $department !== '' || $group !== '')
                                Karyawan tidak ditemukan.
                            @else
                                Belum ada data karyawan.
                                @can('import', \App\Modules\Hrms\Models\Karyawan::class)
                                    <div style="margin-top: 12px;"><button type="button" class="btn btn-sm" data-open="import-modal">Import dari Excel</button></div>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $karyawans->links() }}

@can('create', \App\Modules\Hrms\Models\Karyawan::class)
    {{-- Modal tambah / edit --}}
    <dialog class="modal wide" id="karyawan-modal">
        <form method="POST" action="{{ route('hrms.karyawans.store') }}" id="karyawan-form">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="create">

            <div class="modal-head">
                <h2 id="karyawan-modal-title">Tambah Karyawan</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                @if ($errors->hasAny(['nik', 'nama', 'department_id', 'jenis_kelamin', 'tempat_lahir', 'tgl_lahir', 'jabatan', 'tanggal_masuk', 'status', 'group', 'potongan_absen']))
                    <div class="alert">Periksa kembali isian yang ditandai.</div>
                @endif
                <div class="grid-2">
                    <div>
                        <label for="f-nik">NIK</label>
                        <input id="f-nik" type="text" name="nik" inputmode="numeric" required maxlength="20">
                        @error('nik') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-nama">Nama</label>
                        <input id="f-nama" type="text" name="nama" required maxlength="255">
                        @error('nama') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-department_id">Departemen</label>
                        @if ($isAdmin)
                            <select id="f-department_id" name="department_id">
                                <option value="">Tanpa departemen</option>
                                @foreach ($departments as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        @else
                            {{-- Admin Departemen selalu menyimpan ke departemennya sendiri (dipaksa juga di server). --}}
                            <input id="f-department_id" type="text" value="{{ $lockedDepartment?->name }}" disabled>
                        @endif
                        @error('department_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-jenis_kelamin">Jenis Kelamin</label>
                        <select id="f-jenis_kelamin" name="jenis_kelamin" required>
                            <option value="">Pilih...</option>
                            @foreach (\App\Modules\Hrms\Models\Karyawan::JENIS_KELAMIN as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('jenis_kelamin') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-tempat_lahir">Tempat Lahir</label>
                        <input id="f-tempat_lahir" type="text" name="tempat_lahir" maxlength="255">
                        @error('tempat_lahir') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-tgl_lahir">Tgl Lahir</label>
                        <input id="f-tgl_lahir" type="date" name="tgl_lahir">
                        @error('tgl_lahir') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-jabatan">Jabatan</label>
                        <input id="f-jabatan" type="text" name="jabatan" list="jabatan-list" maxlength="255">
                        <datalist id="jabatan-list">
                            @foreach ($jabatanOptions as $option)
                                <option value="{{ $option }}">
                            @endforeach
                        </datalist>
                        @error('jabatan') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-tanggal_masuk">Tanggal Masuk</label>
                        <input id="f-tanggal_masuk" type="date" name="tanggal_masuk">
                        @error('tanggal_masuk') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-status">Status</label>
                        <select id="f-status" name="status" required>
                            <option value="">Pilih...</option>
                            @foreach (\App\Modules\Hrms\Models\Karyawan::STATUS as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('status') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-group">Group</label>
                        <input id="f-group" type="text" name="group" list="group-list" maxlength="20" placeholder="Mis. A, B, NS" style="text-transform: uppercase;">
                        <datalist id="group-list">
                            @foreach ($groupOptions as $option)
                                <option value="{{ $option }}">
                            @endforeach
                        </datalist>
                        @error('group') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    @if ($isAdmin)
                        <div>
                            <label for="f-potongan_absen">Potongan Absen / 30 menit (Rp)</label>
                            <input id="f-potongan_absen" type="number" name="potongan_absen" min="0" max="10000000" step="1000"
                                   placeholder="{{ \App\Modules\Hrms\Models\Karyawan::POTONGAN_ABSEN_DEFAULT }}">
                            <div class="muted small" style="margin-top: 4px;">Dipotong setiap kelipatan 30 menit datang terlambat. Kosong = {{ number_format(\App\Modules\Hrms\Models\Karyawan::POTONGAN_ABSEN_DEFAULT, 0, ',', '.') }}.</div>
                            @error('potongan_absen') <div class="field-error">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn">Simpan</button>
            </div>
        </form>
    </dialog>

    {{-- Modal hapus --}}
    <dialog class="modal" id="delete-modal">
        <form method="POST" id="delete-form">
            @csrf
            @method('DELETE')
            <div class="modal-head">
                <h2>Hapus Karyawan</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                Yakin ingin menghapus karyawan <strong id="delete-name"></strong>? Tindakan ini tidak bisa dibatalkan.
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" style="background: var(--danger);">Hapus</button>
            </div>
        </form>
    </dialog>

    {{-- Modal import (hanya Admin HRMS & super admin) --}}
    @can('import', \App\Modules\Hrms\Models\Karyawan::class)
    <dialog class="modal" id="import-modal">
        <form method="POST" action="{{ route('hrms.karyawans.import') }}" enctype="multipart/form-data" id="import-form">
            @csrf
            <div class="modal-head">
                <h2>Import Karyawan dari Excel</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div class="dropzone" id="dropzone">
                    <input type="file" name="file" id="import-file" accept=".xlsx" required>
                    <div style="font-weight: 600;" id="dropzone-label">Klik atau tarik file .xlsx ke sini</div>
                    <div class="muted small">Maksimal 10 MB</div>
                </div>
                <div class="muted small" style="margin-top: 14px; line-height: 1.6;">
                    Kolom yang dibaca: <strong>{{ implode(', ', array_keys(\App\Modules\Hrms\Imports\KaryawanImport::COLUMNS)) }}</strong>.
                    Kolom lain diabaikan. NIK yang sudah ada akan diperbarui, NIK baru akan ditambahkan.
                    <br><a href="{{ route('hrms.karyawans.template') }}" data-turbo="false" style="color: var(--primary); font-weight: 600;">Unduh template Excel</a>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" id="import-submit">Import</button>
            </div>
        </form>
    </dialog>
    @endcan
@endcan
@endsection

@can('create', \App\Modules\Hrms\Models\Karyawan::class)
@push('scripts')
<script>
    (function () {
        var fields = ['nik', 'nama', 'department_id', 'jenis_kelamin', 'tempat_lahir', 'tgl_lahir', 'jabatan', 'tanggal_masuk', 'status', 'group', 'potongan_absen'];
        var modal = document.getElementById('karyawan-modal');
        var form = document.getElementById('karyawan-form');
        var storeUrl = {{ Js::from(route('hrms.karyawans.store')) }};
        var updateUrl = {{ Js::from(route('hrms.karyawans.update', '__ID__')) }};
        var destroyUrl = {{ Js::from(route('hrms.karyawans.destroy', '__ID__')) }};

        function openForm(id, record, keepErrors) {
            form.action = id ? updateUrl.replace('__ID__', id) : storeUrl;
            form.elements._method.value = id ? 'PUT' : 'POST';
            form.elements._modal.value = id ? 'edit:' + id : 'create';
            document.getElementById('karyawan-modal-title').textContent = id ? 'Edit Karyawan' : 'Tambah Karyawan';
            fields.forEach(function (name) {
                if (form.elements[name]) form.elements[name].value = (record && record[name]) || '';
            });
            if (!keepErrors) {
                modal.querySelectorAll('.field-error, .alert').forEach(function (el) { el.remove(); });
            }
            modal.showModal();
            form.elements.nik.focus();
        }

        document.querySelectorAll('[data-create]').forEach(function (button) {
            button.addEventListener('click', function () { openForm(null, null); });
        });
        document.querySelectorAll('[data-edit]').forEach(function (button) {
            button.addEventListener('click', function () { openForm(button.dataset.edit, JSON.parse(button.dataset.record)); });
        });
        document.querySelectorAll('[data-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('delete-form').action = destroyUrl.replace('__ID__', button.dataset.delete);
                document.getElementById('delete-name').textContent = button.dataset.name;
                document.getElementById('delete-modal').showModal();
            });
        });
        document.querySelectorAll('[data-open]').forEach(function (button) {
            button.addEventListener('click', function () { document.getElementById(button.dataset.open).showModal(); });
        });

        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () { dialog.close(); });
            });
            dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
        });

        // Tampilkan nama file yang dipilih dan cegah klik ganda saat import berjalan.
        var fileInput = document.getElementById('import-file');
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files[0];
                document.getElementById('dropzone').classList.toggle('has-file', !!file);
                document.getElementById('dropzone-label').textContent = file ? file.name : 'Klik atau tarik file .xlsx ke sini';
            });
            document.getElementById('import-form').addEventListener('submit', function () {
                var submit = document.getElementById('import-submit');
                submit.disabled = true;
                submit.textContent = 'Mengimport...';
            });
        }

        // Validasi gagal: buka lagi modal yang tadi dipakai beserta isiannya.
        var reopen = {{ Js::from($errors->hasAny(['nik', 'nama', 'department_id', 'jenis_kelamin', 'tempat_lahir', 'tgl_lahir', 'jabatan', 'tanggal_masuk', 'status', 'group', 'potongan_absen']) ? old('_modal') : null) }};
        if (reopen) {
            var old = {{ Js::from(collect(old())->only(['nik', 'nama', 'department_id', 'jenis_kelamin', 'tempat_lahir', 'tgl_lahir', 'jabatan', 'tanggal_masuk', 'status', 'group', 'potongan_absen'])) }};
            openForm(reopen.indexOf('edit:') === 0 ? reopen.slice(5) : null, old, true);
        }
    })();
</script>
@endpush
@endcan
