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
        // Temuan Daily Patrol (modul SMS). Diisi Admin Departemen untuk area (departemen) yang dipegangnya.
        Schema::create('daily_patrols', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->text('finding');
            $table->string('foto');
            $table->text('potensi_bahaya');
            // Area = departemen tempat temuan.
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('plant', 50);
            $table->text('rekomendasi');
            $table->string('resiko', 1);
            $table->string('kategori', 30);
            $table->date('target');
            $table->string('pic');
            $table->string('foto_perbaikan')->nullable();
            $table->string('status', 10)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'tanggal']);
            $table->index('status');
        });

        // Modul SMS sekarang juga punya role "Admin Departemen" (departemennya dipilih Super Admin di Kelola User).
        $smsId = DB::table('applications')->where('code', 'sms')->value('id');

        if ($smsId && ! DB::table('roles')->where('application_id', $smsId)->where('code', 'dept_admin')->exists()) {
            DB::table('roles')->insert([
                'application_id' => $smsId, 'code' => 'dept_admin', 'name' => 'Admin Departemen',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_patrols');

        $smsId = DB::table('applications')->where('code', 'sms')->value('id');

        if ($smsId) {
            DB::table('roles')->where('application_id', $smsId)->where('code', 'dept_admin')->delete();
        }
    }
};
