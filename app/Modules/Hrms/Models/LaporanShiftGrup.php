<?php

namespace App\Modules\Hrms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Grup shift yang dipakai laporan satu tanggal, menggantikan jadwal rotasi hanya untuk laporan itu.
 * Grup malam dan pagi boleh sama. Grup NS selalu ikut shift pagi.
 */
#[Fillable(['tanggal', 'grup_malam', 'grup_pagi', 'updated_by'])]
class LaporanShiftGrup extends Model
{
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
}
