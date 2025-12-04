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
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();

            // Quiz yang dikerjakan
            $table->foreignId('quiz_id')
                ->constrained()
                ->cascadeOnDelete();

            // Mahasiswa yang mengerjakan
            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Nomor percobaan (1,2,3,...)
            $table->unsignedInteger('attempt_number')->default(1);

            // Jawaban dalam bentuk JSON (array soal & jawaban)
            $table->longText('answers')->nullable();

            // Skor hasil perhitungan (0..max_score)
            $table->unsignedInteger('score')->nullable();

            // Waktu mulai dan selesai attempt
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->timestamps();

            // Index untuk pencarian cepat
            $table->index(['quiz_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
