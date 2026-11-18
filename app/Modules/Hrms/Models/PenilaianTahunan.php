<?php

namespace App\Modules\Hrms\Models;

use App\Models\User;
use App\Modules\Hrms\Support\JenjangPenilaian;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'karyawan_id', 'tahun', 'jabatan', 'department_id', 'form', 'tahapan', 'tahap', 'posisi_level', 'status',
    'absen', 'nilai_absen', 'skor_penilai', 'nilai_performance', 'nilai_akhir', 'huruf', 'catatan', 'hasil',
    'penilai_id', 'submitted_at',
])]
class PenilaianTahunan extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_MENUNGGU = 'menunggu';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_MENUNGGU => 'Menunggu approval',
        self::STATUS_DISETUJUI => 'Disetujui',
    ];

    /** Pilihan "Hasil Penilaian" di form (untuk karyawan kontrak). */
    public const HASIL = ['Dapat dilanjutkan', 'Tidak dapat dilanjutkan', 'Diangkat menjadi karyawan tetap'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tahapan' => 'array',
            'absen' => 'array',
            'skor_penilai' => 'array',
            'nilai_absen' => 'float',
            'nilai_performance' => 'float',
            'nilai_akhir' => 'float',
            'submitted_at' => 'datetime',
        ];
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penilai_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PenilaianTahunanLog::class)->oldest('id');
    }

    /** Masih bisa diubah penilai: belum dikirim. Setelah dikirim, hanya approver yang sedang mendapat giliran yang bisa mengubah. */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isMenunggu(): bool
    {
        return $this->status === self::STATUS_MENUNGGU;
    }

    /**
     * Skor tiap penilai, urut tahap: [tahap => ['level' => jenjang penilai, 'skor' => [kategori => [index => huruf]]]].
     *
     * @return array<int, array{level: ?string, skor: array<string, array<int, string>>}>
     */
    public function skorPerPenilai(): array
    {
        $skorPenilai = [];
        foreach ($this->skor_penilai ?? [] as $tahap => $isian) {
            $skorPenilai[(int) $tahap] = ['level' => $isian['level'] ?? null, 'skor' => $isian['skor'] ?? []];
        }
        ksort($skorPenilai);

        return $skorPenilai;
    }

    /**
     * Skor yang diisi penilai di tahap ini, atau skor penilai sebelumnya (untuk pilihan awal approver).
     *
     * @return array<string, array<int, string>>
     */
    public function skorUntukTahap(int $tahap): array
    {
        $sebelumnya = [];
        foreach ($this->skorPerPenilai() as $tahapPenilai => $isian) {
            if ($tahapPenilai > $tahap) {
                break;
            }
            $sebelumnya = $isian['skor'];
        }

        return $sebelumnya;
    }

    /**
     * Simpan skor penilai di tahap ini (menimpa isian tahap yang sama).
     *
     * @param  array<string, array<int, string>>  $skor
     */
    public function isiSkorPenilai(int $tahap, ?string $level, array $skor): void
    {
        $skorPenilai = $this->skor_penilai ?? [];
        $skorPenilai[(string) $tahap] = ['level' => $level, 'skor' => $skor];
        ksort($skorPenilai);
        $this->skor_penilai = $skorPenilai;
    }

    /**
     * Daftar set skor semua penilai, untuk dihitung rata-ratanya.
     *
     * @return list<array<string, array<int, string>>>
     */
    public function daftarSkor(): array
    {
        return array_values(array_column($this->skorPerPenilai(), 'skor'));
    }

    /**
     * Penilaian yang sedang menunggu approval di jenjang user.
     */
    public function scopePerluTindakan(Builder $query, User $user): void
    {
        $level = JenjangPenilaian::userLevel($user);
        $departmentIds = JenjangPenilaian::departmentIds($user);

        if ($level === null || $departmentIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('status', self::STATUS_MENUNGGU)
            ->where('posisi_level', $level)
            ->whereIn('department_id', $departmentIds);
    }
}
