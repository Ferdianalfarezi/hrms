@extends('layouts.app')

@section('title', __('Kelola User'))

@section('content')
<div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
    <div>
        <h1 style="font-size: clamp(26px, 4vw, 40px); font-weight: 500; letter-spacing: -.03em;">{{ __('Kelola User') }}</h1>
        <p class="muted" style="margin: 2px 0 0;">{{ __(':count user terdaftar', ['count' => $users->total()]) }}</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <form method="GET" style="display: flex; gap: 8px;">
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('Cari NIK atau nama...') }}" style="width: 240px; border-radius: 999px;">
            <button type="submit" class="btn btn-ghost">{{ __('Cari') }}</button>
        </form>
        <a href="{{ route('admin.users.create') }}" class="btn">{{ __('+ Tambah User') }}</a>
    </div>
</div>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Karyawan') }}</th>
                    <th>{{ __('Akses') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Last Login') }}</th>
                    <th style="text-align: right;">{{ __('Aksi') }}</th>
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
                                            <span class="badge badge-warn">{{ __('Super Admin') }}</span>
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
                                        {{ $application->name }} · {{ __($roleNames[$application->pivot->role_id] ?? '-') }}
                                        @php($managed = $user->managedDepartments->where('pivot.application_id', $application->id)->sortBy('name'))
                                        @if ($managed->isNotEmpty())
                                            ({{ $managed->pluck('name')->implode(', ') }})
                                        @endif
                                    </span>
                                @empty
                                    <span class="muted small">{{ __('Belum ada akses') }}</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            @if ($user->is_active)
                                <span class="badge" style="background: var(--success-bg); color: var(--success);">{{ __('Aktif') }}</span>
                            @else
                                <span class="badge badge-off">{{ __('Nonaktif') }}</span>
                            @endif
                        </td>
                        <td style="white-space: nowrap;">
                            @if ($user->last_login_at)
                                <div title="{{ $user->last_login_at->translatedFormat('l, d F Y H:i') }}">{{ $user->last_login_at->format('d M Y H:i') }}</div>
                                <div class="muted small">{{ $user->last_login_at->diffForHumans() }}</div>
                            @else
                                <span class="muted small">{{ __('Belum pernah login') }}</span>
                            @endif
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost btn-sm">{{ __('Edit') }}</a>
                            @unless ($user->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display: inline;"
                                      onsubmit="return confirm({{ Js::from(__('Hapus user :nik - :nama?', ['nik' => $user->nik, 'nama' => $user->name])) }})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Hapus') }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted" style="text-align: center; padding: 32px;">{{ __('User tidak ditemukan.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $users->links() }}
@endsection
