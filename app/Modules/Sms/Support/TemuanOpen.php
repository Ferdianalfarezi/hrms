<?php

namespace App\Modules\Sms\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Angka notifikasi menu Daily / Cross Patrol: jumlah temuan yang masih Open (perlu di-Close) yang terlihat oleh user.
 * Admin SMS menghitung semua departemen, Admin Departemen hanya departemennya.
 */
class TemuanOpen
{
    /**
     * @param  class-string<Model>  $model  DailyPatrol::class atau CrossPatrol::class
     */
    public static function count(string $model, ?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $query = $model::query()->where('status', $model::STATUS_OPEN);

        if ($user->isModuleAdmin('sms')) {
            return $query->count();
        }

        $departmentId = $user->managedDepartmentId('sms');

        return $departmentId === null ? 0 : $query->where('department_id', $departmentId)->count();
    }
}
