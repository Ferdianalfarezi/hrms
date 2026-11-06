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
        // Folder di menu ISO (bisa bertingkat lewat parent_id). Isi folder ikut departemen folder-nya;
        // department_id kosong = folder umum.
        Schema::create('iso_folders', function (Blueprint $table) {
            $table->id();
            $table->string('kategori', 20);
            $table->foreignId('parent_id')->nullable()->constrained('iso_folders')->restrictOnDelete();
            $table->string('nama');
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kategori', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iso_folders');
    }
};
