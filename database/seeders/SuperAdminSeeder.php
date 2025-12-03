<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Jalankan database seeds.
     */
    public function run(): void
    {
        $username = 'admin';
        $password = 'admin123'; // ganti di production nanti

        // Cek apakah sudah ada superadmin dengan username ini
        if (User::where('username', $username)->exists()) {
            return;
        }

        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'username' => $username,
            'role' => 'superadmin',
            'status' => 'active',
            'password' => Hash::make($password),
            'force_password_change' => true,
        ]);
    }
}
