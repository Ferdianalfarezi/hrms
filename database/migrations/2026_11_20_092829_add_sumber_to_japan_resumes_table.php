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
        // Japan Resume bisa diedit: "excel" = data dari file HRD (2026 summary), "manual" = angka hari otomatis
        // yang disesuaikan admin (bisa dikembalikan ke hitungan otomatis). updated_by = admin terakhir yang mengedit.
        Schema::table('japan_resumes', function (Blueprint $table) {
            $table->string('sumber', 10)->default('excel')->after('sakit_izin');
            $table->foreignId('updated_by')->nullable()->after('sumber')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('japan_resumes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('sumber');
        });
    }
};
