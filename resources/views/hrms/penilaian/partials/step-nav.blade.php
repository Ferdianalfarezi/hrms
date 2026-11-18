{{-- Tombol pindah langkah di bawah tiap langkah form penilaian. --}}
<div class="step-nav">
    @if ($nomor > 1)
        <button type="button" class="btn btn-ghost" data-step-go="-1">&lsaquo; {{ __('Sebelumnya') }}</button>
    @else
        <span></span>
    @endif
    <span class="muted small">{{ __('Langkah :nomor dari :jumlah', ['nomor' => $nomor, 'jumlah' => $jumlah]) }}</span>
    @if ($nomor < $jumlah)
        <button type="button" class="btn" data-step-go="1">{{ __('Berikutnya') }} &rsaquo;</button>
    @else
        <span></span>
    @endif
</div>
