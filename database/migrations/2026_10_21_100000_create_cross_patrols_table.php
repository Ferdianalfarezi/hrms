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
        // Temuan Cross Patrol (modul SMS), kolom mengikuti "Form Temuan Safety Patrol" (K3/FMR/15).
        Schema::create('cross_patrols', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('plant', 50);
            $table->text('finding');
            $table->string('foto');
            $table->text('efek');
            $table->string('resiko', 1);
            // Area temuan (lokasi bebas, mis. OBEYA, QAD); department_id = departemen pemilik data (Admin Departemen).
            $table->string('area', 100);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->text('rekomendasi');
            $table->date('target');
            $table->string('pic');
            $table->string('foto_perbaikan')->nullable();
            $table->string('kategori', 30);
            $table->string('patroler');
            $table->string('status', 10)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['department_id', 'tanggal']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cross_patrols');
    }
};
