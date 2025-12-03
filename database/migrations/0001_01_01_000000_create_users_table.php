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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Identitas dasar
            $table->string('name', 100);

            // Email opsional (boleh null), tapi kalau diisi harus unik
            $table->string('email', 150)->nullable()->unique();

            // Username untuk login (NIM / NIP / username), wajib unik
            $table->string('username', 50)->unique();

            // Role user di sistem
            // - superadmin
            // - dosen
            // - mahasiswa
            $table->enum('role', ['superadmin', 'dosen', 'mahasiswa'])
                  ->default('mahasiswa');

            // Password yang sudah di-hash (bcrypt)
            $table->string('password');

            // Dipakai untuk memaksa user ganti password awal
            $table->boolean('force_password_change')->default(true);

            // Status akun: active / inactive
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Fitur remember me (kalau nanti pakai session, aman saja ada)
            $table->rememberToken();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
