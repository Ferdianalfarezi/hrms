{{-- Modal detail, lihat foto besar, dan hapus; dipakai Daily Patrol & Cross Patrol. --}}
{{-- Isi detail diambil dari <template id="detail-{id}"> di tiap baris tabel. --}}
<dialog class="modal" id="detail-modal">
    <div class="modal-head">
        <div>
            <h2>Detail Temuan</h2>
            <div class="muted small" id="detail-sub"></div>
        </div>
        <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
    </div>
    <div class="modal-body" id="detail-body"></div>
    <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close>Tutup</button>
        <button type="button" class="btn btn-ghost" data-detail-action="print" hidden>Print</button>
        @if ($canEdit)
            <button type="button" class="btn" data-detail-action="edit">Edit</button>
        @endif
    </div>
</dialog>

<dialog class="modal" id="photo-modal">
    <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
    <img id="photo-modal-img" alt="Foto">
</dialog>

@if ($canEdit)
    <dialog class="modal" id="delete-modal">
        <form method="POST" id="delete-form">
            @csrf
            @method('DELETE')
            @foreach ($keepFilters as $key => $value)
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2>Hapus Temuan</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                Hapus temuan <strong id="delete-name"></strong>? Foto temuan dan foto perbaikannya ikut terhapus.
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" style="background: var(--danger);">Hapus</button>
            </div>
        </form>
    </dialog>
@endif
