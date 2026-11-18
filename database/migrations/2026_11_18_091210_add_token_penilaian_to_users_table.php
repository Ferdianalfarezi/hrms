<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Token penilaian: kode unik penilai (dicantumkan di verifikasi tanda tangan) + sisa jatah tanda tangan penilaian.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('token_kode', 20)->nullable()->unique()->after('jabatan');
            $table->unsignedInteger('token_penilaian')->default(100)->after('token_kode');
        });

        DB::table('users')->whereNull('token_kode')->orderBy('id')->each(function (object $user) {
            DB::table('users')->where('id', $user->id)->update(['token_kode' => 'PNL-'.Str::upper(Str::random(8))]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['token_kode']);
            $table->dropColumn(['token_kode', 'token_penilaian']);
        });
    }
};
