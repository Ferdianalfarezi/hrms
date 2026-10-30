@extends('layouts.app')

@section('title', 'Komparasi Mesin Absen')

@section('styles')
    .kmp-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .kmp-head input[type=date] { width: auto; border-radius: 999px; padding: 9px 14px; }

    .upload { display: flex; align-items: center; gap: 12px 16px; flex-wrap: wrap; padding: 12px 16px; margin-bottom: 20px; }
    .up-info { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 260px; }
    .up-ico { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; flex: none; background: var(--navy); color: #fff; }
    .up-ico svg { width: 17px; height: 17px; }
    .up-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-weight: 600; font-size: 14px; }
    .up-desc { font-size: 12px; color: var(--muted); margin-top: 1px; }
    .up-state { display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #fff8e6; color: #92600a; }
    .up-state.done { background: #e8f7ee; color: #1a7f45; }
    .up-state::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

    .upload form { display: flex; align-items: center; gap: 8px; }
    .dz, .upload .btn { box-sizing: border-box; height: 34px; font-size: 13px; }
    .dz { position: relative; display: inline-flex; align-items: center; gap: 8px; max-width: 280px; padding: 0 14px; border: 1px dashed var(--line-strong);
        border-radius: 999px; background: var(--soft-2); cursor: pointer; transition: .15s; margin: 0; font-weight: 400; }
    .upload .btn { padding: 0 18px; }
    .dz input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .dz svg { width: 15px; height: 15px; flex: none; color: var(--primary); }
    .dz-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--muted); }
    .dz:hover, .dz.drag { border-color: var(--primary); background: #e8f0ff; }
    .dz.has-file { border-style: solid; border-color: var(--primary); background: #eef4ff; }
    .dz.has-file .dz-label { color: var(--text); font-weight: 600; }
    .upload .btn:disabled { opacity: .5; cursor: not-allowed; }
    .upload .field-error { flex-basis: 100%; margin: 0; }

    .kmp-section { padding: 0; overflow: hidden; }
    .kmp-title { padding: 16px 20px 12px; }
    .kmp-title h2 { font-size: 16px; }
    table.kmp { width: 100%; border-collapse: collapse; font-size: 13px; background: #fff; }
    table.kmp th, table.kmp td { border: 1px solid var(--line-strong); padding: 6px 8px; text-align: left; white-space: nowrap; }
    table.kmp thead th { background: #f5f8fe; color: var(--text); font-size: 11px; letter-spacing: .03em; }
    table.kmp td.c { text-align: center; }
    table.kmp td.nama { font-weight: 600; }
    table.kmp tbody tr:hover td { background: #f7faff; }
    table.kmp .empty { color: var(--muted); padding: 20px; text-align: center; }
@endsection

@section('content')
<div class="kmp-head">
    <div>
        <h1 style="font-size: clamp(24px, 3.5vw, 36px); font-weight: 500; letter-spacing: -.03em;">Komparasi Mesin Absen</h1>
        <p class="muted" style="margin: 2px 0 0;">Hari kerja {{ $tanggal->translatedFormat('l, d F Y') }} · shift malam {{ $tanggal->copy()->subDay()->translatedFormat('d M') }} & shift pagi {{ $tanggal->translatedFormat('d M') }}</p>
    </div>
    <form method="GET">
        <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.requestSubmit()" aria-label="Tanggal hari kerja">
    </form>
</div>

<div class="card upload">
    <div class="up-info">
        <span class="up-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg></span>
        <div>
            <div class="up-title">
                Upload Attendance Report
                <span @class(['up-state', 'done' => $uploaded])>{{ $uploaded ? 'Sudah diupload' : 'Belum diupload' }}</span>
            </div>
            <div class="up-desc">Berisi 2 tanggal: kemarin (shift malam, Time In mulai 12:00) dan hari ini. Laporan untuk tanggal terakhir di file.</div>
        </div>
    </div>

    <form method="POST" action="{{ route('hrms.absensi.komparasi.store') }}" enctype="multipart/form-data" id="kmp-upload">
        @csrf
        <label class="dz" id="kmp-dz" title="Klik atau tarik file .xlsx / .xls ke sini (maks. 10 MB)">
            <input type="file" name="file" accept=".xls,.xlsx" required aria-label="File Attendance Report" id="kmp-file">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
            <span class="dz-label" id="kmp-dz-label">Pilih / tarik file .xlsx</span>
        </label>
        <button type="submit" class="btn" id="kmp-submit" disabled>Upload</button>
    </form>
    @error('file') <div class="field-error">{{ $message }}</div> @enderror
</div>

<script>
    (function () {
        var input = document.getElementById('kmp-file');
        var zone = document.getElementById('kmp-dz');
        var label = document.getElementById('kmp-dz-label');
        var submit = document.getElementById('kmp-submit');
        if (!input) return;

        function render() {
            var file = input.files[0];
            zone.classList.toggle('has-file', !!file);
            submit.disabled = !file;
            label.textContent = file ? file.name : 'Pilih / tarik file .xlsx';
        }

        input.addEventListener('change', render);
        ['dragenter', 'dragover'].forEach(function (type) {
            zone.addEventListener(type, function () { zone.classList.add('drag'); });
        });
        ['dragleave', 'drop'].forEach(function (type) {
            zone.addEventListener(type, function () { zone.classList.remove('drag'); });
        });
        document.getElementById('kmp-upload').addEventListener('submit', function () {
            submit.disabled = true;
            submit.textContent = 'Mengupload...';
        });
        render();
    })();
</script>

<section class="card kmp-section">
    <div class="kmp-title">
        <h2>Belum ada keterangan ({{ $belum->count() }})</h2>
        <span class="muted small">Tidak absen di mesin dan Admin Departemen belum mengisi kode absensi.</span>
    </div>
    <div class="table-wrap">
        <table class="kmp">
            <thead><tr><th>No</th><th>NIK</th><th>Nama</th><th>Departemen</th><th>Group</th><th>Tanggal laporan</th></tr></thead>
            <tbody>
                @forelse ($belum as $row)
                    <tr>
                        <td class="c">{{ $loop->iteration }}</td>
                        <td>{{ $row['karyawan']->nik }}</td>
                        <td class="nama">{{ $row['karyawan']->nama }}</td>
                        <td>{{ $row['karyawan']->department?->name ?? '-' }}</td>
                        <td class="c">{{ $row['karyawan']->group ?? '-' }}</td>
                        <td>{{ $row['tanggalLaporan']->translatedFormat('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ $uploaded ? 'Semua karyawan yang tidak absen sudah diberi keterangan.' : 'Belum ada data mesin untuk tanggal ini.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
