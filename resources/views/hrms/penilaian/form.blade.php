@extends('layouts.app')

@section('title', __('Penilaian').' '.$karyawan->nama)

@section('styles')
    .pn-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    .pn-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start; }
    @media (max-width: 1000px) { .pn-grid { grid-template-columns: 1fr; } }
    .side { position: sticky; top: var(--side-top, 150px); display: grid; gap: 16px; max-height: calc(100vh - var(--side-top, 150px) - 16px); overflow-y: auto; padding-bottom: 4px; }
    @media (max-width: 1000px) { .side { position: static; max-height: none; overflow: visible; } }
    .info { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px 16px; font-size: 13px; }
    .info dt { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
    .info dd { margin: 2px 0 0; font-weight: 600; }
    .sec { margin-top: 16px; }
    .sec h2 { font-size: 15px; margin-bottom: 8px; }
    .sec .sub { font-size: 12px; color: var(--muted); margin: -4px 0 8px; }
    td.num, th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    th.c, td.c { text-align: center; width: 44px; padding-left: 4px; padding-right: 4px; }
    th.c { padding-top: 0; padding-bottom: 0; vertical-align: bottom; }
    th.c .h-huruf, th.c .h-nilai { display: block; padding: 6px 0; font-size: 13px; color: var(--text); letter-spacing: 0; }
    th.c .h-huruf { border-bottom: 1px solid var(--line-strong); }
    th.c .h-nilai { font-weight: 500; color: var(--muted); }
    td.butir { font-size: 13px; }
    tfoot td { font-weight: 700; border-top: 2px solid var(--line-strong); font-size: 13px; }
    .pick { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--line-strong); cursor: pointer; font-weight: 700; font-size: 13px; color: var(--muted); background: var(--glass-strong); }
    .pick input { position: absolute; opacity: 0; pointer-events: none; }
    .pick:has(input:checked) { background: var(--navy); color: #fff; border-color: var(--navy); }
    .pick:has(input:disabled) { cursor: default; }
    .pick:has(input:focus-visible) { outline: 2px solid var(--primary); outline-offset: 1px; }
    tr.kosong td.butir { color: #b45309; }
    td.c { white-space: nowrap; }
    .centang { font-size: 16px; line-height: 1; margin: 0 1px; vertical-align: middle; }
    .legenda { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 16px; font-size: 13px; padding-top: 12px; padding-bottom: 12px; }
    .penilai { display: grid; border: 1px solid var(--line-strong); border-radius: 12px; overflow: hidden; font-size: 13px; }
    .penilai > div { display: grid; grid-template-rows: 1fr auto; text-align: center; border-left: 1px solid var(--line-strong); min-height: 110px; }
    .penilai > div:first-child { border-left: 0; }
    .penilai .ttd { padding: 10px 8px; display: flex; flex-direction: column; justify-content: center; gap: 2px; }
    .penilai .ttd .ok { color: #15803d; font-weight: 700; }
    .penilai .ttd .wait { color: #92400e; font-weight: 600; }
    .penilai .jab { padding: 6px 8px; border-top: 1px solid var(--line-strong); background: var(--soft-2); font-weight: 600; }
    .penilai .aktif .jab { background: #fef3c7; }
    .score { display: grid; grid-template-columns: 1fr auto; gap: 6px 12px; font-size: 14px; }
    .score .big { font-size: 28px; font-weight: 700; }
    .score .grade { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 12px; background: var(--navy); color: #fff; font-size: 22px; font-weight: 700; }
    .range { font-size: 12px; color: var(--muted); margin-top: 8px; }
    .st { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .st-draft { background: #dbeafe; color: #1d4ed8; }
    .st-menunggu { background: #fef3c7; color: #92400e; }
    .st-disetujui { background: #dcfce7; color: #15803d; }
    textarea { width: 100%; padding: 11px 14px; font: inherit; font-size: 14px; border: 1px solid var(--line-strong); border-radius: 12px; resize: vertical; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-success { background: var(--success); }
    .log { font-size: 13px; display: grid; gap: 10px; }
    .log .when { color: var(--muted); font-size: 12px; }
@endsection

@section('content')
@use('App\Modules\Hrms\Models\PenilaianTahunan')
@use('App\Modules\Hrms\Models\PenilaianTahunanLog')
@use('App\Modules\Hrms\Support\FormPenilaian')
@use('App\Modules\Hrms\Support\JenjangPenilaian')
@php
    $angka = fn (float $value) => number_format($value, 2, ',', '.');
    $ringkas = fn (float $value) => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    $readonly = $tahapSaya === null;
    // Skor milik user ini (pilihan awal approver = skor penilai sebelumnya) dan skor penilai lain (centang berwarna).
    $skor = old('skor', $readonly ? [] : $penilaian->skorUntukTahap($tahapSaya));
    $penilaiLain = collect($penilaian->skorPerPenilai())->except($readonly ? [] : [$tahapSaya]);
    $levelSayaSkor = JenjangPenilaian::userLevel(auth()->user());
    $legenda = $penilaiLain->pluck('level')->when(! $readonly, fn ($levels) => $levels->push($levelSayaSkor))->filter()->unique()->values();
@endphp

<div class="pn-head">
    <div>
        <a href="{{ route('hrms.penilaian.index', ['tahun' => $tahun]) }}" class="muted small">&lsaquo; {{ __('Kembali ke daftar') }}</a>
        <h1 style="font-size: clamp(22px, 3vw, 32px); font-weight: 500; letter-spacing: -.03em;">{{ __($form['label']) }} · {{ $tahun }}</h1>
        <p class="muted" style="margin: 2px 0 0;">
            {{ $form['dokumen'] }}
            @if ($penilaian->exists)
                · <span class="st st-{{ $penilaian->status }}">{{ __(PenilaianTahunan::STATUS[$penilaian->status]) }}</span>
            @endif
        </p>
    </div>
</div>

@if ($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
@endif

@if ($penilaian->tahapan === [])
    <div class="alert">{{ __('Belum ada akun penilai (jenjang di atas :jabatan) di departemen :departemen. Hubungi Admin HRMS.', ['jabatan' => $karyawan->jabatan, 'departemen' => $karyawan->department?->name ?? '-']) }}</div>
@endif

<div class="pn-grid">
    <div>
        <div class="card">
            <dl class="info" style="margin: 0;">
                <div><dt>NIK</dt><dd>{{ $karyawan->nik }}</dd></div>
                <div><dt>{{ __('Nama') }}</dt><dd>{{ $karyawan->nama }}</dd></div>
                <div><dt>{{ __('Jabatan') }}</dt><dd>{{ $penilaian->jabatan ?? $karyawan->jabatan }}</dd></div>
                <div><dt>{{ __('Bagian') }}</dt><dd>{{ $karyawan->department?->name ?? '-' }}</dd></div>
                <div><dt>{{ __('Periode') }}</dt><dd>{{ __('Jan - Des :tahun', ['tahun' => $tahun]) }}</dd></div>
                <div><dt>{{ __('Status') }}</dt><dd>{{ $karyawan->status ?? '-' }}</dd></div>
            </dl>
        </div>

        <form method="POST" action="{{ route('hrms.penilaian.save', $karyawan) }}" id="penilaian-form">
            @csrf
            <input type="hidden" name="tahun" value="{{ $tahun }}">

            <div class="card sec">
                <h2>{{ __('I. Absen') }} <span class="muted small" style="font-weight: 400;">{{ __('(otomatis dari data absensi)') }}</span></h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('Kategori Absen') }}</th>
                                <th class="num">{{ __('Banyaknya') }}</th>
                                <th class="num">{{ __('Poin') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (FormPenilaian::KONVERSI_ABSEN as $kategori => $konversi)
                                <tr>
                                    <td>{{ __($konversi['label']) }}</td>
                                    <td class="num">{{ $absen['rincian'][$kategori]['jumlah'] ?? 0 }}x</td>
                                    <td class="num">{{ $absen['rincian'][$kategori]['poin'] ?? 5 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2">{{ __('Total poin (maks :maks) · bobot :bobot%', ['maks' => FormPenilaian::MAKS_ABSEN, 'bobot' => $form['bobot_absen']]) }}</td>
                                <td class="num">{{ $absen['total_poin'] }} → {{ $angka($absen['nilai']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="muted small" style="margin: 8px 0 0;">{{ __('Nilai = (total poin / :maks) x :bobot.', ['maks' => FormPenilaian::MAKS_ABSEN, 'bobot' => $form['bobot_absen']]) }}</p>
            </div>

            @if ($legenda->isNotEmpty())
                <div class="card sec legenda">
                    <span class="muted small">{{ __('Warna centang penilai:') }}</span>
                    @foreach ($legenda as $levelLegenda)
                        <span><b class="centang" style="color: {{ JenjangPenilaian::warnaCentang($levelLegenda) }};">✓</b> {{ JenjangPenilaian::label($levelLegenda) }}</span>
                    @endforeach
                    <span class="muted small">{{ __('Nilai tiap butir = rata-rata skor semua penilai.') }}</span>
                </div>
            @endif

            @foreach ($form['butir'] as $kategori => $butirs)
                <div class="card sec" data-kategori="{{ $kategori }}" data-maks="{{ count($butirs) * 5 }}">
                    <h2>{{ __(FormPenilaian::KATEGORI[$kategori]) }}</h2>
                    <p class="sub">{{ __('Nilai max :maks · bobot :bobot%', ['maks' => count($butirs) * 5, 'bobot' => $form['bobot_kategori']]) }}</p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 32px;">{{ __('No') }}</th>
                                    <th>{{ __('Faktor penilaian') }}</th>
                                    @foreach (FormPenilaian::NILAI_HURUF as $huruf => $nilai)
                                        <th class="c">
                                            <span class="h-huruf">{{ $huruf }}</span>
                                            <span class="h-nilai">{{ $nilai }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($butirs as $index => $butir)
                                    @php
                                        $terpilih = $skor[$kategori][$index] ?? null;
                                        $pilihanLain = $penilaiLain
                                            ->map(fn (array $isian) => ['level' => $isian['level'], 'huruf' => $isian['skor'][$kategori][$index] ?? null])
                                            ->filter(fn (array $pilihan) => isset(FormPenilaian::NILAI_HURUF[$pilihan['huruf']]));
                                    @endphp
                                    <tr @class(['kosong' => ! $readonly && ! $terpilih]) data-lain="{{ $pilihanLain->map(fn (array $pilihan) => FormPenilaian::NILAI_HURUF[$pilihan['huruf']])->values()->toJson() }}">
                                        <td class="muted">{{ $index + 1 }}</td>
                                        <td class="butir">{{ __($butir) }}</td>
                                        @foreach (FormPenilaian::NILAI_HURUF as $huruf => $nilai)
                                            <td class="c">
                                                @unless ($readonly)
                                                    <label class="pick" title="{{ $huruf }} = {{ $nilai }}">
                                                        <input type="radio" name="skor[{{ $kategori }}][{{ $index }}]" value="{{ $huruf }}" data-nilai="{{ $nilai }}" @checked($terpilih === $huruf)>
                                                        {{ $huruf }}
                                                    </label>
                                                @endunless
                                                @foreach ($pilihanLain->where('huruf', $huruf) as $pilihan)
                                                    <b class="centang" style="color: {{ JenjangPenilaian::warnaCentang($pilihan['level']) }};" title="{{ JenjangPenilaian::label($pilihan['level']) }}">✓</b>
                                                @endforeach
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">{{ __('Total nilai') }} <span data-total>{{ $ringkas($performance['kategori'][$kategori]['total']) }}</span> / {{ count($butirs) * 5 }}</td>
                                    <td colspan="5" class="num">{{ __('Nilai') }} <span data-nilai-kategori>{{ $angka($performance['kategori'][$kategori]['nilai']) }}</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="card sec">
                <h2>{{ __('Catatan Atasan') }}</h2>
                <textarea name="catatan" rows="3" maxlength="2000" @disabled($readonly) placeholder="{{ __('Catatan untuk karyawan / HRD (opsional)') }}">{{ old('catatan', $penilaian->catatan) }}</textarea>
                <div style="margin-top: 12px; max-width: 320px;">
                    <label for="hasil">{{ __('Hasil penilaian') }} <span class="muted" style="font-weight: 400;">{{ __('(untuk karyawan kontrak)') }}</span></label>
                    <select name="hasil" id="hasil" @disabled($readonly)>
                        <option value="">-</option>
                        @foreach (PenilaianTahunan::HASIL as $option)
                            <option value="{{ $option }}" @selected(old('hasil', $penilaian->hasil) === $option)>{{ __($option) }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($canFill)
                    @php($levelPengisi = JenjangPenilaian::userLevel(auth()->user()))
                    @php($adaApprover = isset($penilaian->tahapan[$tahapKirim]))
                    @if ($levelPengisi !== ($penilaian->tahapan[0] ?? null))
                        <p class="sub" style="color: #92400e; margin-top: 16px;">{{ __('Anda mengisi sebagai') }} <b>{{ JenjangPenilaian::label($levelPengisi) }}</b> ({{ __('ambil alih tahap :tahap', ['tahap' => JenjangPenilaian::label($penilaian->tahapan[0])]) }}).</p>
                    @endif
                    <div class="actions" style="margin-top: 16px;">
                        <button type="submit" name="aksi" value="draft" class="btn btn-ghost">{{ __('Simpan draft') }}</button>
                        <button type="submit" name="aksi" value="kirim" class="btn" data-lengkap
                            data-confirm="{{ $adaApprover ? __('Kirim penilaian :nama ke :tahap untuk approval? Setelah dikirim, isian hanya bisa diubah oleh approver.', ['nama' => $karyawan->nama, 'tahap' => JenjangPenilaian::label($penilaian->tahapan[$tahapKirim])]) : __('Kirim penilaian :nama? Setelah dikirim, isian hanya bisa diubah oleh approver.', ['nama' => $karyawan->nama]) }}">
                            {{ $adaApprover ? __('Kirim untuk approval') : __('Kirim') }}
                        </button>
                    </div>
                    @include('hrms.penilaian.partials.sisa-token')
                @endif
            </div>

            @if ($canApprove)
                <div class="card sec">
                    @php($levelSaya = JenjangPenilaian::userLevel(auth()->user()))
                    <h2>{{ __('Approval') }} {{ JenjangPenilaian::label($penilaian->posisi_level) }}</h2>
                    @if ($levelSaya !== $penilaian->posisi_level)
                        <p class="sub" style="color: #92400e;">{{ __('Anda menyetujui sebagai') }} <b>{{ JenjangPenilaian::label($levelSaya) }}</b> ({{ __('override tahap :tahap', ['tahap' => JenjangPenilaian::label($penilaian->posisi_level)]) }}).</p>
                    @endif
                    <p class="sub">{{ __('Kalau tidak setuju dengan nilainya, ubah langsung di atas lalu setujui. Perubahan tercatat di riwayat.') }}</p>
                    <div class="actions" style="margin-top: 12px;">
                        <button type="submit" name="aksi" value="simpan" class="btn btn-ghost">{{ __('Simpan perubahan') }}</button>
                        <button type="submit" name="aksi" value="setuju" class="btn btn-success" data-lengkap
                            data-confirm="{{ isset($penilaian->tahapan[$penilaian->tahap + 1]) ? __('Setujui penilaian :nama dan teruskan ke :tahap?', ['nama' => $karyawan->nama, 'tahap' => JenjangPenilaian::label($penilaian->tahapan[$penilaian->tahap + 1])]) : __('Setujui penilaian :nama?', ['nama' => $karyawan->nama]) }}">{{ __('Simpan & Setujui') }}</button>
                    </div>
                    @include('hrms.penilaian.partials.sisa-token')
                </div>
            @endif
        </form>
    </div>

    <div class="side">
        <div class="card">
            <h2 style="font-size: 15px; margin-bottom: 12px;">{{ __('Nilai Akhir') }}</h2>
            <div class="score">
                <span>{{ __('Nilai absen') }}</span><b>{{ $angka($absen['nilai']) }}</b>
                <span>{{ __('Performance') }}</span><b id="nilai-performance">{{ $angka($performance['nilai']) }}</b>
                <span style="align-self: center;">{{ __('Total') }}</span>
                <span style="display: flex; align-items: center; gap: 10px;">
                    <span class="big" id="nilai-akhir">{{ $angka($absen['nilai'] + $performance['nilai']) }}</span>
                    <span class="grade" id="huruf">{{ FormPenilaian::huruf($absen['nilai'] + $performance['nilai']) }}</span>
                </span>
            </div>
            <p class="range">A 91-100 · B 80-90 · C 60-79 · D 39-59 · E 16-38</p>
        </div>

        @if ($penilaian->tahapan !== [])
            <div class="card">
                <h2 style="font-size: 15px; margin-bottom: 12px;">{{ __('Penilai') }}</h2>
                <div class="penilai" style="grid-template-columns: repeat({{ count($penilaiPerTahap) }}, 1fr);">
                    @foreach (array_reverse($penilaiPerTahap) as $tahap)
                        <div class="{{ $tahap['aktif'] ? 'aktif' : '' }}">
                            <div class="ttd">
                                @if ($tahap['log'])
                                    <span class="ok">✓ {{ $tahap['log']->aksi === PenilaianTahunanLog::AKSI_KIRIM ? __('Dinilai') : __('Disetujui') }}</span>
                                    <span>{{ $tahap['log']->user?->name ?? '-' }}</span>
                                    <span class="muted small">{{ $tahap['log']->created_at->translatedFormat('d M Y') }}</span>
                                    @if ($tahap['override'])
                                        <span class="muted small" style="font-style: italic;">{{ __('override :nama', ['nama' => $tahap['override']]) }}</span>
                                    @endif
                                @elseif ($tahap['aktif'])
                                    <span class="wait">{{ __('Menunggu') }}</span>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </div>
                            <div class="jab">{{ $tahap['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($penilaian->exists && $penilaian->logs->isNotEmpty())
            <div class="card">
                <h2 style="font-size: 15px; margin-bottom: 12px;">{{ __('Riwayat') }}</h2>
                <div class="log">
                    @foreach ($penilaian->logs->reverse() as $log)
                        <div>
                            <b>{{ __(PenilaianTahunanLog::AKSI[$log->aksi] ?? $log->aksi) }}</b> · {{ $log->user?->name ?? '-' }}
                            <span class="muted">({{ JenjangPenilaian::label($log->level) }})</span>
                            <div class="when">{{ $log->created_at->translatedFormat('d M Y H:i') }}</div>
                            @if ($log->catatan)
                                <div>“{{ $log->catatan }}”</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        // Panel kanan menempel tepat di bawah navbar (navbar juga sticky, tingginya bisa berubah).
        var nav = document.querySelector('.app-nav');
        var side = document.querySelector('.side');
        if (nav && side) {
            var aturTop = function () { side.style.setProperty('--side-top', (nav.offsetHeight + 12 + 16) + 'px'); };
            aturTop();
            if (window.ResizeObserver) new ResizeObserver(aturTop).observe(nav);
        }

        var form = document.getElementById('penilaian-form');
        if (!form) return;

        // Kirim / Setujui: semua butir wajib terisi. Baris yang kosong ditandai dan di-scroll ke yang pertama.
        form.addEventListener('submit', function (event) {
            var button = event.submitter;
            if (!button) return;

            if (button.hasAttribute('data-lengkap')) {
                var kosong = Array.prototype.filter.call(form.querySelectorAll('[data-kategori] tbody tr'), function (row) {
                    return !row.querySelector('input:checked');
                });
                if (kosong.length) {
                    event.preventDefault();
                    kosong[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    window.alert(__t('Masih ada :count butir penilaian yang belum diisi (ditandai warna oranye). Semua butir wajib diisi.', { count: kosong.length }));
                    return;
                }
            }

            if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) event.preventDefault();
        });

        var bobot = {{ $form['bobot_kategori'] }};
        var nilaiAbsen = {{ $absen['nilai'] }};
        var ranges = [['A', 91], ['B', 80], ['C', 60], ['D', 39], ['E', 0]];
        var format = function (value) { return value.toFixed(2).replace('.', ','); };

        function hitung() {
            var performance = 0;
            form.querySelectorAll('[data-kategori]').forEach(function (card) {
                var total = 0;
                // Nilai butir = rata-rata skor penilai lain (data-lain) dan pilihan user ini.
                card.querySelectorAll('tbody tr').forEach(function (row) {
                    var nilaiButir = JSON.parse(row.dataset.lain || '[]');
                    var pilihan = row.querySelector('input:checked');
                    if (pilihan) nilaiButir.push(Number(pilihan.dataset.nilai));
                    if (nilaiButir.length) total += nilaiButir.reduce(function (a, b) { return a + b; }, 0) / nilaiButir.length;
                    if (row.querySelector('input[type=radio]')) row.classList.toggle('kosong', !pilihan);
                });
                total = Math.round(total * 100) / 100;
                var nilai = Math.round(total / Number(card.dataset.maks) * bobot * 100) / 100;
                performance += nilai;
                card.querySelector('[data-total]').textContent = String(total).replace('.', ',');
                card.querySelector('[data-nilai-kategori]').textContent = format(nilai);
            });

            var akhir = Math.round((nilaiAbsen + performance) * 100) / 100;
            var bulat = Math.round(akhir);
            document.getElementById('nilai-performance').textContent = format(performance);
            document.getElementById('nilai-akhir').textContent = format(akhir);
            document.getElementById('huruf').textContent = ranges.find(function (range) { return bulat >= range[1]; })[0];
        }

        form.addEventListener('change', hitung);
    })();
</script>
@endpush
