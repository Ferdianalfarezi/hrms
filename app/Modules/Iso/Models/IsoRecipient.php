<?php

namespace App\Modules\Iso\Models;

use Database\Factories\IsoRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Penerima email notifikasi pembaruan dokumen ISO (menu Email, dikelola Admin ISO).
 */
#[UseFactory(IsoRecipientFactory::class)]
#[Fillable(['nama', 'email', 'is_active', 'created_by', 'updated_by'])]
class IsoRecipient extends Model
{
    /** @use HasFactory<IsoRecipientFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
