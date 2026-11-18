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
    .legenda { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 16px; font-size: 13px; margin: 0 0 10px; }

    /* I. Absen: tampilan seperti form cetak. */
    .absen-ringkas, .absen-matriks { border-collapse: collapse; }
    .absen-ringkas { width: auto; min-width: 420px; }
    .absen-ringkas th, .absen-ringkas td, .absen-matriks th, .absen-matriks td { border: 1px solid var(--line-strong); padding: 6px 8px; text-align: center; font-size: 13px; }
    /* Layout menghapus garis bawah baris terakhir tabel (tbody tr:last-child td); tabel absen tetap berbingkai penuh. */
    .absen-ringkas tbody tr:last-child td, .absen-matriks tbody tr:last-child td { border-bottom: 1px solid var(--line-strong); }
    .absen-ringkas th, .absen-matriks th { background: var(--soft-2); color: var(--text); font-weight: 600; text-transform: none; letter-spacing: 0; white-space: nowrap; }
    .absen-ringkas td { font-weight: 700; font-size: 15px; }
    .absen-matriks { width: 100%; }
    .absen-matriks th.kat, .absen-matriks td.kat { text-align: left; white-space: nowrap; }
    .absen-matriks td.on span { display: inline-flex; align-items: center; justify-content: center; min-width: 26px; height: 26px; padding: 0 4px; border: 2px solid var(--primary); border-radius: 999px; color: var(--primary); font-weight: 700; }
    .absen-matriks .total-kolom { background: var(--soft-2); font-weight: 700; }
    .absen-matriks tr.baris-total td { font-weight: 700; }
    .absen-rumus { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 10px; }
    .absen-nilai { padding: 6px 14px; border: 2px solid var(--primary); border-radius: 10px; font-size: 18px; font-weight: 700; }

    /* Stepper: bulatan bernomor + garis progres. */
    .stepper { position: relative; margin-top: 16px; padding: 18px 12px 12px; }
    .stepper ol { position: relative; display: grid; grid-template-columns: repeat(var(--jumlah), minmax(0, 1fr)); margin: 0; padding: 0; list-style: none; }
    .stepper li { display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; min-width: 0; }
    .stepper-track { position: absolute; top: 35px; left: calc(12px + 100% / var(--jumlah) / 2 - 12px / var(--jumlah)); right: calc(12px + 100% / var(--jumlah) / 2 - 12px / var(--jumlah)); height: 4px; border-radius: 999px; background: var(--line-strong); }
    .stepper-fill { display: block; width: var(--progress, 0%); height: 100%; border-radius: inherit; background: var(--primary); transition: width .25s ease; }
    .stepper-dot { position: relative; z-index: 1; width: 36px; height: 36px; border-radius: 50%; border: 2px solid var(--line-strong); background: #fff; color: var(--muted); font: 700 14px/1 inherit; cursor: pointer; transition: background .2s, border-color .2s, color .2s; }
    .stepper-dot:hover { border-color: var(--primary); color: var(--primary); }
    .stepper-dot.done { background: var(--soft); border-color: var(--primary); color: var(--primary); }
    .stepper-dot.active { background: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 0 0 4px var(--soft); }
    .stepper-dot.kurang { border-color: #f59e0b; }
    .stepper-label { font-size: 12px; color: var(--muted); line-height: 1.2; overflow-wrap: anywhere; }
    .stepper li:has(.active) .stepper-label { color: var(--text); font-weight: 600; }
    .stepper-sub { display: block; font-size: 10.5px; font-weight: 400; color: var(--muted); }
    @media (max-width: 640px) { .stepper-label { display: none; } .stepper-dot { width: 30px; height: 30px; font-size: 13px; } .stepper-track { top: 32px; } }
    .step-nav { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-top: 16px; }

    /* Modal preview form cetak. */
    dialog.preview-modal { width: min(920px, calc(100% - 32px)); max-height: calc(100vh - 32px); }
    .preview-frame { position: relative; height: min(70vh, 900px); border: 1px solid var(--line-strong); border-radius: 12px; overflow: hidden; background: #e5e7eb; }
    .preview-frame iframe { width: 100%; height: 100%; border: 0; }
    .preview-loading { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 14px; background: #e5e7eb; }
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
    // Langkah I. Absen ditampilkan seperti form cetak.
    $kolomAbsen = ['0', '1 x', '2 x', '3 x', '4 x', '5 x', '6 x', '7 ~ 10', '11~15', '≥ 16'];
    $singkatanAbsen = ['A' => 'A', 'I' => 'I', 'S' => 'S', 'DT' => 'DT', 'DT30' => 'DT > 30 mnt', 'PC' => 'PC', 'PC30' => 'PC > 30 mnt', 'SD' => 'SD'];
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

        {{-- Langkah pengisian: 1 Absen, 2-6 poin II-VI, terakhir Catatan & kirim. Satu langkah tampil sekaligus. --}}
        @php
            $langkah = [__('Absen')];
            foreach (array_keys($form['butir']) as $kategoriLangkah) {
                $langkah[] = Str::after(__(FormPenilaian::KATEGORI[$kategoriLangkah]), '. ');
            }
            $langkah[] = $readonly ? __('Catatan') : __('Catatan & Kirim');
            // Mulai di langkah yang bermasalah setelah gagal validasi: butir kosong → langkah butir pertama yang kosong (dicari JS).
            $langkahAwal = $errors->has('skor') ? 'kosong' : ($errors->any() ? count($langkah) : 1);
        @endphp
        <nav class="card stepper" aria-label="{{ __('Langkah penilaian') }}" style="--jumlah: {{ count($langkah) }};">
            <div class="stepper-track"><span class="stepper-fill"></span></div>
            <ol>
                @foreach ($langkah as $nomor => $judulLangkah)
                    <li>
                        <button type="button" class="stepper-dot" data-step-dot="{{ $nomor + 1 }}" aria-label="{{ __('Langkah :nomor: :judul', ['nomor' => $nomor + 1, 'judul' => $judulLangkah]) }}">{{ $nomor + 1 }}</button>
                        <span class="stepper-label">
                            {{ $judulLangkah }}
                            {{-- Absen diisi otomatis dari data absensi, penilai tidak perlu mengisi. --}}
                            @if ($nomor === 0)
                                <small class="stepper-sub">{{ __('(Auto by system)') }}</small>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        </nav>

        <form method="POST" action="{{ route('hrms.penilaian.save', $karyawan) }}" id="penilaian-form"
              data-preview-url="{{ route('hrms.penilaian.preview', $karyawan) }}" data-step-awal="{{ $langkahAwal }}">
            @csrf
            <input type="hidden" name="tahun" value="{{ $tahun }}">

            <div class="step" data-step="1">
            <div class="card sec">
                <h2>{{ __('I. Absen') }} <span class="muted small" style="font-weight: 400;">{{ __('(otomatis dari data absensi)') }}</span></h2>
                {{-- Tampilan seperti form cetak: ringkasan jumlah absen + matriks konversi, poin yang dipakai dilingkari. --}}
                <div class="table-wrap">
                    <table class="absen-ringkas">
                        <tr>
                            @foreach ($singkatanAbsen as $singkatan)
                                <th>{{ $singkatan }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach (array_keys($singkatanAbsen) as $kategori)
                                <td>{{ $absen['rincian'][$kategori]['jumlah'] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    </table>
                </div>
                <div class="table-wrap" style="margin-top: 12px;">
                    <table class="absen-matriks">
                        <tr>
                            <th rowspan="2" class="kat">{{ __('Kategori Absen') }}</th>
                            <th colspan="{{ count($kolomAbsen) }}">{{ __('Banyaknya Absen') }}</th>
                            <th rowspan="2" class="total-kolom">{{ __('Poin') }}</th>
                        </tr>
                        <tr>
                            @foreach ($kolomAbsen as $kolom)
                                <th>{{ $kolom }}</th>
                            @endforeach
                        </tr>
                        @foreach (FormPenilaian::KONVERSI_ABSEN as $kategori => $konversi)
                            <tr>
                                <td class="kat">{{ __($konversi['label']) }}</td>
                                @foreach ($konversi['poin'] as $index => $poin)
                                    <td @class(['on' => $index === FormPenilaian::kolomAbsen($absen['rincian'][$kategori]['jumlah'] ?? 0)])><span>{{ $poin }}</span></td>
                                @endforeach
                                <td class="total-kolom">{{ $absen['rincian'][$kategori]['poin'] ?? 5 }}</td>
                            </tr>
                        @endforeach
                        <tr class="baris-total">
                            <td class="kat">{{ __('TOTAL') }}</td>
                            <td colspan="{{ count($kolomAbsen) }}"></td>
                            <td class="total-kolom">{{ $absen['total_poin'] }}</td>
                        </tr>
                    </table>
                </div>
                <div class="absen-rumus">
                    <span class="muted small">
                        {{ __('Nilai Maksimum = :maks (a)', ['maks' => FormPenilaian::MAKS_ABSEN]) }} ·
                        {{ __('Bobot Absen = :bobot% (b)', ['bobot' => $form['bobot_absen']]) }} ·
                        {{ __('Nilai akhir = (Total nilai / a ) x ( b% x 100)') }}
                    </span>
                    <span class="absen-nilai">{{ $angka($absen['nilai']) }}</span>
                </div>
            </div>
            @include('hrms.penilaian.partials.step-nav', ['nomor' => 1, 'jumlah' => count($langkah)])
            </div>

            @foreach ($form['butir'] as $kategori => $butirs)
                <div class="step" data-step="{{ $loop->iteration + 1 }}" hidden>
                <div class="card sec" data-kategori="{{ $kategori }}" data-maks="{{ count($butirs) * 5 }}">
                    <h2>{{ __(FormPenilaian::KATEGORI[$kategori]) }}</h2>
                    <p class="sub">{{ __('Nilai max :maks · bobot :bobot%', ['maks' => count($butirs) * 5, 'bobot' => $form['bobot_kategori']]) }}</p>
                    @if ($legenda->isNotEmpty())
                        <div class="legenda">
                            <span class="muted small">{{ __('Warna centang penilai:') }}</span>
                            @foreach ($legenda as $levelLegenda)
                                <span><b class="centang" style="color: {{ JenjangPenilaian::warnaCentang($levelLegenda) }};">✓</b> {{ JenjangPenilaian::label($levelLegenda) }}</span>
                            @endforeach
                            <span class="muted small">{{ __('Nilai tiap butir = rata-rata skor semua penilai.') }}</span>
                        </div>
                    @endif
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
                                        $nilaiLain = $pilihanLain->map(fn (array $pilihan) => FormPenilaian::NILAI_HURUF[$pilihan['huruf']])->values()->toJson();
                                        // Satu kotak satu centang: kalau beberapa penilai memilih huruf yang sama, pakai penilai jenjang tertinggi (terakhir).
                                        $pilihanLain = $pilihanLain->keyBy('huruf');
                                    @endphp
                                    <tr @class(['kosong' => ! $readonly && ! $terpilih]) data-lain="{{ $nilaiLain }}">
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
                @include('hrms.penilaian.partials.step-nav', ['nomor' => $loop->iteration + 1, 'jumlah' => count($langkah)])
                </div>
            @endforeach

            <div class="step" data-step="{{ count($langkah) }}" hidden>
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
                            {{ __('Submit Penilaian') }}
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
            @include('hrms.penilaian.partials.step-nav', ['nomor' => count($langkah), 'jumlah' => count($langkah)])
            </div>
        </form>

        {{-- Preview form cetak sebelum Kirim / Setujui: isian belum disimpan sampai dikonfirmasi di sini. --}}
        @unless ($readonly)
            <dialog class="modal preview-modal" id="preview-modal" aria-labelledby="preview-title">
                <div class="modal-head">
                    <h2 id="preview-title">{{ __('Preview Form Penilaian') }}</h2>
                    <button type="button" class="modal-close" data-preview-tutup aria-label="{{ __('Tutup') }}">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="muted small" data-preview-pesan style="margin: 0 0 10px;"></p>
                    <div class="preview-frame">
                        <div class="preview-loading" data-preview-loading>{{ __('Memuat preview...') }}</div>
                        <iframe title="{{ __('Preview Form Penilaian') }}" data-preview-iframe></iframe>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-ghost" data-preview-tutup>{{ __('Kembali ke isian') }}</button>
                    <button type="button" class="btn" data-preview-konfirmasi>{{ __('Submit Penilaian') }}</button>
                </div>
            </dialog>
        @endunless
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

        // ===== Stepper: satu langkah tampil, bulatan bernomor + garis progres. =====
        var steps = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
        var dots = Array.prototype.slice.call(document.querySelectorAll('[data-step-dot]'));
        var stepper = document.querySelector('.stepper');
        var aktif = 1;

        function butirKosong(step) {
            return Array.prototype.filter.call(step.querySelectorAll('[data-kategori] tbody tr'), function (row) {
                return row.querySelector('input[type=radio]') && !row.querySelector('input:checked');
            });
        }

        function perbaruiDots() {
            dots.forEach(function (dot, index) {
                var nomor = index + 1;
                var step = steps[index];
                var kurang = step && butirKosong(step).length > 0;
                dot.classList.toggle('active', nomor === aktif);
                dot.classList.toggle('done', nomor !== aktif && (nomor < aktif || (step.querySelector('[data-kategori]') && !kurang)));
                dot.classList.toggle('kurang', nomor !== aktif && nomor < aktif && kurang);
                dot.setAttribute('aria-current', nomor === aktif ? 'step' : 'false');
            });
            stepper.style.setProperty('--progress', (steps.length > 1 ? (aktif - 1) / (steps.length - 1) * 100 : 100) + '%');
        }

        function tampil(nomor, gulir) {
            aktif = Math.min(Math.max(nomor, 1), steps.length);
            steps.forEach(function (step) { step.hidden = Number(step.dataset.step) !== aktif; });
            perbaruiDots();
            if (gulir !== false && stepper.getBoundingClientRect().top < 0) {
                stepper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        dots.forEach(function (dot) {
            dot.addEventListener('click', function () { tampil(Number(dot.dataset.stepDot)); });
        });
        form.addEventListener('click', function (event) {
            var go = event.target.closest('[data-step-go]');
            if (go) tampil(aktif + Number(go.dataset.stepGo));
        });

        // Langkah awal: setelah gagal validasi butir kosong, langsung ke langkah butir kosong pertama.
        var awal = form.dataset.stepAwal;
        var stepKosong = steps.find(function (step) { return butirKosong(step).length > 0; });
        tampil(awal === 'kosong' && stepKosong ? Number(stepKosong.dataset.step) : Number(awal) || 1, false);

        // ===== Kirim / Setujui: cek semua butir terisi, lalu tampilkan preview form cetak sebelum benar-benar dikirim. =====
        var modal = document.getElementById('preview-modal');
        var iframe = modal && modal.querySelector('[data-preview-iframe]');
        var loading = modal && modal.querySelector('[data-preview-loading]');
        var tombolKonfirmasi = modal && modal.querySelector('[data-preview-konfirmasi]');
        var tombolAsal = null;

        function bukaPreview(button) {
            tombolAsal = button;
            modal.querySelector('[data-preview-pesan]').textContent = button.dataset.confirm || '';
            tombolKonfirmasi.className = button.className;
            tombolKonfirmasi.disabled = true;
            loading.textContent = __t('Memuat preview...');
            loading.hidden = false;
            iframe.removeAttribute('srcdoc');
            modal.showModal();

            var data = new FormData(form);
            data.set('aksi', button.value);
            fetch(form.dataset.previewUrl, { method: 'POST', body: data, headers: { 'Accept': 'text/html' } })
                .then(function (response) {
                    if (!response.ok) throw new Error(response.status);
                    return response.text();
                })
                .then(function (html) {
                    iframe.srcdoc = html;
                    loading.hidden = true;
                    tombolKonfirmasi.disabled = false;
                })
                .catch(function () {
                    loading.textContent = __t('Preview gagal dimuat. Tutup lalu coba lagi.');
                });
        }

        if (modal) {
            modal.querySelectorAll('[data-preview-tutup]').forEach(function (tombol) {
                tombol.addEventListener('click', function () { modal.close(); });
            });
            tombolKonfirmasi.addEventListener('click', function () {
                if (!tombolAsal) return;
                form.dataset.terkonfirmasi = '1';
                modal.close();
                form.requestSubmit(tombolAsal);
            });
        }

        form.addEventListener('submit', function (event) {
            var button = event.submitter;
            if (!button) return;

            if (form.dataset.terkonfirmasi === '1') {
                delete form.dataset.terkonfirmasi;
                return;
            }

            if (button.hasAttribute('data-lengkap')) {
                var kosong = butirKosong(form);
                if (kosong.length) {
                    event.preventDefault();
                    tampil(Number(kosong[0].closest('[data-step]').dataset.step), false);
                    kosong[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    window.alert(__t('Masih ada :count butir penilaian yang belum diisi (ditandai warna oranye). Semua butir wajib diisi.', { count: kosong.length }));
                    return;
                }

                if (modal) {
                    event.preventDefault();
                    bukaPreview(button);
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

        form.addEventListener('change', function () {
            hitung();
            perbaruiDots();
        });
    })();
</script>
@endpush
