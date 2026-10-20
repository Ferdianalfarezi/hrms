<?php

namespace App\Modules\Hrms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Department extends Model
{
    /**
     * Tabel departments hanya berisi id dan name, tanpa created_at/updated_at.
     */
    public $timestamps = false;

    public function karyawans(): HasMany
    {
        return $this->hasMany(Karyawan::class);
    }
}
