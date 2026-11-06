{{-- Pilihan departemen di form ISO. Di dalam folder, departemen selalu ikut folder (tidak bisa dipilih). --}}
@if ($inFolder)
    <div class="locked-dept">{{ $inFolder->department?->name ?? 'Umum' }} <span class="muted small">(ikut folder)</span></div>
@elseif ($isAdmin)
    <select id="{{ $id }}" name="department_id">
        <option value="">Umum (semua departemen)</option>
        @foreach ($departments as $option)
            <option value="{{ $option->id }}" @selected(old('department_id') == $option->id)>{{ $option->name }}</option>
        @endforeach
    </select>
@elseif ($departments->count() > 1)
    <select id="{{ $id }}" name="department_id" required>
        @foreach ($departments as $option)
            <option value="{{ $option->id }}" @selected(old('department_id') == $option->id)>{{ $option->name }}</option>
        @endforeach
    </select>
@else
    <div class="locked-dept">{{ $departments->first()?->name ?? '-' }}</div>
@endif
@error('department_id') <div class="field-error">{{ $message }}</div> @enderror
