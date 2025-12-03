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
        Schema::create('course_instances', function (Blueprint $table) {
            $table->id();

            // Relasi ke template mata kuliah
            $table->foreignId('course_template_id')
                ->constrained('course_templates')
                ->cascadeOnDelete();

            // Nama/label kelas, misal: "A", "B", "C"
            $table->string('class_name', 50);

            // Semester, misal: "2024/2025 Ganjil"
            $table->string('semester', 50);

            // Dosen pengampu (user dengan role = dosen)
            $table->foreignId('lecturer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Status kelas: draft / active / finished
            $table->enum('status', ['draft', 'active', 'finished'])
                ->default('draft');

            // Tanggal mulai & selesai kelas
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Opsional: deskripsi khusus untuk kelas ini
            $table->text('notes')->nullable();

            $table->timestamps();

            // Index tambahan (opsional, buat pencarian cepat)
            $table->index(['semester', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_instances');
    }
};
