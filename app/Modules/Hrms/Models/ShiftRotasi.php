<?php

namespace App\Modules\Hrms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[Fillable(['tanggal_mulai', 'grup_pagi', 'created_by'])]
class ShiftRotasi extends Model
{
    /** Grup yang bergantian pagi/malam. Grup NS selalu non-shift 07.00 - 16.00. */
    public const GRUP = ['A', 'B'];

    /** Lama satu putaran sebelum grup A dan B bertukar shift. */
    public const PERIODE_HARI = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
        ];
    }

    /**
     * Grup yang masuk shift pagi pada tanggal tersebut, null kalau belum ada titik acuan sebelum tanggal itu.
     *
     * @param  Collection<int, self>|null  $rotasis  daftar acuan yang sudah dimuat (urut tanggal_mulai), supaya tidak query per hari
     */
    public static function grupPagi(Carbon $tanggal, $rotasis = null): ?string
    {
        $rotasis ??= static::orderBy('tanggal_mulai')->get();
        $acuan = $rotasis->filter(fn (self $rotasi) => $rotasi->tanggal_mulai->lte($tanggal))->last();

        if (! $acuan) {
            return null;
        }

        $putaran = intdiv((int) $acuan->tanggal_mulai->diffInDays($tanggal), self::PERIODE_HARI);

        return $putaran % 2 === 0 ? $acuan->grup_pagi : static::lawan($acuan->grup_pagi);
    }

    public static function lawan(string $grup): string
    {
        return $grup === 'A' ? 'B' : 'A';
    }
}
