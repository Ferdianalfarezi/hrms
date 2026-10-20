<?php

namespace App\Modules\Hrms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['karyawan_id', 'tanggal', 'kode', 'keterangan', 'created_by', 'updated_by'])]
class Absensi extends Model
{
    /**
     * Kode absensi mengikuti file "Acuan menu Absensi.xlsx".
     * "hadir" = karyawan tetap dihitung hadir (hanya datang terlambat / pulang cepat).
     */
    public const KODE = [
        'A' => ['label' => 'Alpa (tanpa keterangan)', 'tone' => 'red', 'hadir' => false],
        'I' => ['label' => 'Izin', 'tone' => 'red', 'hadir' => false],
        'S' => ['label' => 'Sakit', 'tone' => 'red', 'hadir' => false],
        'SD' => ['label' => 'Sakit (surat dokter)', 'tone' => 'red', 'hadir' => false],
        'DT<' => ['label' => 'Datang terlambat ≤ 30 menit', 'tone' => 'orange', 'hadir' => true],
        'DT>' => ['label' => 'Datang terlambat ≥ 30 menit', 'tone' => 'orange', 'hadir' => true],
        'PC<' => ['label' => 'Pulang cepat ≤ 30 menit', 'tone' => 'orange', 'hadir' => true],
        'PC>' => ['label' => 'Pulang cepat ≥ 30 menit', 'tone' => 'orange', 'hadir' => true],
        'CT' => ['label' => 'Cuti tahunan', 'tone' => 'blue', 'hadir' => false],
        'IMP' => ['label' => 'Izin meninggalkan pekerjaan', 'tone' => 'blue', 'hadir' => false],
        'CK' => ['label' => 'Cuti khusus', 'tone' => 'blue', 'hadir' => false],
        'CH' => ['label' => 'Cuti hamil', 'tone' => 'blue', 'hadir' => false],
        'CB' => ['label' => 'Cuti bersama', 'tone' => 'blue', 'hadir' => false],
        'DISP' => ['label' => 'Dispensasi', 'tone' => 'blue', 'hadir' => false],
        'WFH' => ['label' => 'Work from home', 'tone' => 'blue', 'hadir' => false],
    ];

    /**
     * Kolom "Kategori Absensi" di laporan detail harian. Kode yang hadir (DT/PC) tidak masuk laporan.
     */
    public const KATEGORI = [
        'CT' => ['CT', 'CK', 'CH', 'CB'],
        'S' => ['S', 'SD'],
        'I' => ['I', 'IMP', 'DISP'],
        'WFH' => ['WFH'],
        'Alpa' => ['A'],
    ];

    public static function kategori(string $kode): ?string
    {
        foreach (self::KATEGORI as $kategori => $kodes) {
            if (in_array($kode, $kodes, true)) {
                return $kategori;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function kodeTidakHadir(): array
    {
        return array_merge(...array_values(self::KATEGORI));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
