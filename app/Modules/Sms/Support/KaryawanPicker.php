<?php

namespace App\Modules\Sms\Support;

use App\Modules\Hrms\Models\Karyawan;
use App\Modules\Sms\Models\CrossPatrol;
use App\Modules\Sms\Models\DailyPatrol;
use Illuminate\Database\Eloquent\Model;

/**
 * Isian PIC / PIC Patrol / Patroler: dipilih dari data karyawan dan disimpan sebagai ID karyawan ({field}_ids),
 * kolom {field} berisi label nama dipisah koma. Dari form nilainya dikirim sebagai JSON [{id, nama}];
 * id null = nama lama yang dulu diketik manual (hanya boleh dipertahankan, tidak bisa ditambah).
 */
class KaryawanPicker
{
    /** Jumlah maksimal hasil pencarian karyawan per ketikan. */
    public const SEARCH_LIMIT = 20;

    /**
     * Nama karyawan saat ini untuk semua ID di field-field temuan ini (satu query untuk satu halaman daftar).
     *
     * @param  iterable<Model>  $patrols
     * @param  list<string>  $fields
     * @return array<int, string> id => nama
     */
    public static function namesFor(iterable $patrols, array $fields): array
    {
        $ids = [];
        foreach ($patrols as $patrol) {
            foreach ($fields as $field) {
                array_push($ids, ...($patrol->{$field.'_ids'} ?? []));
            }
        }

        return $ids === [] ? [] : Karyawan::whereIn('id', array_unique($ids))->pluck('nama', 'id')->all();
    }

    /**
     * Isi awal pilihan saat form edit dibuka: karyawan terpilih (nama terbaru) + nama lama yang belum terhubung.
     *
     * @param  array<int, string>  $names  hasil namesFor()
     * @return list<array{id: ?int, nama: string}>
     */
    public static function items(Model $patrol, string $field, array $names): array
    {
        $items = [];
        foreach ($patrol->{$field.'_ids'} ?? [] as $id) {
            if (isset($names[$id])) {
                $items[] = ['id' => (int) $id, 'nama' => $names[$id]];
            }
        }
        foreach (self::legacyNames($patrol, $field, $names) as $nama) {
            $items[] = ['id' => null, 'nama' => $nama];
        }

        return $items;
    }

    /**
     * Nama di kolom teks yang bukan milik karyawan terpilih (data lama ketik manual / nama karyawan ambigu).
     *
     * @param  array<int, string>|null  $names  null = diambil dari database
     * @return list<string>
     */
    public static function legacyNames(?Model $patrol, string $field, ?array $names = null): array
    {
        if (! $patrol) {
            return [];
        }

        $names ??= self::namesFor([$patrol], [$field]);
        $linked = array_map(fn ($id) => $names[$id] ?? null, $patrol->{$field.'_ids'} ?? []);

        return array_values(array_diff(self::split((string) $patrol->{$field}), array_filter($linked)));
    }

    /**
     * Nama karyawan diubah di HRMS: perbarui label nama di temuan yang memilih karyawan ini.
     * Label disusun ulang dari karyawan terpilih (nama terbaru) + nama lama yang belum terhubung.
     */
    public static function refreshNamesOf(Karyawan $karyawan): void
    {
        $fieldsByModel = [DailyPatrol::class => ['pic', 'pic_patrol'], CrossPatrol::class => ['pic', 'patroler']];
        $oldName = (string) $karyawan->getOriginal('nama');

        foreach ($fieldsByModel as $model => $fields) {
            foreach ($fields as $field) {
                $model::query()->whereJsonContains($field.'_ids', $karyawan->id)->each(function (Model $patrol) use ($field, $oldName) {
                    $names = self::namesFor([$patrol], [$field]);
                    $legacy = array_diff(self::legacyNames($patrol, $field, $names), [$oldName]);
                    $current = array_map(fn ($id) => $names[$id] ?? null, $patrol->{$field.'_ids'});

                    $patrol->forceFill([$field => implode(', ', array_unique([...array_filter($current), ...$legacy]))])->saveQuietly();
                });
            }
        }
    }

    /**
     * @return list<array{id: ?int, nama: string}>|null null = format tidak valid
     */
    public static function decode(mixed $value): ?array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;
        if (! is_array($decoded) || ! array_is_list($decoded)) {
            return null;
        }

        $items = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                return null;
            }
            $id = $item['id'] ?? null;
            $nama = is_string($item['nama'] ?? null) ? trim($item['nama']) : '';
            if (($id !== null && ! is_int($id)) || ($id === null && $nama === '')) {
                return null;
            }
            $items[] = ['id' => $id, 'nama' => $nama];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public static function split(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), 'strlen')));
    }
}
