<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin Departemen bisa memegang lebih dari satu departemen per modul.
     * Departemen tunggal di user_app_roles.department_id dipindah ke tabel ini, lalu kolom lamanya dihapus.
     */
    public function up(): void
    {
        Schema::create('user_managed_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Nama index dibuat pendek: nama otomatis melebihi batas 64 karakter MySQL.
            $table->unique(['user_id', 'application_id', 'department_id'], 'user_managed_departments_unique');
        });

        $now = now();
        DB::table('user_app_roles')->whereNotNull('department_id')->orderBy('id')
            ->get(['user_id', 'application_id', 'department_id'])
            ->each(fn (object $row) => DB::table('user_managed_departments')->insert([
                'user_id' => $row->user_id,
                'application_id' => $row->application_id,
                'department_id' => $row->department_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        Schema::table('user_app_roles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }

    /**
     * Kembali ke satu departemen per modul (departemen pertama yang tersimpan).
     */
    public function down(): void
    {
        Schema::table('user_app_roles', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('role_id')->constrained()->nullOnDelete();
        });

        DB::table('user_managed_departments')->orderBy('id')->get()
            ->unique(fn (object $row) => $row->user_id.'-'.$row->application_id)
            ->each(fn (object $row) => DB::table('user_app_roles')
                ->where('user_id', $row->user_id)
                ->where('application_id', $row->application_id)
                ->update(['department_id' => $row->department_id]));

        Schema::dropIfExists('user_managed_departments');
    }
};
