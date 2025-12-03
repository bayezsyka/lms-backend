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
        Schema::create('course_templates', function (Blueprint $table) {
            $table->id();

            // Kode mata kuliah, misal: IF101, MAT201
            $table->string('code', 50)->unique();

            // Nama mata kuliah, misal: "Pengantar Pemrograman"
            $table->string('name', 150);

            // Deskripsi singkat / panjang tentang mata kuliah
            $table->text('description')->nullable();

            // Jumlah SKS, biasanya 1–9
            $table->unsignedTinyInteger('sks');

            // Semester rekomendasi (1,2,3,...), opsional
            $table->unsignedTinyInteger('semester_recommendation')->nullable();

            // Apakah mata kuliah ini masih aktif digunakan
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_templates');
    }
};
