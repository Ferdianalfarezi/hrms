<?php

namespace App\Modules\Hrms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nik', 'nama', 'department_id', 'jenis_kelamin', 'tempat_lahir', 'tgl_lahir', 'jabatan', 'tanggal_masuk', 'status', 'group', 'potongan_absen'])]
class Karyawan extends Model
{
    public const JENIS_KELAMIN = ['Laki-laki', 'Perempuan'];

    public const STATUS = ['Tetap', 'PKWT 1', 'PKWT 2', 'PKWT 3', 'Pemagangan'];

    /** Potongan gaji default (rupiah) per 30 menit datang terlambat. */
    public const POTONGAN_ABSEN_DEFAULT = 50000;

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
            'potongan_absen' => 'integer',
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
     * Batasi data sesuai hak user: Admin Departemen hanya melihat departemennya,
     * user tanpa hak melihat data karyawan tidak mendapat apa-apa.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isModuleAdmin('hrms')) {
            return;
        }

        $departmentId = $user->managedDepartmentId();

        $departmentId === null
            ? $query->whereRaw('1 = 0')
            : $query->where('department_id', $departmentId);
    }
}
