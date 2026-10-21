{{-- Bagian edit saja: upload foto perbaikan lalu Close (dipakai Daily Patrol & Cross Patrol). --}}
<div class="fix-box" data-edit-only>
    <h3>Perbaikan</h3>
    <div>
        <label for="f-foto_perbaikan">Foto perbaikan</label>
        <div class="photo-field">
            <img class="photo-preview" id="preview-foto_perbaikan" alt="" hidden>
            <input id="f-foto_perbaikan" type="file" name="foto_perbaikan" accept="image/*" data-photo-input data-preview="preview-foto_perbaikan">
        </div>
        @error('foto_perbaikan') <div class="field-error">{{ $message }}</div> @enderror
    </div>
    <div>
        <label for="f-status">Status</label>
        <select id="f-status" name="status">
            <option value="open">Open</option>
            <option value="closed">Close</option>
        </select>
        <div class="muted small" id="status-hint" style="margin-top: 4px;">Upload foto perbaikan dulu untuk bisa memilih Close.</div>
        @error('status') <div class="field-error">{{ $message }}</div> @enderror
    </div>
</div>
