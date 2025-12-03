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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();

            // Kelas yang diambil
            $table->foreignId('course_instance_id')
                ->constrained()
                ->cascadeOnDelete();

            // Mahasiswa yang ter-enroll (user dengan role "mahasiswa")
            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Status keikutsertaan
            $table->enum('status', ['active', 'dropped'])
                ->default('active');

            // Info waktu penting
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('dropped_at')->nullable();

            $table->timestamps();

            // Satu mahasiswa hanya boleh sekali per kelas
            $table->unique(['course_instance_id', 'student_id'], 'enrollments_unique_course_student');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
