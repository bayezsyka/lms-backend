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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();

            // Kelas / course per semester yang menjadi induk section ini
            $table->foreignId('course_instance_id')
                ->constrained()    // default: references 'id' on 'course_instances'
                ->cascadeOnDelete();

            // Judul/topik minggu, misal: "Minggu 1 - Pengantar Mata Kuliah"
            $table->string('title');

            // Urutan section dalam 1 kelas (1,2,3,...)
            $table->unsignedSmallInteger('order')->default(1);

            // Optional: jaga konsistensi urutan per kelas
            $table->unique(['course_instance_id', 'order'], 'sections_course_order_unique');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
