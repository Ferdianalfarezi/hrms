{{-- Profil lengkap user yang login: dibuka dengan klik foto di kanan atas. --}}
{{-- Penilai (menu Penilaian) juga melihat kode & sisa token penilaian, dan bisa mengajukan penambahan token ke Admin HRMS. --}}
@use('App\Modules\Hrms\Models\PenilaianTokenRequest')
@use('App\Modules\Hrms\Support\JenjangPenilaian')
@php
    $profil = auth()->user()->loadMissing(['karyawan.department', 'managedDepartments']);
    $penilai = $profil->canAccessApplication('hrms') && Gate::allows('penilaian-tahunan');
    $pengajuanToken = $penilai
        ? PenilaianTokenRequest::where('user_id', $profil->id)->latest()->limit(3)->get()
        : collect();
    $adaPengajuanMenunggu = $pengajuanToken->contains(fn ($pengajuan) => $pengajuan->isPending());
    $aksesModul = $profil->accessibleApplications()
        ->map(fn ($application) => ['nama' => $application->name, 'role' => $profil->roleLabel($application->code)]);
@endphp

<style>
    .profile-trigger { padding: 0; border: 0; background: none; cursor: pointer; border-radius: 50%; }
    .profile-trigger:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
    dialog.profile-modal { width: min(560px, calc(100% - 32px)); max-height: calc(100vh - 32px); overflow-y: auto; }
    .profile-hero { display: flex; gap: 16px; align-items: center; }
    .profile-hero img { width: 76px; height: 76px; border: 3px solid #fff; box-shadow: var(--shadow); flex: none; }
    .profile-hero h3 { margin: 0; font-size: 19px; font-weight: 600; }
    .profile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 16px; margin-top: 18px; font-size: 13px; }
    .profile-grid span { display: block; color: var(--muted); font-size: 12px; }
    .profile-grid div { overflow-wrap: anywhere; }
    .profile-token { margin-top: 18px; padding: 14px 16px; border-radius: 14px; background: var(--soft-2); }
    .profile-token-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .profile-token-sisa { font-size: 28px; font-weight: 600; line-height: 1; }
    .profile-token-sisa.habis { color: #b91c1c; }
    .profile-token code { font-size: 13px; font-weight: 600; letter-spacing: .04em; }
    .profile-token form { margin-top: 12px; display: grid; gap: 8px; }
    .profile-token input[type=number] { width: 100%; padding: 9px 12px; font: inherit; font-size: 13px; border: 1px solid var(--line-strong); border-radius: 10px; background: #fff; }
    .profile-token textarea { width: 100%; padding: 9px 12px; font: inherit; font-size: 13px; border: 1px solid var(--line-strong); border-radius: 10px; resize: vertical; }
    .profile-history { margin: 10px 0 0; padding: 0; list-style: none; font-size: 12px; display: grid; gap: 4px; }
    .profile-history li { display: flex; justify-content: space-between; gap: 8px; }
    .profile-badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-weight: 600; }
    .profile-badge.amber { background: #fef3c7; color: #92400e; }
    .profile-badge.green { background: #dcfce7; color: #15803d; }
    .profile-badge.red { background: #fee2e2; color: #b91c1c; }
    @media (max-width: 520px) { .profile-grid { grid-template-columns: minmax(0, 1fr); } }
</style>

<dialog class="modal profile-modal" id="profile-modal" aria-labelledby="profile-modal-title" @if ($errors->token->any()) data-open-on-load @endif>
    <div class="modal-head">
        <h2 id="profile-modal-title">{{ __('Profil Saya') }}</h2>
        <button type="button" class="modal-close" data-close-profile aria-label="{{ __('Tutup') }}">&times;</button>
    </div>
    <div class="modal-body">
        <div class="profile-hero">
            <img src="{{ $profil->photo_url }}" alt="{{ $profil->name }}" class="avatar">
            <div>
                <h3>{{ $profil->name }}</h3>
                <div class="muted small">NIK {{ $profil->nik }}</div>
                @if ($profil->is_superadmin)
                    <span class="badge" style="margin-top: 4px;">{{ __('Super Admin') }}</span>
                @endif
            </div>
        </div>

        <div class="profile-grid">
            <div><span>{{ __('Jabatan') }}</span>{{ __(JenjangPenilaian::userJabatan($profil) ?? '-') }}</div>
            <div><span>{{ __('Departemen') }}</span>{{ $profil->karyawan?->department?->name ?? '-' }}</div>
            <div><span>{{ __('Email') }}</span>{{ $profil->email ?: '-' }}</div>
            <div><span>{{ __('Login terakhir') }}</span>{{ $profil->last_login_at?->translatedFormat('d M Y H:i') ?? '-' }}</div>
            @foreach ($aksesModul as $akses)
                <div><span>{{ __('Akses :modul', ['modul' => $akses['nama']]) }}</span>{{ $akses['role'] ?? '-' }}</div>
            @endforeach
        </div>

        @if ($penilai)
            <div class="profile-token">
                <div class="profile-token-row">
                    <div>
                        <div class="muted small">{{ __('Sisa token penilaian') }}</div>
                        <div @class(['profile-token-sisa', 'habis' => $profil->token_penilaian < 1])>{{ $profil->token_penilaian }}</div>
                    </div>
                    <div style="text-align: right;">
                        <div class="muted small">{{ __('Kode token penilai') }}</div>
                        <code>{{ $profil->token_kode }}</code>
                    </div>
                </div>
                <p class="muted small" style="margin: 8px 0 0;">{{ __('Setiap tanda tangan penilaian (kirim / setuju) memakai 1 token.') }}</p>

                {{-- Pengajuan penambahan token baru bisa dilakukan setelah token habis. --}}
                @if ($adaPengajuanMenunggu)
                    <p class="small" style="margin: 10px 0 0; color: #92400e;">{{ __('Pengajuan token Anda sedang menunggu approval HRD.') }}</p>
                @elseif ($profil->token_penilaian > 0)
                @else
                    <form method="POST" action="{{ route('hrms.penilaian.token.store') }}">
                        @csrf
                        <div style="display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 8px; align-items: start;">
                            <div>
                                <label for="token-jumlah" class="small">{{ __('Token diminta') }}</label>
                                <input id="token-jumlah" type="number" name="jumlah" min="1" max="{{ PenilaianTokenRequest::MAKS_JUMLAH }}" value="{{ old('jumlah', 10) }}" required>
                            </div>
                            <div>
                                <label for="token-alasan" class="small">{{ __('Alasan') }}</label>
                                <textarea id="token-alasan" name="alasan" rows="2" maxlength="500" required placeholder="{{ __('Mis. masih ada 12 karyawan yang belum dinilai') }}">{{ old('alasan') }}</textarea>
                            </div>
                        </div>
                        @if ($errors->token->any())
                            <div class="field-error">{{ $errors->token->first() }}</div>
                        @endif
                        <div style="text-align: right;">
                            <button type="submit" class="btn btn-sm">{{ __('Ajukan penambahan token') }}</button>
                        </div>
                    </form>
                @endif

                @if ($pengajuanToken->isNotEmpty())
                    <ul class="profile-history">
                        @foreach ($pengajuanToken as $pengajuan)
                            <li>
                                <span>{{ $pengajuan->created_at->translatedFormat('d M Y') }} · +{{ $pengajuan->jumlah }}</span>
                                <span class="profile-badge {{ $pengajuan->statusTone() }}">{{ $pengajuan->statusLabel() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>
</dialog>

<script>
    (function () {
        var modal = document.getElementById('profile-modal');
        var trigger = document.getElementById('profile-trigger');
        if (!modal || !trigger) return;

        trigger.addEventListener('click', function () { modal.showModal(); });
        modal.querySelector('[data-close-profile]').addEventListener('click', function () { modal.close(); });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) modal.close();
        });

        // Pengajuan token gagal validasi: buka lagi modalnya supaya pesan error terlihat.
        if (modal.hasAttribute('data-open-on-load')) modal.showModal();
    })();
</script>
