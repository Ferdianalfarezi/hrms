<?php

// Tab menu modul ISO. Procedures, Forms & SOP sementara punya fungsi yang sama (kartu dokumen & folder seperti Google Drive).

return [
    ['label' => 'Dashboard', 'route' => 'iso.dashboard', 'active' => 'iso.dashboard', 'can' => ['iso-documents']],
    ['label' => 'Procedures', 'route' => 'iso.procedures.index', 'active' => 'iso.procedures.*', 'can' => ['iso-documents']],
    ['label' => 'Forms', 'route' => 'iso.forms.index', 'active' => 'iso.forms.*', 'can' => ['iso-documents']],
    ['label' => 'SOP', 'route' => 'iso.sop.index', 'active' => 'iso.sop.*', 'can' => ['iso-documents']],
    // Penerima email notifikasi pembaruan dokumen: khusus Admin ISO.
    ['label' => 'Email', 'route' => 'iso.emails.index', 'active' => 'iso.emails.*', 'can' => ['manage-module', 'iso']],
];
