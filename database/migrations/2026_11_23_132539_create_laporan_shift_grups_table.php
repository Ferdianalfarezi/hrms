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
        // Grup shift malam dan pagi untuk laporan satu tanggal, menggantikan jadwal rotasi hanya di laporan itu.
        Schema::create('laporan_shift_grups', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('grup_malam', 5);
            $table->string('grup_pagi', 5);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_shift_grups');
    }
};
