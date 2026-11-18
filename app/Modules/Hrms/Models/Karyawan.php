<?php

namespace App\Modules\Hrms\Models;

use App\Models\User;
use App\Modules\Hrms\Support\JenjangPenilaian;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['nik', 'nama', 'department_id', 'jenis_kelamin', 'tempat_lahir', 'tgl_lahir', 'jabatan', 'tanggal_masuk', 'status', 'group'])]
class Karyawan extends Model
{
    public const JENIS_KELAMIN = ['Laki-laki', 'Perempuan'];

    public const STATUS = ['Tetap', 'PKWT 1', 'PKWT 2', 'PKWT 3', 'Pemagangan'];

    /** Tempat lahir (huruf besar) yang membuat karyawan bebas absen mesin. */
    public const TEMPAT_LAHIR_BEBAS_ABSEN_MESIN = ['JAPAN', 'JEPANG'];

    /** NIK yang dikecualikan khusus dari absen mesin, di luar aturan jabatan / tempat lahir. */
    public const NIK_BEBAS_ABSEN_MESIN = ['0098'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tgl_lahir' => 'date',
            'tanggal_masuk' => 'date',
        ];
    }

    /**
     * Umur dihitung dari tgl_lahir terhadap tanggal hari ini (tidak disimpan di database),
     * jadi otomatis bertambah setiap karyawan berulang tahun.
     */
    protected function umur(): Attribute
    {
        return Attribute::get(fn () => $this->tgl_lahir?->age);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Karyawan yang tidak wajib tap mesin: jabatan Asst. Manager ke atas, tempat lahir Jepang, atau NIK pengecualian khusus.
     * Mereka tetap tercatat "tidak absen" di Komparasi Mesin, tapi di Harian / laporan dianggap masuk
     * selama admin belum mengisi absensinya secara manual.
     */
    public function scopeBebasAbsenMesin(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('jabatan', self::jabatanBebasAbsenMesin())
            ->orWhereIn(DB::raw('UPPER(TRIM(tempat_lahir))'), self::TEMPAT_LAHIR_BEBAS_ABSEN_MESIN)
            ->orWhereIn('nik', self::NIK_BEBAS_ABSEN_MESIN));
    }

    /**
     * Versi per model dari scopeBebasAbsenMesin (butuh kolom jabatan & tempat_lahir sudah dimuat).
     */
    public function isBebasAbsenMesin(): bool
    {
        return in_array($this->jabatan, self::jabatanBebasAbsenMesin(), true)
            || in_array((string) $this->nik, self::NIK_BEBAS_ABSEN_MESIN, true)
            || in_array(mb_strtoupper(trim((string) $this->tempat_lahir)), self::TEMPAT_LAHIR_BEBAS_ABSEN_MESIN, true);
    }

    /**
     * Jabatan jenjang Asst. Manager / Manager ke atas.
     *
     * @return list<string>
     */
    public static function jabatanBebasAbsenMesin(): array
    {
        return array_keys(array_filter(
            JenjangPenilaian::JABATAN,
            fn (string $level) => JenjangPenilaian::rank($level) >= JenjangPenilaian::RANK['manager'],
        ));
    }

    /**
     * Batasi data sesuai hak user: Admin Departemen hanya melihat departemennya (gabungan kalau lebih dari satu),
     * user tanpa hak melihat data karyawan tidak mendapat apa-apa.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isModuleAdmin('hrms')) {
            return;
        }

        $departmentIds = $user->managedDepartmentIds();

        $departmentIds === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('department_id', $departmentIds);
    }
}
