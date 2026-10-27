@extends('layouts.app')

@section('title', $user->exists ? 'Edit User' : 'Tambah User')

@section('styles')
    /* Pilihan departemen Admin Departemen: chip checkbox, bisa pilih banyak. */
    .dept-checks { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .dept-check { display: inline-flex; align-items: center; gap: 6px; margin: 0; padding: 6px 12px; border-radius: 999px; border: 1px solid var(--line-strong); background: var(--glass-strong); font-size: 13px; font-weight: 500; cursor: pointer; user-select: none; }
    .dept-check input { width: auto; margin: 0; accent-color: var(--primary); }
    .dept-check:has(input:checked) { border-color: var(--primary); background: var(--soft-2); color: var(--primary); }
    .form-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (max-width: 860px) { .form-layout { grid-template-columns: minmax(0, 1fr); } }
    .photo-card { text-align: center; }
    .photo-preview { width: 160px; height: 160px; margin: 8px auto 16px; border: 4px solid #fff; box-shadow: var(--shadow); }
    .file-btn { position: relative; overflow: hidden; }
    .file-btn input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .section-title { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
    .grid-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    @media (max-width: 640px) { .grid-form { grid-template-columns: minmax(0, 1fr); } .grid-form > .spacer { display: none; } }
@endsection

@section('content')
@php($isSelf = $user->exists && $user->is(auth()->user()))

<div style="margin-bottom: 20px;">
    <a href="{{ route('admin.users.index') }}" class="muted small">&larr; Kembali ke daftar user</a>
    <h1 style="font-size: clamp(26px, 4vw, 40px); font-weight: 500; letter-spacing: -.03em; margin-top: 4px;">
        {{ $user->exists ? "Edit {$user->name}" : 'Tambah User' }}
    </h1>
</div>

<form method="POST" enctype="multipart/form-data"
      action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf
    @if ($user->exists)
        @method('PUT')
    @endif

    <div class="form-layout">
        {{-- Foto profil --}}
        <div class="card photo-card">
            <div class="section-title">Foto Profil</div>
            <img id="photo-preview" src="{{ $user->photo_url }}" data-default="{{ asset('images/default-avatar.svg') }}"
                 alt="Foto profil" class="avatar photo-preview">

            <span class="btn btn-ghost btn-sm file-btn">
                Pilih foto
                <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp">
            </span>
            <p class="muted small" style="margin: 10px 0 0;">JPG, PNG, atau WEBP, maks. 2 MB.<br>Tanpa foto akan memakai avatar default.</p>
            @error('photo') <div class="field-error">{{ $message }}</div> @enderror

            @if ($user->photo)
                <label class="check" style="justify-content: center; margin-top: 12px; font-size: 12px;">
                    <input type="checkbox" name="remove_photo" value="1" id="remove-photo"> Hapus foto
                </label>
            @endif
        </div>

        <div style="display: grid; gap: 16px;">
            <div class="card">
                <div class="section-title" style="margin-bottom: 16px;">Data Akun</div>
                <div class="grid-form">
                    <div>
                        <label for="nik">NIK</label>
                        <input id="nik" type="text" name="nik" value="{{ old('nik', $user->nik) }}" required>
                        @error('nik') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="name">Nama</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="email">Email <span class="muted">(opsional)</span></label>
                        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}">
                        @error('email') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div class="spacer"></div>
                    <div>
                        <label for="password">PIN / Password @if ($user->exists)<span class="muted">(kosongkan jika tidak diganti)</span>@endif</label>
                        <input id="password" type="password" name="password" autocomplete="new-password" @required(! $user->exists)>
                        @error('password') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="password_confirmation">Ulangi PIN / Password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
                    </div>
                </div>

                <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-top: 18px;">
                    <label class="check">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) @disabled($isSelf)>
                        Akun aktif
                    </label>
                    <label class="check">
                        <input type="hidden" name="is_superadmin" value="0">
                        <input type="checkbox" name="is_superadmin" value="1" @checked(old('is_superadmin', $user->is_superadmin)) @disabled($isSelf)>
                        Super Admin <span class="muted">(semua modul &amp; kelola user)</span>
                    </label>
                </div>
                @if ($isSelf)
                    <p class="muted small" style="margin: 8px 0 0;">Status aktif dan Super Admin akun Anda sendiri tidak bisa diubah.</p>
                @endif
            </div>

            <div class="card">
                <div class="section-title">Hak Akses Modul</div>
                <p class="muted" style="margin: 0 0 16px;">
                    Pilih role untuk setiap modul. Pilih "Tidak ada akses" untuk menutup modul tersebut.
                    Super Admin otomatis bisa membuka semua modul.
                </p>
                @error('access') <div class="alert">{{ $message }}</div> @enderror

                <div class="grid-2">
                    @foreach ($applications as $application)
                        <div>
                            <label for="access-{{ $application->id }}">
                                {{ $application->name }}
                                @unless ($application->is_active)<span class="badge badge-off">modul nonaktif</span>@endunless
                            </label>
                            <select id="access-{{ $application->id }}" name="access[{{ $application->id }}]" data-role-select="{{ $application->id }}">
                                <option value="">Tidak ada akses</option>
                                @foreach ($application->roles as $role)
                                    <option value="{{ $role->id }}" data-code="{{ $role->code }}"
                                            @selected((string) old("access.{$application->id}", $currentAccess[$application->id] ?? '') === (string) $role->id)>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>

                            @if ($application->roles->contains('code', 'dept_admin'))
                                {{-- Muncul hanya saat role "Admin Departemen" dipilih. Bisa pilih lebih dari satu departemen. --}}
                                @php($checkedDepartments = array_map('strval', (array) old("department.{$application->id}", $currentDepartments[$application->id] ?? [])))
                                <div data-department-for="{{ $application->id }}" style="margin-top: 10px;">
                                    <label>Departemen yang dikelola <span class="muted small">(bisa lebih dari satu)</span></label>
                                    <div class="dept-checks">
                                        @foreach ($departments as $department)
                                            <label class="dept-check">
                                                <input type="checkbox" name="department[{{ $application->id }}][]" value="{{ $department->id }}"
                                                       @checked(in_array((string) $department->id, $checkedDepartments, true))>
                                                <span>{{ $department->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error("department.{$application->id}") <div class="field-error">{{ $message }}</div> @enderror
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn">Simpan</button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('photo');
        var preview = document.getElementById('photo-preview');
        var remove = document.getElementById('remove-photo');
        var original = preview.src;

        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                preview.src = URL.createObjectURL(input.files[0]);
                if (remove) remove.checked = false;
            } else {
                preview.src = original;
            }
        });

        if (remove) {
            remove.addEventListener('change', function () {
                preview.src = remove.checked ? preview.dataset.default : original;
                if (remove.checked) input.value = '';
            });
        }

        // Pilihan departemen hanya tampil kalau role "Admin Departemen" dipilih.
        document.querySelectorAll('[data-role-select]').forEach(function (select) {
            var box = document.querySelector('[data-department-for="' + select.dataset.roleSelect + '"]');
            if (!box) return;

            function toggle() {
                var option = select.options[select.selectedIndex];
                box.hidden = !(option && option.dataset.code === 'dept_admin');
            }

            select.addEventListener('change', toggle);
            toggle();
        });
    })();
</script>
@endpush
