<?php

namespace App\Modules\Hrms\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Pemberitahuan ke Admin Departemen setelah Admin HRMS upload data mesin absen:
 * karyawan di departemennya yang tidak absen di mesin tapi belum diberi keterangan.
 * Tampil sebagai toast di pojok kanan bawah sampai ditutup user.
 */
class AbsensiBelumAdaKeterangan extends Notification
{
    /**
     * @param  Carbon  $tanggal  Tanggal data mesin absen (hari kerja).
     * @param  list<array{nik: string, nama: string, tanggal: string}>  $karyawans  tanggal = tanggal yang perlu diisi.
     */
    public function __construct(private Carbon $tanggal, private array $karyawans) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'absensi-belum-ada-keterangan';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $total = count($this->karyawans);

        return [
            'tanggal' => $this->tanggal->toDateString(),
            'title' => "{$total} karyawan belum ada keterangan",
            'subtitle' => 'Tidak absen di mesin · '.$this->tanggal->translatedFormat('l, d M Y'),
            'karyawans' => $this->karyawans,
            'url' => route('hrms.absensi.index', ['tanggal' => $this->karyawans[0]['tanggal'] ?? $this->tanggal->toDateString()]),
        ];
    }
}
