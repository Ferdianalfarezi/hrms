<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_app_roles', function (Blueprint $table) {
            // Departemen yang dipegang user dengan role "Admin Departemen" (HRMS).
            $table->foreignId('department_id')->nullable()->after('role_id')->constrained()->nullOnDelete();
        });

        // Role HRMS sekarang: Admin HRMS & Admin Departemen. "Staff HRMS" diubah jadi
        // "Admin Departemen"; user yang memegangnya perlu dipilihkan departemen oleh Super Admin.
        $hrmsId = DB::table('applications')->where('code', 'hrms')->value('id');

        if ($hrmsId) {
            DB::table('roles')->where('application_id', $hrmsId)->where('code', 'staff')
                ->update(['code' => 'dept_admin', 'name' => 'Admin Departemen', 'updated_at' => now()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hrmsId = DB::table('applications')->where('code', 'hrms')->value('id');

        if ($hrmsId) {
            DB::table('roles')->where('application_id', $hrmsId)->where('code', 'dept_admin')
                ->update(['code' => 'staff', 'name' => 'Staff HRMS', 'updated_at' => now()]);
        }

        Schema::table('user_app_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
