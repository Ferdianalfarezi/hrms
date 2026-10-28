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
        // User yang mengubah status temuan menjadi Close (ditampilkan di bawah badge status).
        // Nullable: temuan Open & data lama yang sudah Close sebelum kolom ini ada.
        foreach (['daily_patrols', 'cross_patrols'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['daily_patrols', 'cross_patrols'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('closed_by');
            });
        }
    }
};
