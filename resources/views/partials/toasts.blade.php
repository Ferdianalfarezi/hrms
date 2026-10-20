{{-- Isi toast notifikasi; dipakai layout dan endpoint polling (notifications.toasts). --}}
@foreach ($toasts as $toast)
    @php($karyawans = $toast->data['karyawans'] ?? [])
    @php($tanggal = isset($toast->data['tanggal']) ? \Illuminate\Support\Carbon::parse($toast->data['tanggal']) : null)
    <div class="toast" role="alert" data-toast="{{ $toast->id }}" data-read-url="{{ route('notifications.read', $toast->id) }}">
        <div class="toast-head">
            <div class="toast-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            </div>
            <div style="min-width: 0;">
                <div class="toast-title">{{ $karyawans ? 'Absensi belum lengkap' : ($toast->data['title'] ?? 'Notifikasi') }}</div>
                <div class="toast-sub">{{ $tanggal?->translatedFormat('l, d M Y') ?? ($toast->data['subtitle'] ?? '') }}</div>
            </div>
            <button type="button" class="toast-close" data-toast-close aria-label="Tutup notifikasi">&times;</button>
        </div>
        @if ($karyawans)
            <div class="toast-metric"><b>{{ count($karyawans) }}</b><span>karyawan tidak absen di mesin<br>dan belum ada keterangan</span></div>
            <div class="toast-chips">
                @foreach (array_slice($karyawans, 0, 3) as $karyawan)
                    <span class="toast-chip" title="{{ $karyawan['nik'] }} - {{ $karyawan['nama'] }}">{{ $karyawan['nama'] }}</span>
                @endforeach
                @if (count($karyawans) > 3)
                    <span class="toast-chip more" title="{{ collect(array_slice($karyawans, 3))->map(fn ($k) => $k['nik'].' - '.$k['nama'])->join("\n") }}">+{{ count($karyawans) - 3 }}</span>
                @endif
            </div>
        @endif
        @if ($toast->data['url'] ?? null)
            <div class="toast-foot"><a href="{{ $toast->data['url'] }}" class="toast-btn">Isi keterangan <span aria-hidden="true">&rarr;</span></a></div>
        @endif
    </div>
@endforeach
