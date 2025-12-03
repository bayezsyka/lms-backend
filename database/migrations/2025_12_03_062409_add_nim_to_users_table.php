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
        Schema::table('users', function (Blueprint $table) {
            // Tambah kolom NIM untuk mahasiswa.
            // Nullable supaya:
            //  - Superadmin & Dosen boleh tidak punya NIM
            //  - Mahasiswa WAJIB nanti diisi lewat form / seeder
            $table->string('nim')->nullable()->unique()->after('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nim');
        });
    }
};
