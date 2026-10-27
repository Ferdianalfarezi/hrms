<?php

namespace App\Modules\Hrms\Policies;

use App\Models\User;
use App\Modules\Hrms\Models\Karyawan;

/**
 * Hak akses data karyawan:
 * - Super admin & Admin HRMS: semua karyawan, termasuk import.
 * - Admin Departemen: hanya karyawan di departemen yang dipegangnya (lihat, tambah, edit, hapus), tanpa import.
 */
class KaryawanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessApplication('hrms');
    }

    public function create(User $user): bool
    {
        return $user->isModuleAdmin('hrms') || $user->managedDepartmentIds() !== [];
    }

    public function update(User $user, Karyawan $karyawan): bool
    {
        return $user->isModuleAdmin('hrms') || $this->ownsDepartment($user, $karyawan);
    }

    public function delete(User $user, Karyawan $karyawan): bool
    {
        return $this->update($user, $karyawan);
    }

    public function import(User $user): bool
    {
        return $user->isModuleAdmin('hrms');
    }

    private function ownsDepartment(User $user, Karyawan $karyawan): bool
    {
        return $user->managesDepartment('hrms', $karyawan->department_id);
    }
}
