{{--
    Badge status temuan Daily / Cross Patrol: Close, Open, atau Open (lewat target) berwarna merah.
    Butuh: $patrol; opsional $showCloser (tampilkan "oleh <user>" di bawah badge Close).
--}}
@if ($patrol->isClosed())
    <span class="status-pill status-closed" title="{{ __('Ditutup') }} {{ $patrol->closed_at?->format('d/m/Y H:i') }}">{{ __('Close') }}</span>
    @if (($showCloser ?? false) && $patrol->closer)
        <span class="closed-by">{{ __('oleh') }} <b>{{ $patrol->closer->name }}</b></span>
    @endif
@elseif ($patrol->isOverdue())
    <span class="status-pill status-overdue">{{ __('Open (lewat target)') }}</span>
    <span class="overdue-note">{{ __('Lewat :count hari', ['count' => (int) $patrol->target->diffInDays(today())]) }}</span>
@else
    <span class="status-pill status-open">{{ __('Open') }}</span>
@endif
