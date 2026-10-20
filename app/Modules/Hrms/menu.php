<?php

// Tab menu modul HRMS. "active" = pola nama route yang membuat tab ini menyala,
// "can" = gate yang harus lolos supaya tab tampil, "cannot" = tab hanya tampil kalau gate ini gagal, "children" = isi dropdown (tab memakai link anak pertama yang tampil).
return [
    ['label' => 'Dashboard', 'route' => 'hrms.dashboard', 'active' => 'hrms.dashboard'],
    ['label' => 'Karyawan', 'route' => 'hrms.karyawans.index', 'active' => 'hrms.karyawans.*'],
    ['label' => 'Absensi', 'active' => 'hrms.absensi.*', 'children' => [
        ['label' => 'Harian', 'route' => 'hrms.absensi.index', 'active' => 'hrms.absensi.index', 'can' => ['manage-module', 'hrms']],
        // Admin Departemen: tabel input (Daftar) + matriks bulanan departemennya (Timeline).
        ['label' => 'Daftar', 'route' => 'hrms.absensi.index', 'active' => 'hrms.absensi.index', 'cannot' => ['manage-module', 'hrms']],
        ['label' => 'Timeline', 'route' => 'hrms.absensi.timeline', 'active' => 'hrms.absensi.timeline', 'cannot' => ['manage-module', 'hrms']],
        ['label' => 'Detail', 'route' => 'hrms.absensi.detail', 'active' => 'hrms.absensi.detail', 'can' => ['manage-module', 'hrms']],
        ['label' => 'Komparasi Mesin', 'route' => 'hrms.absensi.komparasi', 'active' => 'hrms.absensi.komparasi*', 'can' => ['manage-module', 'hrms']],
    ]],
    ['label' => 'Departemen', 'route' => 'hrms.departments.index', 'active' => 'hrms.departments.*', 'can' => ['manage-module', 'hrms']],
];
