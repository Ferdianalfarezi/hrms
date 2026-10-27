<?php

// Tab menu modul SMS. "active" = pola nama route yang membuat tab ini menyala,
// "badge" = jumlah temuan Open yang perlu di-Close (Admin SMS: semua departemen, Admin Departemen: departemennya).

use App\Modules\Sms\Models\CrossPatrol;
use App\Modules\Sms\Models\DailyPatrol;
use App\Modules\Sms\Support\TemuanOpen;

return [
    ['label' => 'Dashboard', 'route' => 'sms.dashboard', 'active' => 'sms.dashboard'],
    ['label' => 'Daily Patrol', 'route' => 'sms.daily-patrols.index', 'active' => 'sms.daily-patrols.*', 'can' => ['sms-patrol'],
        'badge' => fn (): int => TemuanOpen::count(DailyPatrol::class, auth()->user())],
    ['label' => 'Cross Patrol', 'route' => 'sms.cross-patrols.index', 'active' => 'sms.cross-patrols.*', 'can' => ['sms-patrol'],
        'badge' => fn (): int => TemuanOpen::count(CrossPatrol::class, auth()->user())],
    ['label' => 'Kecelakaan', 'route' => 'sms.kecelakaan.index', 'active' => 'sms.kecelakaan.*', 'can' => ['sms-patrol']],
];
