<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            // Grup kerja dari kolom GROUP di Excel (mis. A, B, NS). Kosong = belum ada grup.
            $table->string('group', 20)->nullable()->after('status')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropIndex(['group']);
            $table->dropColumn('group');
        });
    }
};
