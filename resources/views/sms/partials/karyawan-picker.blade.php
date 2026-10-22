{{--
    Pilih karyawan (bisa lebih dari 1) dengan pencarian nama / NIK / departemen.
    Nilai dikirim lewat input hidden berisi nama dipisah koma. Butuh: $name, $label; opsional $placeholder.
    JS-nya ada di sms.partials.karyawan-picker-script.
--}}
<div>
    <label for="f-{{ $name }}">{{ $label }}</label>
    <div class="kp" data-karyawan-picker>
        <input type="hidden" name="{{ $name }}">
        <div class="kp-box">
            <span class="kp-chips"></span>
            <input id="f-{{ $name }}" type="text" class="kp-search" placeholder="{{ $placeholder ?? 'Cari nama / NIK karyawan...' }}"
                autocomplete="off" role="combobox" aria-expanded="false" required>
        </div>
        <div class="kp-list" role="listbox" hidden></div>
    </div>
    @error($name) <div class="field-error">{{ $message }}</div> @enderror
</div>
