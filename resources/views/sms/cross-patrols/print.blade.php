<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Cross Patrol</title>
    <style>
        /* Satu lembar A4 portrait berisi 9 kotak (3 x 3). Ukuran pakai mm supaya hasil cetak pas. */
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .sheet {
            width: 210mm; height: 297mm; padding: 8mm; background: #fff; overflow: hidden;
            display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(3, 1fr); gap: 4mm;
        }
        .card { border: 0.35mm solid #000; display: grid; grid-template-rows: 1fr 9mm 9mm 7mm; min-height: 0; }
        .photo { position: relative; min-height: 0; padding: 1.2mm; border-bottom: 0.25mm solid #000; }
        .photo img { display: block; width: 100%; height: 100%; object-fit: cover; }
        .photo .no-photo { display: grid; place-items: center; height: 100%; color: #888; font-size: 9pt; }
        .num {
            position: absolute; top: 2mm; left: 2mm; min-width: 8mm; height: 7.5mm; padding: 0 1.5mm;
            display: grid; place-items: center; background: #4472c4; color: #fff; font-size: 13pt; font-weight: bold;
            border: 0.3mm solid #fff;
        }
        .finding {
            display: flex; align-items: center; justify-content: center; padding: 0 1.5mm; text-align: center;
            font-size: 8.5pt; line-height: 1.15; border-bottom: 0.25mm solid #000; overflow: hidden;
        }
        .finding span { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .row { display: grid; grid-template-columns: 1fr 0.5fr 0.5fr; font-size: 7.5pt; }
        .row + .row { border-top: 0.25mm solid #000; }
        .row > div { display: flex; align-items: center; padding: 0 1.2mm; min-width: 0; }
        .row > div + div { border-left: 0.25mm solid #000; }
        .row .date { grid-column: span 2; justify-content: center; font-size: 8.5pt; }
        .row .opt { justify-content: center; }
        .row .opt.on { background: #bfbfbf; }
        .label { line-height: 1.1; }

        /* Layar (preview di modal): lembar abu-abu dengan bayangan, diperkecil kalau layar sempit. */
        @media screen {
            body { background: #525659; padding: 16px 0; }
            .sheet { margin: 0 auto 16px; box-shadow: 0 4px 16px rgba(0, 0, 0, .35); zoom: var(--zoom, 1); }
            .empty { color: #fff; text-align: center; font-size: 14px; padding: 60px 16px; }
        }
        @media print {
            .sheet { break-after: page; }
            .sheet:last-child { break-after: auto; }
        }
    </style>
</head>
<body>
    @forelse ($patrols->chunk($perPage) as $page => $chunk)
        <div class="sheet">
            @foreach ($chunk as $patrol)
                <div class="card">
                    <div class="photo">
                        <span class="num">{{ $page * $perPage + $loop->iteration }}</span>
                        @if ($patrol->foto)
                            <img src="{{ $patrol->foto_url }}" alt="">
                        @else
                            <div class="no-photo">Tidak ada foto</div>
                        @endif
                    </div>
                    <div class="finding"><span>{{ $patrol->finding }}</span></div>
                    <div class="row">
                        <div class="label">DUE DATE PERBAIKAN</div>
                        <div class="date">{{ $patrol->target->format('d.m.Y') }}</div>
                    </div>
                    <div class="row">
                        <div class="label">STATUS</div>
                        <div @class(['opt', 'on' => ! $patrol->isClosed()])>OPEN</div>
                        <div @class(['opt', 'on' => $patrol->isClosed()])>CLOSE</div>
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="empty">Tidak ada temuan untuk dicetak.</div>
    @endforelse

    <script>
        // Preview: perkecil lembar A4 (± 794 px) supaya muat di lebar modal.
        function fit() {
            document.documentElement.style.setProperty('--zoom', Math.min(1, (window.innerWidth - 24) / 794));
        }
        fit();
        window.addEventListener('resize', fit);
    </script>
</body>
</html>
