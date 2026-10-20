@extends('layouts.app')

@section('title', 'Komparasi Mesin Absen')

@section('styles')
    .kmp-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .kmp-head input[type=date] { width: auto; border-radius: 999px; padding: 9px 14px; }

    .upload { display: flex; align-items: center; justify-content: space-between; gap: 12px 20px; flex-wrap: wrap; padding: 14px 20px; margin-bottom: 20px; }
    .upload form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .upload input[type=file] { width: auto; font-size: 13px; }

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
    <span class="muted">Upload file export mesin absen (AC-No, Name, Date). Tanggal dibaca dari kolom Date di file.</span>
    <form method="POST" action="{{ route('hrms.absensi.komparasi.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file" accept=".xls,.xlsx" required aria-label="File export mesin absen">
        <button type="submit" class="btn btn-sm">Upload</button>
    </form>
    @error('file') <div class="field-error" style="flex-basis: 100%;">{{ $message }}</div> @enderror
</div>

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
