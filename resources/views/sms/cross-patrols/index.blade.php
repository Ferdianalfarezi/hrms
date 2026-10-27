@extends('layouts.app')

@section('title', 'Cross Patrol')

@section('styles')
    @include('sms.partials.patrol-styles')
    table.patrol .c-check { width: 32px; padding-right: 0; }
    table.patrol .c-check input { width: 16px; height: 16px; cursor: pointer; accent-color: var(--navy); }
    table.patrol tr.checked td { background: #f2f6ff; }

    #print-modal { width: min(920px, calc(100% - 32px)); height: calc(100vh - 48px); max-height: none; }
    #print-modal[open] { display: flex; flex-direction: column; }
    .print-frame-wrap { position: relative; flex: 1; min-height: 0; margin: 16px 24px; border-radius: 12px; overflow: hidden; background: #525659; }
    .print-frame-wrap iframe { display: block; width: 100%; height: 100%; border: 0; }
    .print-loading {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; gap: 10px;
        color: #fff; font-size: 13px; font-weight: 600; background: #525659;
    }
    .print-loading .spinner { width: 26px; height: 26px; border-width: 3px; }
    /* display: flex di atas mengalahkan atribut hidden. */
    .print-loading[hidden] { display: none; }
    @media (max-width: 720px) { .print-frame-wrap { margin: 12px; } }
@endsection

@section('content')
@php
    $resikoTone = collect($resikos)->map(fn ($info) => $info['tone']);
    $fields = ['tanggal', 'plant', 'zona', 'finding', 'efek', 'resiko', 'area', 'department_id', 'rekomendasi', 'target', 'pic', 'kategori', 'patroler', 'status', 'foto', 'foto_perbaikan'];
    $listFilters = array_filter(['q' => $search, 'status' => $status, 'department' => $department, 'tanggal' => $tanggal, 'plant' => $plant, 'zona' => $zona]);
    $keepFilters = $listFilters + array_filter(['page' => $patrols->currentPage() > 1 ? $patrols->currentPage() : null]);
@endphp

<div class="dp-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Cross Patrol</h1>
        <p class="muted" style="margin: 2px 0 0;">
            Form temuan safety patrol
            @if ($lockedDepartments->isNotEmpty())
                · departemen
                @foreach ($lockedDepartments as $lockedDepartment)
                    <span class="badge">{{ $lockedDepartment->name }}</span>
                @endforeach
            @endif
        </p>
    </div>
    <div class="dp-actions">
        {{-- Export mengikuti filter yang sedang dipakai; data-turbo="false" karena hasilnya file, bukan halaman. --}}
        <a href="{{ route('sms.cross-patrols.export', $listFilters) }}" class="btn btn-ghost" data-turbo="false">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Export Excel
        </a>
        {{-- Tanpa centang = cetak semua hasil filter; ada centang = cetak yang dicentang saja. --}}
        <button type="button" class="btn btn-ghost" data-print-bulk data-total="{{ $patrols->total() }}" @disabled($patrols->total() === 0)>
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            <span data-print-label>Print semua ({{ $patrols->total() }})</span>
        </button>
        @if ($canManage)
            <button type="button" class="btn" data-create>+ Tambah Temuan</button>
        @endif
    </div>
</div>

@if ($missingDepartment)
    <div class="alert">Akun Anda adalah Admin Departemen SMS tetapi belum dipilihkan departemen. Hubungi Super Admin.</div>
@endif

<form method="GET" class="filters">
    <input type="date" name="tanggal" value="{{ $tanggal }}" onchange="this.form.requestSubmit()" aria-label="Tanggal" title="Tanggal (kosongkan untuk semua)">
    <select name="plant" onchange="this.form.requestSubmit()" aria-label="Plant">
        <option value="">Semua plant</option>
        @foreach ($plantOptions as $option)
            <option value="{{ $option }}" @selected($plant === $option)>{{ $option }}</option>
        @endforeach
    </select>
    <select name="zona" onchange="this.form.requestSubmit()" aria-label="Zona" style="min-width: 120px;">
        <option value="">Semua zona</option>
        @foreach ($zonas as $option)
            <option value="{{ $option }}" @selected($zona === (string) $option)>Zona {{ $option }}</option>
        @endforeach
    </select>
    <select name="status" onchange="this.form.requestSubmit()" aria-label="Status">
        <option value="">Semua status</option>
        <option value="open" @selected($status === 'open')>Open</option>
        <option value="closed" @selected($status === 'closed')>Close</option>
    </select>
    @if ($canFilterDepartment)
        <select name="department" onchange="this.form.requestSubmit()" aria-label="Departemen">
            <option value="">Semua departemen</option>
            @foreach ($departments as $option)
                <option value="{{ $option->id }}" @selected($department === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    @endif
    <input type="search" name="q" value="{{ $search }}" placeholder="Cari finding / area / PIC / patroler...">
    @if ($listFilters)
        <a href="{{ route('sms.cross-patrols.index') }}" class="btn btn-ghost btn-sm">Reset</a>
    @endif
    <div class="summary">
        <span class="chip">Total <b>{{ $patrols->total() }}</b></span>
    </div>
</form>

<div class="card" style="padding: 8px 12px;">
    <div class="table-wrap">
        <table class="patrol">
            <thead>
                <tr>
                    <th class="c-check"><input type="checkbox" data-check-all aria-label="Pilih semua di halaman ini" @disabled($patrols->isEmpty())></th>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>Foto</th>
                    <th>Finding</th>
                    <th>Resiko</th>
                    <th>Target</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($patrols as $patrol)
                    <tr>
                        <td class="c-check"><input type="checkbox" value="{{ $patrol->id }}" data-check aria-label="Pilih temuan {{ $patrols->firstItem() + $loop->index }}"></td>
                        <td class="muted">{{ $patrols->firstItem() + $loop->index }}</td>
                        <td class="nowrap">{{ $patrol->tanggal->format('d M Y') }}</td>
                        <td>
                            <button type="button" class="thumb" data-photo="{{ $patrol->foto_url }}" title="Lihat foto temuan">
                                <img src="{{ $patrol->foto_url }}" alt="Foto temuan" loading="lazy">
                            </button>
                        </td>
                        <td class="c-finding" style="max-width: 360px;">
                            {{ $patrol->finding }}
                            <span class="sub">{{ $patrol->area }} · {{ $patrol->zonaLabel() }} · {{ $patrol->plant }}@if ($canFilterDepartment) · {{ $patrol->department?->name }}@endif</span>
                        </td>
                        <td><span class="risk risk-{{ $resikoTone[$patrol->resiko] ?? 'orange' }}">{{ $patrol->resikoLabel() }}</span></td>
                        <td class="nowrap">{{ $patrol->target->format('d M Y') }}</td>
                        <td>
                            @if ($patrol->isClosed())
                                <span class="status-pill status-closed" title="Ditutup {{ $patrol->closed_at?->format('d/m/Y H:i') }}">Close</span>
                            @else
                                <span class="status-pill status-open">Open</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            @include('sms.partials.patrol-actions', ['printable' => true, 'record' => [
                                'tanggal' => $patrol->tanggal->toDateString(),
                                'plant' => $patrol->plant,
                                'zona' => $patrol->zona ? (string) $patrol->zona : '',
                                'finding' => $patrol->finding,
                                'efek' => $patrol->efek,
                                'resiko' => $patrol->resiko,
                                'area' => $patrol->area,
                                'department_id' => (string) $patrol->department_id,
                                'rekomendasi' => $patrol->rekomendasi,
                                'target' => $patrol->target->toDateString(),
                                'pic' => $patrol->pic,
                                'kategori' => $patrol->kategori,
                                'patroler' => $patrol->patroler,
                                'status' => $patrol->status,
                                'foto_url' => $patrol->foto_url,
                                'foto_perbaikan_url' => $patrol->foto_perbaikan_url,
                            ]])
                            @include('sms.partials.patrol-detail', ['items' => [
                                ['Finding', $patrol->finding, true],
                                ['Plant', $patrol->plant],
                                ['Zona', $patrol->zonaLabel()],
                                ['Area', $patrol->area],
                                ['Departemen', $patrol->department?->name],
                                ['Efek / Impact', $patrol->efek],
                                ['Tingkat Resiko Bahaya', $patrol->resikoLabel()],
                                ['Rekomendasi Perbaikan', $patrol->rekomendasi, true],
                                ['Target Perbaikan', $patrol->target->translatedFormat('d F Y')],
                                ['Kategori', $patrol->kategoriLabel()],
                                ['PIC', $patrol->pic],
                                ['Patroler', $patrol->patroler],
                            ]])
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="muted" style="text-align: center; padding: 32px;">
                            {{ $listFilters ? 'Data tidak ditemukan.' : 'Belum ada temuan Cross Patrol.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $patrols->links() }}
</div>

@include('sms.partials.patrol-modals')

{{-- Preview cetak: halaman A4 asli dimuat di iframe, tombol Print mencetak isi iframe saja. --}}
<dialog class="modal" id="print-modal">
    <div class="modal-head">
        <div>
            <h2>Preview Print</h2>
            <div class="muted small" id="print-info"></div>
        </div>
        <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
    </div>
    <div class="print-frame-wrap">
        <div class="print-loading" id="print-loading"><span class="spinner" aria-hidden="true"></span> Menyiapkan halaman...</div>
        <iframe id="print-frame" title="Preview print"></iframe>
    </div>
    <div class="modal-foot">
        <span class="muted small" style="margin-right: auto;">Kertas A4 portrait · maksimal 25 kotak per halaman</span>
        <button type="button" class="btn btn-ghost" data-close>Batal</button>
        <button type="button" class="btn" id="print-go" disabled>
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
        </button>
    </div>
</dialog>

@if ($canEdit)
    {{-- Modal tambah / edit --}}
    <dialog class="modal" id="patrol-modal">
        <form method="POST" action="{{ route('sms.cross-patrols.store') }}" id="patrol-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" value="POST">
            <input type="hidden" name="_modal" value="create">
            @foreach ($keepFilters as $key => $value)
                {{-- Filter & halaman daftar dipertahankan setelah simpan. --}}
                <input type="hidden" name="keep[{{ $key }}]" value="{{ $value }}">
            @endforeach
            <div class="modal-head">
                <h2 id="patrol-modal-title">Tambah Temuan</h2>
                <button type="button" class="modal-close" data-close aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                @unless ($canManage)
                    <div class="locked-note">Admin Departemen hanya bisa mengisi bagian <b>Perbaikan</b>. Data temuan lainnya diisi oleh Admin SMS.</div>
                @endunless
                {{-- Admin Departemen: semua field temuan dikunci (abu-abu), hanya kotak Perbaikan yang bisa diisi. --}}
                <fieldset class="patrol-main" @disabled(! $canManage)>
                <div class="grid-2">
                    <div>
                        <label for="f-tanggal">Tanggal</label>
                        <input id="f-tanggal" type="date" name="tanggal" required>
                        @error('tanggal') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-plant">Plant</label>
                        <input id="f-plant" type="text" name="plant" maxlength="50" placeholder="Mis. Zona 1" list="plant-list" required>
                        <datalist id="plant-list">
                            @foreach ($plantOptions as $option)
                                <option value="{{ $option }}">
                            @endforeach
                        </datalist>
                        @error('plant') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div>
                    <label for="f-zona">Zona</label>
                    <select id="f-zona" name="zona" required>
                        <option value="">-- Pilih Zona --</option>
                        @foreach ($zonas as $option)
                            <option value="{{ $option }}">Zona {{ $option }}</option>
                        @endforeach
                    </select>
                    @error('zona') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="f-finding">Finding</label>
                    <textarea id="f-finding" name="finding" maxlength="2000" rows="2" placeholder="Mis. Kabel AC menjuntai" required></textarea>
                    @error('finding') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="f-foto">Foto temuan <span class="muted" data-edit-only>(kosongkan kalau tidak diganti)</span></label>
                    <div class="photo-field">
                        <img class="photo-preview" id="preview-foto" alt="" hidden>
                        <input id="f-foto" type="file" name="foto" accept="image/*" data-photo-input data-preview="preview-foto">
                    </div>
                    @error('foto') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="grid-2">
                    <div>
                        <label for="f-efek">Efek / Impact</label>
                        <input id="f-efek" type="text" name="efek" maxlength="2000" placeholder="Mis. Tersetrum" required>
                        @error('efek') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-resiko">Tingkat Resiko Bahaya</label>
                        <select id="f-resiko" name="resiko" required>
                            <option value="">-- Pilih --</option>
                            @foreach ($resikos as $kode => $info)
                                <option value="{{ $kode }}">{{ $info['label'] }}</option>
                            @endforeach
                        </select>
                        @error('resiko') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="grid-2">
                    <div>
                        <label for="f-area">Area</label>
                        <input id="f-area" type="text" name="area" maxlength="100" placeholder="Mis. OBEYA, QAD" required>
                        @error('area') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-department_id">Departemen</label>
                        @if ($canFilterDepartment)
                            <select id="f-department_id" name="department_id" required>
                                <option value="">-- Pilih Departemen --</option>
                                @foreach ($departments as $option)
                                    <option value="{{ $option->id }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="locked-field">{{ $lockedDepartments->first()?->name ?? '-' }}</div>
                        @endif
                        @error('department_id') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div>
                    <label for="f-rekomendasi">Rekomendasi Perbaikan</label>
                    <textarea id="f-rekomendasi" name="rekomendasi" maxlength="2000" rows="2" placeholder="Mis. Kabel AC dirapikan" required></textarea>
                    @error('rekomendasi') <div class="field-error">{{ $message }}</div> @enderror
                </div>
                <div class="grid-2">
                    <div>
                        <label for="f-target">Target Perbaikan</label>
                        <input id="f-target" type="date" name="target" required>
                        @error('target') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="f-kategori">Kategori</label>
                        <select id="f-kategori" name="kategori" required>
                            <option value="">-- Pilih Kategori --</option>
                            @foreach ($kategoris as $group => $options)
                                <optgroup label="── {{ $group }} ──">
                                    @foreach ($options as $kode => $label)
                                        <option value="{{ $kode }}">{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('kategori') <div class="field-error">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="grid-2">
                    @include('sms.partials.karyawan-picker', ['name' => 'pic', 'label' => 'PIC'])
                    @include('sms.partials.karyawan-picker', ['name' => 'patroler', 'label' => 'Patroler'])
                </div>
                </fieldset>

                @include('sms.partials.patrol-fix-fields')
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close>Batal</button>
                <button type="submit" class="btn" id="patrol-submit">Simpan</button>
            </div>
        </form>
    </dialog>
@endif
@endsection

@push('scripts')
    @if ($canEdit)
        @include('sms.partials.karyawan-picker-script')
    @endif
    @include('sms.partials.patrol-script', ['routePrefix' => 'sms.cross-patrols'])
<script>
    // Print kartu temuan: per baris (icon print) atau bulk (dicentang / semua hasil filter), lewat modal preview.
    (function () {
        var printUrl = {{ Js::from(route('sms.cross-patrols.print')) }};
        var listFilters = {{ Js::from($listFilters) }};
        var modal = document.getElementById('print-modal');
        var frame = document.getElementById('print-frame');
        var loading = document.getElementById('print-loading');
        var go = document.getElementById('print-go');
        var bulk = document.querySelector('[data-print-bulk]');
        var checkAll = document.querySelector('[data-check-all]');
        var checks = Array.prototype.slice.call(document.querySelectorAll('[data-check]'));

        function selectedIds() {
            return checks.filter(function (box) { return box.checked; }).map(function (box) { return box.value; });
        }

        function syncSelection() {
            var ids = selectedIds();
            checks.forEach(function (box) { box.closest('tr').classList.toggle('checked', box.checked); });
            if (checkAll) {
                checkAll.checked = ids.length > 0 && ids.length === checks.length;
                checkAll.indeterminate = ids.length > 0 && ids.length < checks.length;
            }
            bulk.querySelector('[data-print-label]').textContent = ids.length
                ? 'Print ' + ids.length + ' terpilih'
                : 'Print semua (' + bulk.dataset.total + ')';
        }

        function openPreview(params) {
            var query = new URLSearchParams(params);
            loading.hidden = false;
            go.disabled = true;
            document.getElementById('print-info').textContent = '';
            frame.src = printUrl + '?' + query.toString();
            modal.showModal();
        }

        // Iframe selesai dimuat (termasuk semua foto): tampilkan jumlah kotak & halaman, aktifkan tombol Print.
        frame.addEventListener('load', function () {
            if (!frame.src) return;
            var doc = frame.contentDocument;
            var cards = doc ? doc.querySelectorAll('.card').length : 0;
            var pages = doc ? doc.querySelectorAll('.sheet').length : 0;
            document.getElementById('print-info').textContent = cards
                ? cards + ' kotak · ' + pages + ' halaman A4'
                : 'Tidak ada temuan untuk dicetak.';
            loading.hidden = true;
            go.disabled = cards === 0;
        });

        go.addEventListener('click', function () {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        });
        modal.addEventListener('close', function () { frame.removeAttribute('src'); });

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                checks.forEach(function (box) { box.checked = checkAll.checked; });
                syncSelection();
            });
        }
        checks.forEach(function (box) { box.addEventListener('change', syncSelection); });

        document.querySelectorAll('[data-print]').forEach(function (button) {
            button.addEventListener('click', function () { openPreview([['ids[]', button.dataset.print]]); });
        });
        bulk.addEventListener('click', function () {
            var ids = selectedIds();
            openPreview(ids.length
                ? ids.map(function (id) { return ['ids[]', id]; })
                : Object.keys(listFilters).map(function (key) { return [key, listFilters[key]]; }));
        });
    })();
</script>
@endpush
