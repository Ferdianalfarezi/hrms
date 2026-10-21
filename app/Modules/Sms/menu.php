<?php

// Tab menu modul SMS. "active" = pola nama route yang membuat tab ini menyala.
return [
    ['label' => 'Dashboard', 'route' => 'sms.dashboard', 'active' => 'sms.dashboard'],
    ['label' => 'Daily Patrol', 'route' => 'sms.daily-patrols.index', 'active' => 'sms.daily-patrols.*', 'can' => ['sms-patrol']],
    ['label' => 'Cross Patrol', 'route' => 'sms.cross-patrols.index', 'active' => 'sms.cross-patrols.*', 'can' => ['sms-patrol']],
    ['label' => 'Kecelakaan', 'route' => 'sms.kecelakaan.index', 'active' => 'sms.kecelakaan.*', 'can' => ['sms-patrol']],
];
