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
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();

            // Quiz selalu terkait ke satu section (minggu/topik)
            $table->foreignId('section_id')
                ->constrained()
                ->cascadeOnDelete();

            // Judul quiz
            $table->string('title');

            // Deskripsi singkat quiz (opsional)
            $table->text('description')->nullable();

            // Waktu mulai & selesai quiz (window kapan quiz tersedia)
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();

            // Durasi pengerjaan (dalam menit) sejak mahasiswa mulai attempt.
            // Bisa null kalau mau dianggap fleksibel/belum di-set.
            $table->unsignedInteger('duration_minutes')->nullable();

            // Skor maksimum quiz
            $table->unsignedInteger('max_score')->default(100);

            // Struktur soal (MCQ / essay) dalam bentuk JSON.
            // Nanti isinya bisa array:
            // [
            //   { "id": "q1", "type": "mcq", "question": "...", "options": [...], "answer": "A", "points": 10 },
            //   ...
            // ]
            $table->longText('questions')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
