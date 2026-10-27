@extends('layouts.app')

@section('title', 'Kelola User')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
    <div>
        <h1 style="font-size: clamp(26px, 4vw, 40px); font-weight: 500; letter-spacing: -.03em;">Kelola User</h1>
        <p class="muted" style="margin: 2px 0 0;">{{ $users->total() }} user terdaftar</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <form method="GET" style="display: flex; gap: 8px;">
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari NIK atau nama..." style="width: 240px; border-radius: 999px;">
            <button type="submit" class="btn btn-ghost">Cari</button>
        </form>
        <a href="{{ route('admin.users.create') }}" class="btn">+ Tambah User</a>
    </div>
</div>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Akses</th>
                    <th>Status</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <img src="{{ $user->photo_url }}" alt="" class="avatar" style="width: 40px; height: 40px;">
                                <div>
                                    <div style="font-weight: 600;">
                                        {{ $user->name }}
                                        @if ($user->is_superadmin)
                                            <span class="badge badge-warn">Super Admin</span>
                                        @endif
                                    </div>
                                    <div class="muted small">NIK {{ $user->nik }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                @forelse ($user->applications as $application)
                                    <span class="badge">
                                        {{ $application->name }} · {{ $roleNames[$application->pivot->role_id] ?? '-' }}
                                        @if ($application->pivot->department_id)
                                            ({{ $departmentNames[$application->pivot->department_id] ?? '-' }})
                                        @endif
                                    </span>
                                @empty
                                    <span class="muted small">Belum ada akses</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            @if ($user->is_active)
                                <span class="badge" style="background: var(--success-bg); color: var(--success);">Aktif</span>
                            @else
                                <span class="badge badge-off">Nonaktif</span>
                            @endif
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost btn-sm">Edit</a>
                            @unless ($user->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display: inline;"
                                      onsubmit="return confirm({{ Js::from("Hapus user {$user->nik} - {$user->name}?") }})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted" style="text-align: center; padding: 32px;">User tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $users->links() }}
@endsection
