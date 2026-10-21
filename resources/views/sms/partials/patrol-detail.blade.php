{{--
    Isi modal detail satu temuan (disalin ke #detail-modal saat icon mata diklik).
    Butuh: $patrol, $items = [[label, nilai, lebar penuh?], ...]
--}}
<template id="detail-{{ $patrol->id }}" data-sub="{{ $patrol->tanggal->translatedFormat('l, d F Y') }}">
    <div class="detail-photos">
        <figure class="detail-photo">
            <figcaption>Foto temuan</figcaption>
            <button type="button" data-photo="{{ $patrol->foto_url }}" title="Perbesar"><img src="{{ $patrol->foto_url }}" alt="Foto temuan"></button>
        </figure>
        <figure class="detail-photo">
            <figcaption>Foto perbaikan</figcaption>
            @if ($patrol->foto_perbaikan_url)
                <button type="button" data-photo="{{ $patrol->foto_perbaikan_url }}" title="Perbesar"><img src="{{ $patrol->foto_perbaikan_url }}" alt="Foto perbaikan"></button>
            @else
                <div class="none">Belum ada</div>
            @endif
        </figure>
    </div>
    <dl class="detail-list">
        @foreach ($items as $item)
            <div @class(['wide' => $item[2] ?? false])>
                <dt>{{ $item[0] }}</dt>
                <dd>{{ filled($item[1]) ? $item[1] : '-' }}</dd>
            </div>
        @endforeach
        <div>
            <dt>Status</dt>
            <dd>
                @if ($patrol->isClosed())
                    <span class="status-pill status-closed">Close</span>
                    <span class="small muted">{{ $patrol->closed_at?->format('d/m/Y H:i') }}</span>
                @else
                    <span class="status-pill status-open">Open</span>
                @endif
            </dd>
        </div>
    </dl>
</template>
