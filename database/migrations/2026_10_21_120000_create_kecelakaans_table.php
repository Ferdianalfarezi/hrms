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
        // Laporan kecelakaan (modul SMS). Kategori hanya diisi untuk Traffic Accident.
        Schema::create('kecelakaans', function (Blueprint $table) {
            $table->id();
            $table->string('tipe', 20);
            $table->string('kategori', 30)->nullable();
            $table->dateTime('waktu');
            $table->string('nama_korban');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->text('deskripsi');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'waktu']);
            $table->index('tipe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kecelakaans');
    }
};
