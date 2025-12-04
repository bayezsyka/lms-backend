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
        Schema::create('materials', function (Blueprint $table) {
            $table->id();

            // Materi selalu terkait ke satu section (minggu/topik)
            $table->foreignId('section_id')
                ->constrained()
                ->cascadeOnDelete();

            // Judul materi
            $table->string('title');

            // Deskripsi materi (opsional)
            $table->text('description')->nullable();

            // Tipe materi: file atau link
            $table->enum('type', ['file', 'link']);

            // Jika type = file → path file yang disimpan di Laravel Storage
            $table->string('file_path')->nullable();

            // Jika type = link → URL eksternal (Google Drive, YouTube, dsb)
            $table->text('url')->nullable();

            // Subject / tag materi (misal: "teori", "praktikum", "video", dll)
            $table->string('subject')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
