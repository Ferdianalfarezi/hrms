{{-- Sisa token penilaian user: setiap kirim / setuju memakai 1 token. --}}
@php($sisaToken = (int) auth()->user()->token_penilaian)
<p class="small" style="margin: 8px 0 0; text-align: right; color: {{ $sisaToken > 0 ? 'var(--muted)' : '#b91c1c' }};">
    @if ($sisaToken > 0)
        {{ __('Kirim / setuju memakai 1 token. Sisa token Anda: :sisa', ['sisa' => $sisaToken]) }}
    @else
        {{ __('Token penilaian Anda habis. Klik foto profil Anda untuk mengajukan penambahan token.') }}
    @endif
</p>
