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
        // Dokumen yang sudah ada tetap di luar folder (folder_id kosong).
        Schema::table('iso_documents', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('kategori')->constrained('iso_folders')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iso_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });
    }
};
