<?php

namespace App\Modules\Hrms\Policies;

use App\Models\User;
use App\Modules\Hrms\Models\Karyawan;

/**
 * Hak akses data karyawan:
 * - Super admin & Admin HRMS: semua karyawan, termasuk import.
 * - Admin Departemen: hanya karyawan di departemennya (lihat, tambah, edit, hapus), tanpa import.
 */
class KaryawanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessApplication('hrms');
    }

    public function create(User $user): bool
    {
        return $user->isModuleAdmin('hrms') || $user->managedDepartmentId() !== null;
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
        $departmentId = $user->managedDepartmentId();

        return $departmentId !== null && (int) $karyawan->department_id === $departmentId;
    }
}
