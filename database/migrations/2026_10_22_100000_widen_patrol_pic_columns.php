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
        // PIC / PIC Patrol / Patroler sekarang dipilih dari data karyawan dan bisa lebih dari 1
        // (nama dipisah koma), jadi kolomnya diperbesar supaya tidak terpotong di 255 karakter.
        Schema::table('daily_patrols', function (Blueprint $table) {
            $table->text('pic')->change();
            $table->text('pic_patrol')->nullable()->change();
        });

        Schema::table('cross_patrols', function (Blueprint $table) {
            $table->text('pic')->change();
            $table->text('patroler')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_patrols', function (Blueprint $table) {
            $table->string('pic')->change();
            $table->string('pic_patrol')->nullable()->change();
        });

        Schema::table('cross_patrols', function (Blueprint $table) {
            $table->string('pic')->change();
            $table->string('patroler')->change();
        });
    }
};
