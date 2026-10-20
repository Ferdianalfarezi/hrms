@extends('layouts.app')

@section('title', 'Absensi')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
@endpush

@section('styles')
    .ent-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px; }
    .filters input.dp-input { width: 160px; border-radius: 999px; padding: 9px 38px 9px 14px; }
    /* Kalender di dalam modal ditempel ke input (mode static), jadi modal tidak boleh memotongnya. */
    #entry-modal { overflow: visible; }
    #entry-modal .flatpickr-wrapper { display: block; }

    /* Kalender tanggal (flatpickr) dibuat mirip date picker bawaan browser; bedanya Sabtu & Minggu tidak bisa dipilih. */
    input.dp-input {
        cursor: pointer; padding-right: 38px; background-repeat: no-repeat; background-position: right 12px center; background-size: 16px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23202124' stroke-width='2' stroke-linecap='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2'/%3E%3Cpath d='M16 2v4M8 2v4M3 10h18M8 14h2M14 14h2M8 18h2M14 18h2'/%3E%3C/svg%3E");
    }
    .flatpickr-calendar {
        width: 253px !important; padding: 12px 10px 8px; border: 1px solid #dadce0; border-radius: 6px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, .18); font-family: "Segoe UI", system-ui, sans-serif; color: #202124;
    }
    .flatpickr-calendar.animate.open { animation: none; }
    .flatpickr-calendar::before, .flatpickr-calendar::after { display: none; }
    .flatpickr-months { align-items: center; padding: 0 2px 8px; }
    .flatpickr-months .flatpickr-month { order: -1; flex: 1; height: 28px; overflow: visible; color: #202124; }
    .flatpickr-current-month {
        position: static; width: auto; height: 28px; padding: 0; display: flex; align-items: center; gap: 4px;
        font-size: 13px; font-weight: 700; text-align: left;
    }
    .flatpickr-current-month span.cur-month { margin: 0; font-weight: 700; }
    .flatpickr-current-month span.cur-month:hover { background: none; }
    .flatpickr-current-month .numInputWrapper { width: 5ch; }
    .flatpickr-current-month .numInputWrapper:hover { background: none; }
    .flatpickr-current-month .numInputWrapper span { display: none; }
    .flatpickr-current-month input.cur-year { padding: 0; font-weight: 700; pointer-events: none; }
    .flatpickr-current-month::after { content: ""; border: 4px solid transparent; border-top: 5px solid #202124; margin: 4px 0 0 -6px; }
    .flatpickr-months .flatpickr-prev-month, .flatpickr-months .flatpickr-next-month {
        position: static; width: 32px; height: 28px; padding: 5px 7px; color: #202124; fill: #202124; border-radius: 4px;
    }
    .flatpickr-months .flatpickr-prev-month:hover, .flatpickr-months .flatpickr-next-month:hover { background: #f1f3f4; }
    .flatpickr-months .flatpickr-prev-month:hover svg, .flatpickr-months .flatpickr-next-month:hover svg { fill: #202124; }
    .flatpickr-months .flatpickr-prev-month svg, .flatpickr-months .flatpickr-next-month svg { width: 18px; height: 18px; }
    .flatpickr-weekdays { height: 28px; }
    span.flatpickr-weekday { color: #202124; font-size: 12px; font-weight: 600; }
    .flatpickr-days { width: 231px !important; }
    .dayContainer { width: 231px; min-width: 231px; max-width: 231px; }
    .flatpickr-day {
        max-width: 31px; height: 30px; line-height: 26px; margin: 1px 0; border: 2px solid transparent; border-radius: 3px;
        font-size: 13px; color: #202124;
    }
    .flatpickr-day:hover, .flatpickr-day:focus { background: #f1f3f4; border-color: transparent; }
    .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay { color: #80868b; }
    .flatpickr-day.today { background: #6e6e6e; border-color: #6e6e6e; color: #fff; font-weight: 700; }
    .flatpickr-day.today:hover { background: #5a5a5a; border-color: #5a5a5a; color: #fff; }
    .flatpickr-day.selected, .flatpickr-day.selected:hover, .flatpickr-day.selected:focus {
        background: #e3e3e3; border-color: #202124; color: #202124; font-weight: 700;
    }
    .flatpickr-day.flatpickr-disabled, .flatpickr-day.flatpickr-disabled:hover { color: #c8cacd; background: none; border-color: transparent; cursor: not-allowed; }
    .flatpickr-day.today.flatpickr-disabled { color: #fff; background: #b4b4b4; border-color: #b4b4b4; }
    .dp-foot { display: flex; justify-content: space-between; padding: 10px 2px 2px; }
    .dp-foot button { border: 0; background: none; padding: 4px 6px; border-radius: 4px; color: #1a73e8; font-size: 13px; cursor: pointer; }
    .dp-foot button:hover { background: #e8f0fe; }
    .filters input[type=search] { width: 240px; border-radius: 999px; }
    .day-nav { display: inline-flex; border: 1px solid var(--line-strong); border-radius: 999px; overflow: hidden; background: var(--glass-strong); }
    .day-nav a { padding: 9px 14px; font-size: 13px; font-weight: 600; color: var(--text); }
    .day-nav a + a { border-left: 1px solid var(--line-strong); }
    .day-nav a:hover { background: #fff; }
    .summary { display: flex; gap: 8px; flex-wrap: wrap; margin-left: auto; }
    .summary .chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; border-radius: 999px; font-size: 13px; white-space: nowrap; }
    .summary .chip.hadir { background: var(--success-bg); color: var(--success); }
    .summary .chip.absen { background: #fee2e2; color: #b91c1c; }
    td.nowrap { white-space: nowrap; }
    .k { font-weight: 700; border-radius: 6px; }
    .k-red { background: #fee2e2; color: #b91c1c; }
    .k-amber { background: #fef3c7; color: #92400e; }
    .k-violet { background: #ede9fe; color: #6d28d9; }
    .k-orange { background: #ffedd5; color: #c2410c; }
    .k-blue { background: #dbeafe; color: #1d4ed8; }
    .k-green { background: #dcfce7; color: #15803d; }
    .k-tag { display: inline-block; min-width: 40px; padding: 3px 8px; text-align: center; font-size: 12px; font-style: normal; }
    textarea { width: 100%; padding: 11px 14px; font: inherit; font-size: 14px; border: 1px solid var(--line-strong); border-radius: 12px; resize: vertical; }
    /* Pilih karyawan ala select2: field tertutup, klik untuk cari, hasil muncul setelah mengetik. */
    .combo { position: relative; }
    .combo-trigger {
        width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left;
        padding: 11px 14px; font: inherit; font-size: 14px; color: var(--text); cursor: pointer;
        background: var(--glass-strong); border: 1px solid var(--line-strong); border-radius: 12px;
    }
    .combo-trigger .placeholder { color: var(--faint); }
    .combo-trigger:disabled { cursor: default; color: var(--muted); background: #f4f6fa; }
    .combo-trigger:disabled svg { display: none; }
    .combo.open .combo-trigger { outline: 2px solid var(--primary); outline-offset: -1px; background: #fff; }
    .combo-panel {
        position: absolute; z-index: 5; top: calc(100% + 4px); left: 0; right: 0; padding: 8px;
        background: #fff; border: 1px solid var(--line-strong); border-radius: 12px; box-shadow: 0 12px 32px rgba(30, 64, 175, .16);
    }
    .combo-panel input[type=search] { padding: 9px 12px; }
    .combo-list { max-height: 220px; overflow-y: auto; margin-top: 6px; }
    .combo-list:empty { display: none; }
    .combo-option { padding: 8px 10px; border-radius: 8px; font-size: 13px; cursor: pointer; }
    .combo-option.active { background: var(--soft-2); }
    .combo-option.selected { font-weight: 600; color: var(--navy); }
    .combo-hint { padding: 8px 10px 2px; font-size: 12px; color: var(--muted); }
    input[type=date] { width: 100%; padding: 11px 14px; font: inherit; font-size: 14px; color: var(--text); background: var(--glass-strong); border: 1px solid var(--line-strong); border-radius: 12px; }
@endsection

@section('content')
@php
    // Navigasi hari melompati Sabtu & Minggu (absensi hanya Senin - Jumat).
    $prev = $tanggal->copy()->subWeekday()->toDateString();
    $next = $tanggal->copy()->addWeekday()->toDateString();
    $tidakHadir = $absensis->filter(fn ($absensi) => ! ($kodes[$absensi->kode]['hadir'] ?? false))->count();
    $fields = ['karyawan_id', 'tanggal', 'sampai', 'kode', 'keterangan'];
@endphp

<div class="ent-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Absensi Karyawan</h1>
        <p class="muted" style="margin: 2px 0 0;">
            {{ $tanggal->translatedFormat('l, d F Y') }}
            @if ($lockedDepartment)
                · departemen <span class="badge">{{ $lockedDepartment->name }}</span>
            @endif
        </p>
    </div>
    @if ($canEdit)
        <button type="button" class="btn" data-create>+ Tambah Absensi</button>
    @endif
</div>

@if ($missingDepartment)
    <div class="alert">Akun Anda adalah Admin Departemen tetapi belum dipilihkan departemen. Hubungi Super Admin.</div>
@endif

<form method="GET" class="filters">
    <span class="day-nav">
        <a href="{{ route('hrms.absensi.index', ['tanggal' => $prev]) }}" title="Hari sebelumnya">&lsaquo;</a>
        <a href="{{ route('hrms.absensi.index') }}">Hari ini</a>
        <a href="{{ route('hrms.absensi.index', ['tanggal' => $next]) }}" title="Hari berikutnya">&rsaquo;</a>
    </span>
    <input type="text" name="tanggal" value="{{ $tanggal->toDateString() }}" data-weekday-picker data-submit aria-label="Tanggal">
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari NIK / nama...">
    <div class="summary">
        <span class="chip hadir">Hadir <b>{{ $karyawans->count() - $tidakHadir }}</b></span>
        <span class="chip absen">Tidak hadir <b>{{ $tidakHadir }}</b></span>
        <span class="chip" style="background: var(--glass-strong);">dari {{ $karyawans->count() }} karyawan</span>
    </div>
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>NIK</th>
                    <th>Nama</th>
                    <th>Group</th>
                    <th>Kode</th>
                    <th>Keterangan</th>
                    <th>Diinput oleh</th>
                    @if ($canEdit)
                        <th></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($absensis as $absensi)
                    @php
                        $info = $kodes[$absensi->kode] ?? ['label' => $absensi->kode, 'tone' => 'blue'];
                    @endphp
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td class="nowrap">{{ $absensi->karyawan->nik }}</td>
                        <td style="font-weight: 600;">{{ $absensi->karyawan->nama }}</td>
                        <td class="nowrap">{{ $absensi->karyawan->group ?? '-' }}</td>
                        <td class="nowrap"><i class="k k-tag k-{{ $info['tone'] }}">{{ $absensi->kode }}</i> <span class="small muted">{{ $info['label'] }}</span></td>
                        <td>{{ $absensi->keterangan ?: '-' }}</td>
                        <td class="small muted">{{ $absensi->creator?->name ?? '-' }}</td>
                        @if ($canEdit)
                            <td style="text-align: right; white-space: nowrap;">
                                <button type="button" class="btn btn-ghost btn-sm" data-edit="{{ $absensi->id }}"
                                        data-record="{{ json_encode([
                                            'karyawan_id' => $absensi->karyawan_id,
                                            'tanggal' => $absensi->tanggal->toDateString(),
                                            'kode' => $absensi->kode,
                                            'keterangan' => $absensi->keterangan,
                                        ]) }}">Edit</button>
                                <button type="button" class="btn btn-danger btn-sm" data-delete="{{ $absensi->id }}"
                                        data-name="{{ $absensi->karyawan->nik }} - {{ $absensi->karyawan->nama }}">Hapus</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted" style="text-align: center; padding: 32px;">
                            {{ $search !== '' ? 'Data tidak ditemukan.' : 'Belum ada karyawan yang tidak hadir di tanggal ini — semua dianggap masuk.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($canEdit)
    {{-- Modal tambah / edit --}}
    <dialog class="modal" id="entry-modal" style="width: min(560px, calc(100% - 32px));">
        <form method="POST" action="{{ route('hrms.absensi.entries.store') }}" id="entry-form">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="create">
            <div class="modal-head">
                <h2 id="entry-modal-title">Tambah Absensi</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body" style="display: grid; gap: 14px;">
                <div>
                    <label for="combo-trigger">Karyawan</label>
                    <input type="hidden" name="karyawan_id" id="f-karyawan_id">
                    <div class="combo" id="karyawan-combo">
                        <button type="button" class="combo-trigger" id="combo-trigger" aria-haspopup="listbox" aria-expanded="false">
                            <span id="combo-label" class="placeholder">Pilih karyawan...</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="combo-panel" id="combo-panel" hidden>
                            <input type="search" id="f-karyawan-search" placeholder="Ketik NIK / nama..." autocomplete="off" aria-label="Cari karyawan">
                            <div class="combo-list" id="combo-list" role="listbox"></div>
                            <div class="combo-hint" id="combo-hint">Ketik NIK atau nama untuk mencari.</div>
                        </div>
                    </div>
                    @error('karyawan_id') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="grid-2">
                    <div>
                        <label for="f-tanggal">Tanggal</label>
                        <input id="f-tanggal" type="text" name="tanggal" placeholder="Pilih tanggal" data-weekday-picker required>
                        @error('tanggal') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div data-create-only>
                        <label for="f-sampai">Sampai <span class="muted">(opsional)</span></label>
                        <input id="f-sampai" type="text" name="sampai" placeholder="Pilih tanggal" data-weekday-picker>
                        @error('sampai') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="muted small" data-create-only style="margin-top: -6px;">Hanya hari Senin - Jumat. Isi "Sampai" untuk cuti / sakit beberapa hari; Sabtu & Minggu dilewati.</div>
                <div>
                    <label for="f-kode">Kode</label>
                    <select id="f-kode" name="kode" required>
                        <option value="">Pilih kode...</option>
                        @foreach ($kodes as $kode => $info)
                            <option value="{{ $kode }}">{{ $kode }} — {{ $info['label'] }}</option>
                        @endforeach
                    </select>
                    @error('kode') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="f-keterangan">Keterangan <span class="muted">(opsional)</span></label>
                    <input id="f-keterangan" type="text" name="keterangan" maxlength="255" placeholder="Mis. Cuti pulang kampung, sakit demam, DT 07:27">
                    @error('keterangan') <div class="field-error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn">Simpan</button>
            </div>
        </form>
    </dialog>

    {{-- Modal hapus --}}
    <dialog class="modal" id="delete-modal">
        <form method="POST" id="delete-form">
            @csrf
            @method('DELETE')
            <div class="modal-head">
                <h2>Hapus Absensi</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                Hapus data absensi <strong id="delete-name"></strong>? Karyawan akan dianggap <strong>masuk</strong> di tanggal ini.
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" style="background: var(--danger);">Hapus</button>
            </div>
        </form>
    </dialog>
@endif
@endsection

@if ($canEdit)
@push('scripts')
<script>
    (function () {
        var fields = {{ Js::from($fields) }};
        var modal = document.getElementById('entry-modal');
        var form = document.getElementById('entry-form');
        var storeUrl = {{ Js::from(route('hrms.absensi.entries.store')) }};
        var updateUrl = {{ Js::from(route('hrms.absensi.entries.update', '__ID__')) }};
        var destroyUrl = {{ Js::from(route('hrms.absensi.entries.destroy', '__ID__')) }};
        // Default tanggal di form = tanggal halaman, kecuali Sabtu/Minggu (dibiarkan kosong).
        var tanggal = {{ Js::from($tanggal->isWeekend() ? '' : $tanggal->toDateString()) }};
        var karyawans = {{ Js::from($karyawans->map(fn ($k) => ['id' => (string) $k->id, 'label' => $k->nik.' - '.$k->nama.($k->group ? ' ('.$k->group.')' : '')])) }};
        var combo = document.getElementById('karyawan-combo');
        var trigger = document.getElementById('combo-trigger');
        var panel = document.getElementById('combo-panel');
        var search = document.getElementById('f-karyawan-search');
        var list = document.getElementById('combo-list');
        var hint = document.getElementById('combo-hint');
        var hidden = form.elements.karyawan_id;
        var MAX_RESULTS = 50;
        var results = [], active = -1;

        function labelOf(id) {
            var found = karyawans.find(function (k) { return k.id === String(id); });
            return found ? found.label : '';
        }

        function choose(id) {
            hidden.value = id || '';
            var label = document.getElementById('combo-label');
            label.textContent = id ? labelOf(id) : 'Pilih karyawan...';
            label.classList.toggle('placeholder', !id);
        }

        // Hasil baru muncul setelah user mengetik kata kunci.
        function render() {
            var term = search.value.trim().toLowerCase();
            list.innerHTML = '';
            results = term === '' ? [] : karyawans.filter(function (k) { return k.label.toLowerCase().indexOf(term) !== -1; });
            active = results.length ? 0 : -1;
            results.slice(0, MAX_RESULTS).forEach(function (k, index) {
                var option = document.createElement('div');
                option.className = 'combo-option' + (index === active ? ' active' : '') + (k.id === hidden.value ? ' selected' : '');
                option.setAttribute('role', 'option');
                option.textContent = k.label;
                option.addEventListener('mousedown', function (event) { event.preventDefault(); pick(index); });
                list.appendChild(option);
            });
            hint.hidden = term !== '' && results.length > 0 && results.length <= MAX_RESULTS;
            hint.textContent = term === '' ? 'Ketik NIK atau nama untuk mencari.'
                : results.length === 0 ? 'Karyawan tidak ditemukan.'
                : 'Menampilkan ' + MAX_RESULTS + ' dari ' + results.length + ' hasil, perjelas kata kunci.';
        }

        function setActive(index) {
            var options = list.children;
            if (!options.length) return;
            active = (index + options.length) % options.length;
            Array.prototype.forEach.call(options, function (el, i) { el.classList.toggle('active', i === active); });
            options[active].scrollIntoView({ block: 'nearest' });
        }

        function pick(index) {
            if (!results[index]) return;
            choose(results[index].id);
            close();
            form.elements.kode.focus();
        }

        function open() {
            if (trigger.disabled) return;
            panel.hidden = false;
            combo.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
            search.value = '';
            render();
            search.focus();
        }

        function close() {
            panel.hidden = true;
            combo.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', function () { panel.hidden ? open() : close(); });
        search.addEventListener('input', render);
        search.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') { event.preventDefault(); setActive(active + 1); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); setActive(active - 1); }
            else if (event.key === 'Enter') { event.preventDefault(); pick(active); }
            else if (event.key === 'Escape') { event.preventDefault(); close(); trigger.focus(); } // jangan ikut menutup modal
        });
        modal.addEventListener('mousedown', function (event) {
            if (!panel.hidden && !combo.contains(event.target)) close();
        });
        form.addEventListener('submit', function (event) {
            if (!hidden.disabled && !hidden.value) {
                event.preventDefault();
                open();
                hint.textContent = 'Pilih karyawan dulu.';
            }
        });

        function openForm(id, record, keepErrors) {
            form.action = id ? updateUrl.replace('__ID__', id) : storeUrl;
            form.elements._method.value = id ? 'PUT' : 'POST';
            form.elements._modal.value = id ? 'edit:' + id : 'create';
            document.getElementById('entry-modal-title').textContent = id ? 'Edit Absensi' : 'Tambah Absensi';
            close();
            choose(record && record.karyawan_id);
            fields.forEach(function (name) {
                if (name !== 'karyawan_id') form.elements[name].value = (record && record[name]) || '';
            });
            if (!form.elements.tanggal.value) form.elements.tanggal.value = tanggal;
            // Saat edit, karyawan tidak diganti dan tidak ada rentang tanggal.
            hidden.disabled = !!id;
            trigger.disabled = !!id;
            modal.querySelectorAll('[data-create-only]').forEach(function (el) { el.hidden = !!id; });
            form.elements.sampai.disabled = !!id;
            // Kalender (flatpickr) diselaraskan dengan nilai yang baru diisi.
            ['tanggal', 'sampai'].forEach(function (name) {
                var picker = form.elements[name]._flatpickr;
                if (!picker) return;
                picker.setDate(form.elements[name].value || null, false);
                picker.altInput.disabled = form.elements[name].disabled;
            });
            if (!keepErrors) modal.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });
            modal.showModal();
            (id ? form.elements.kode : trigger).focus();
        }

        document.querySelector('[data-create]').addEventListener('click', function () { openForm(null, null); });
        document.querySelectorAll('[data-edit]').forEach(function (button) {
            button.addEventListener('click', function () { openForm(button.dataset.edit, JSON.parse(button.dataset.record)); });
        });
        document.querySelectorAll('[data-delete]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('delete-form').action = destroyUrl.replace('__ID__', button.dataset.delete);
                document.getElementById('delete-name').textContent = button.dataset.name;
                document.getElementById('delete-modal').showModal();
            });
        });
        document.querySelectorAll('dialog.modal').forEach(function (dialog) {
            dialog.querySelectorAll('[data-close]').forEach(function (button) {
                button.addEventListener('click', function () { dialog.close(); });
            });
            dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
        });

        // Validasi gagal: buka lagi modal yang tadi dipakai beserta isiannya.
        var reopen = {{ Js::from($errors->hasAny($fields) ? old('_modal') : null) }};
        if (reopen) {
            openForm(reopen.indexOf('edit:') === 0 ? reopen.slice(5) : null, {{ Js::from(collect(old())->only($fields)) }}, true);
        }
    })();
</script>
@endpush
@endif

@push('scripts')
<script>
    // Kalender tanggal yang hanya bisa memilih Senin - Jumat (input date bawaan browser tidak bisa menonaktifkan hari tertentu).
    (function () {
        var base = {{ Js::from(asset('vendor/flatpickr')) }};

        function load(src, done) {
            var script = document.createElement('script');
            script.src = src;
            script.onload = done;
            document.head.appendChild(script);
        }

        var arrow = function (path) {
            return '<svg viewBox="0 0 24 24"><path d="' + path + '"/></svg>';
        };

        function init() {
            document.querySelectorAll('[data-weekday-picker]').forEach(function (input) {
                if (input._flatpickr) return;
                flatpickr(input, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    altInputClass: 'dp-input',
                    monthSelectorType: 'static',
                    disableMobile: true,
                    // <dialog> modal tampil di lapisan paling atas; kalender harus berada di dalamnya supaya tidak tertutup.
                    static: !!input.closest('dialog'),
                    prevArrow: arrow('M11 20V7.8l-5.6 5.6L4 12l8-8 8 8-1.4 1.4L13 7.8V20z'),
                    nextArrow: arrow('M13 4v12.2l5.6-5.6L20 12l-8 8-8-8 1.4-1.4 5.6 5.6V4z'),
                    disable: [function (date) { return date.getDay() === 0 || date.getDay() === 6; }],
                    onReady: function (selected, value, picker) {
                        // Tombol Clear & Today seperti date picker bawaan browser.
                        var foot = document.createElement('div');
                        foot.className = 'dp-foot';
                        foot.innerHTML = '<button type="button" data-clear>Clear</button><button type="button" data-today>Today</button>';
                        foot.querySelector('[data-clear]').addEventListener('click', function () { picker.clear(); picker.close(); });
                        foot.querySelector('[data-today]').addEventListener('click', function () {
                            picker.jumpToDate(new Date());
                            picker.setDate(new Date(), true);
                            if (picker.selectedDates.length) picker.close();
                        });
                        picker.calendarContainer.appendChild(foot);
                    },
                    onChange: function () {
                        if ('submit' in input.dataset) input.form.requestSubmit();
                    },
                });
            });
        }

        // Halaman disimpan ke cache Turbo tanpa kalender, supaya tidak terpasang dobel saat tombol Back.
        document.addEventListener('turbo:before-cache', function () {
            document.querySelectorAll('[data-weekday-picker]').forEach(function (input) {
                if (input._flatpickr) input._flatpickr.destroy();
            });
        }, { once: true });

        if (window.flatpickr) return init();
        load(base + '/flatpickr.min.js', init);
    })();
</script>
@endpush
