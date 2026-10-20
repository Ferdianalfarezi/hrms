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
        // Titik acuan rotasi grup A/B: mulai tanggal_mulai, grup_pagi masuk shift pagi dan grup lain shift malam,
        // lalu bertukar otomatis setiap minggu sampai ada titik acuan berikutnya.
        Schema::create('shift_rotasis', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_mulai')->unique();
            $table->string('grup_pagi', 5);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_rotasis');
    }
};
