<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'is_active', 'sort_order'])]
class Application extends Model
{
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

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('applications.is_active', true);
    }

    /**
     * URL tujuan setelah user memilih modul ini.
     */
    public function homeUrl(): string
    {
        return route($this->code.'.dashboard');
    }
}
