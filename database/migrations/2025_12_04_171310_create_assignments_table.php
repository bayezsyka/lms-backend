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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();

            // Tugas selalu terkait ke satu section (minggu/topik)
            $table->foreignId('section_id')
                ->constrained()
                ->cascadeOnDelete();

            // Judul tugas
            $table->string('title');

            // Deskripsi singkat tugas (opsional, beda dengan instruksi detail)
            $table->text('description')->nullable();

            // Tipe konten: mahasiswa akan submit dalam bentuk apa (file/link)
            $table->enum('type', ['file', 'link']);

            // Instruksi detail tugas (boleh panjang)
            $table->longText('instructions')->nullable();

            // Deadline pengumpulan
            $table->dateTime('deadline')->nullable();

            // Skor maksimum tugas
            $table->unsignedInteger('max_score')->default(100);

            // Apakah masih boleh submit setelah deadline (late submission)
            $table->boolean('allow_late')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
