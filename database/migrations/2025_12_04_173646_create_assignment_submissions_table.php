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
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();

            // Assignment yang disubmit
            $table->foreignId('assignment_id')
                ->constrained()
                ->cascadeOnDelete();

            // Mahasiswa yang submit (users.id dengan role=mahasiswa)
            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Jawaban bisa berupa file_path atau URL (salah satu diisi)
            $table->string('file_path')->nullable();
            $table->text('url')->nullable();

            // Waktu submit (bisa beda dengan created_at kalau nanti kita butuh)
            $table->dateTime('submitted_at')->nullable();

            // Skor yang diberikan dosen (0..max_score)
            $table->unsignedInteger('score')->nullable();

            // Feedback teks dari dosen
            $table->text('feedback')->nullable();

            // Info penilaian
            $table->foreignId('graded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete(); // kalau dosen dihapus, field ini jadi null

            $table->dateTime('graded_at')->nullable();

            $table->timestamps();

            // Satu mahasiswa idealnya satu submission per assignment (bisa diubah nanti kalau mau multi attempt)
            $table->unique(['assignment_id', 'student_id'], 'assignment_student_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
