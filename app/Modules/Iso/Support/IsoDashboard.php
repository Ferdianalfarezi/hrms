<?php

namespace App\Modules\Iso\Support;

use App\Models\Application;
use App\Models\User;
use App\Modules\Iso\Models\IsoDocument;
use App\Support\ModuleDashboard;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * Dashboard ISO berbasis tabel iso_documents, bentuk datanya mengikuti view dashboard/module.blade.php.
 * Semua angka mengikuti hak akses: Admin Departemen hanya menghitung dokumen departemennya + dokumen umum.
 */
class IsoDashboard
{
    /** Warna segmen jenis file di kartu komposisi (kelas tone-* di template dashboard). */
    private const JENIS_TONE = [
        'pdf' => 'danger',
        'word' => 'primary',
        'excel' => 'success',
        'powerpoint' => 'navy',
        'gambar' => 'primary-soft',
        'lainnya' => 'soft',
    ];

    public static function build(User $viewer): array
    {
        $application = Application::where('code', 'iso')->firstOrFail();
        $visible = fn () => IsoDocument::query()->visibleTo($viewer);
        $managedDepartments = $viewer->managedDepartmentList('iso');

        $total = $visible()->count();
        $perKategori = $visible()->selectRaw('kategori, count(*) as total')->groupBy('kategori')->pluck('total', 'kategori');
        $umum = $visible()->whereNull('department_id')->count();

        // Upload per bulan, 7 bulan terakhir (termasuk bulan ini), dihitung di PHP supaya tidak bergantung fungsi tanggal DB.
        $since = Carbon::now()->startOfMonth()->subMonths(6);
        $uploadDates = $visible()->where('created_at', '>=', $since)->pluck('created_at');
        $bars = collect(range(0, 6))->map(function (int $offset) use ($since, $uploadDates) {
            $month = $since->copy()->addMonths($offset);

            return [
                'label' => $month->translatedFormat('M'),
                'count' => $uploadDates->filter(fn (Carbon $date) => $date->isSameMonth($month))->count(),
                'highlight' => $month->isSameMonth(now()),
            ];
        });

        $weekStart = Carbon::now()->startOfWeek();
        $weekUploads = $visible()->with('creator')
            ->whereBetween('created_at', [$weekStart, $weekStart->copy()->endOfWeek()])
            ->get();

        return [
            'application' => $application,
            'subtitle' => match (true) {
                $managedDepartments->isNotEmpty() => 'Departemen '.$managedDepartments->pluck('name')->implode(', ').' + dokumen umum',
                // Guest (dan Admin Departemen tanpa departemen) hanya melihat dokumen umum.
                ! $viewer->isModuleAdmin('iso') => 'Dokumen umum',
                default => $application->description,
            },
            'myApplications' => $viewer->accessibleApplications(),
            'bigStats' => [
                ['label' => 'Dokumen', 'value' => $total, 'icon' => 'file'],
                ['label' => 'Upload bulan ini', 'value' => $bars->last()['count'], 'icon' => 'calendar'],
                ['label' => 'Total ukuran', 'value' => Number::fileSize((int) $visible()->sum('size'), precision: 1), 'icon' => 'database'],
            ],
            'pills' => collect(IsoDocument::KATEGORI)
                ->map(fn (string $label, string $kategori) => ['label' => $label, 'count' => (int) ($perKategori[$kategori] ?? 0)])
                ->filter(fn (array $item) => $item['count'] > 0)
                ->map(fn (array $item) => [...$item, 'percent' => ModuleDashboard::percent($item['count'], $total)])
                ->values()->all(),
            'pillOutlines' => $total > 0 ? [
                ['label' => 'Umum', 'count' => $umum],
                ['label' => 'Per departemen', 'count' => $total - $umum],
            ] : [],
            'growth' => [
                'title' => 'Upload Dokumen',
                'total' => $bars->sum('count'),
                'caption' => "dokumen diupload\n7 bulan terakhir",
                'bubble' => 'bulan ini',
                'bars' => $bars->all(),
                'link' => route('iso.procedures.index'),
            ],
            'composition' => self::jenisFile($visible, $total),
            'people' => [
                'title' => 'Upload Terbaru',
                'counter' => min(5, $total).'/'.$total,
                'items' => $visible()->with(['creator', 'department'])->latest()->orderByDesc('id')->take(5)->get()
                    ->map(fn (IsoDocument $document) => [
                        'name' => $document->nama,
                        'sub' => collect([
                            IsoDocument::KATEGORI[$document->kategori] ?? $document->kategori,
                            $document->department?->name ?? 'Umum',
                            $document->created_at->translatedFormat('d M Y'),
                            $document->creator ? 'oleh '.$document->creator->first_name : null,
                        ])->filter()->join(' · '),
                        'photo' => $document->creator?->photo_url,
                        'url' => route("iso.{$document->kategori}.file", $document),
                    ])->all(),
                'empty' => 'Belum ada dokumen yang diupload.',
            ],
            'calendar' => ModuleDashboard::week(
                $weekUploads->map(fn (IsoDocument $document) => [
                    'date' => $document->created_at,
                    'name' => $document->nama,
                    'title' => $document->nama.($document->creator ? " · oleh {$document->creator->name}" : ''),
                    'photo' => $document->creator?->photo_url,
                ]),
                caption: 'Dokumen yang diupload minggu ini.',
                empty: 'Belum ada dokumen yang diupload minggu ini.',
            ),
        ];
    }

    /**
     * Komposisi jenis file (PDF, Word, Excel, ...), dikelompokkan dari ekstensi.
     *
     * @param  Closure(): Builder<IsoDocument>  $visible
     */
    private static function jenisFile(Closure $visible, int $total): array
    {
        $perJenis = $visible()->selectRaw('extension, count(*) as total')->groupBy('extension')->pluck('total', 'extension')
            ->reduce(function (array $carry, int $count, ?string $extension) {
                $jenis = IsoDocument::jenisOf($extension ?: null);
                $carry[$jenis] = ($carry[$jenis] ?? 0) + $count;

                return $carry;
            }, []);

        $labels = array_map(fn (array $info) => $info['label'], IsoDocument::JENIS) + ['lainnya' => 'Lainnya'];

        return [
            'title' => 'Jenis File',
            'value' => $total,
            'unit' => 'file',
            'segments' => collect($labels)->map(fn (string $label, string $jenis) => [
                'label' => $label,
                'count' => $perJenis[$jenis] ?? 0,
                'tone' => self::JENIS_TONE[$jenis],
            ])->values()->all(),
            'note' => $total === 0 ? 'Belum ada dokumen.' : null,
        ];
    }
}
