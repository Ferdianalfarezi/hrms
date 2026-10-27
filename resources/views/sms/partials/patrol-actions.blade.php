{{--
    Tombol aksi berupa icon (Daily & Cross Patrol): detail, print (opsional), edit, hapus.
    Butuh: $patrol, $record (isi form edit), $canEdit, $canManage (hapus), $printable.
--}}
<div class="act">
    <button type="button" class="act-btn" data-detail="{{ $patrol->id }}" title="Detail" aria-label="Detail">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
    </button>
    @if ($printable)
        <button type="button" class="act-btn" data-print="{{ $patrol->id }}" title="Print" aria-label="Print">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        </button>
    @endif
    @if ($canEdit)
        <button type="button" class="act-btn" data-edit="{{ $patrol->id }}" data-record="{{ json_encode($record) }}" title="Edit" aria-label="Edit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
        </button>
        @if ($canManage)
        <button type="button" class="act-btn act-danger" data-delete="{{ $patrol->id }}" data-name="{{ \Illuminate\Support\Str::limit($patrol->finding, 60) }}" title="Hapus" aria-label="Hapus">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>
        </button>
        @endif
    @endif
</div>
