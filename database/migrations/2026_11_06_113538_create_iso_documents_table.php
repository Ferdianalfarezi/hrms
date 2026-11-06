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
        // Dokumen modul ISO (Procedures, Forms, SOP). File disimpan di disk "local" (tidak bisa dibuka tanpa login).
        // department_id kosong = dokumen umum, terlihat oleh semua Admin Departemen ISO.
        Schema::create('iso_documents', function (Blueprint $table) {
            $table->id();
            $table->string('kategori', 20);
            $table->string('nama');
            $table->string('path');
            $table->string('extension', 20)->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kategori', 'department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iso_documents');
    }
};
