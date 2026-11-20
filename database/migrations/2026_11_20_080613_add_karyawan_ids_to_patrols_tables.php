<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom nama (teks) => kolom ID karyawan (JSON) per tabel patrol.
     *
     * @var array<string, array<string, string>>
     */
    private const COLUMNS = [
        'daily_patrols' => ['pic' => 'pic_ids', 'pic_patrol' => 'pic_patrol_ids'],
        'cross_patrols' => ['pic' => 'pic_ids', 'patroler' => 'patroler_ids'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PIC / PIC Patrol / Patroler sekarang disimpan sebagai ID karyawan (kolom teks tetap jadi label nama).
        // Nama karyawan bisa sama atau berubah, jadi yang dipakai untuk menghubungkan ke karyawan adalah ID-nya.
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach ($columns as $nameColumn => $idsColumn) {
                    $table->json($idsColumn)->nullable()->after($nameColumn);
                }
            });
        }

        $this->backfillIdsFromNames();
    }

    /**
     * Data lama: nama yang cocok dengan tepat satu karyawan dipetakan ke ID-nya.
     * Nama yang tidak ditemukan atau dobel (ambigu) tetap hanya tersimpan sebagai teks.
     */
    private function backfillIdsFromNames(): void
    {
        $idsByName = DB::table('karyawans')->get(['id', 'nama'])
            ->groupBy(fn (object $karyawan) => trim($karyawan->nama))
            ->filter(fn ($group) => $group->count() === 1)
            ->map(fn ($group) => (int) $group->first()->id);

        foreach (self::COLUMNS as $tableName => $columns) {
            DB::table($tableName)->orderBy('id')->select(['id', ...array_keys($columns)])
                ->each(function (object $row) use ($tableName, $columns, $idsByName) {
                    $update = [];

                    foreach ($columns as $nameColumn => $idsColumn) {
                        $names = array_filter(array_map('trim', explode(',', (string) $row->{$nameColumn})), 'strlen');
                        $ids = collect($names)->map(fn (string $name) => $idsByName[$name] ?? null)->filter()->unique()->values()->all();
                        $update[$idsColumn] = json_encode($ids);
                    }

                    DB::table($tableName)->where('id', $row->id)->update($update);
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropColumn(array_values($columns));
            });
        }
    }
};
