<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login dengan username (NIM/NIP) + password.
     * Response: token + data user (role, force_password_change, dll).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::where('username', $credentials['username'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Username atau password salah.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Akun tidak aktif. Silakan hubungi administrator.',
            ], 403);
        }

        // Opsional: hapus semua token lama supaya hanya 1 session aktif
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'force_password_change' => $user->force_password_change,
            ],
        ]);
    }

    /**
     * Logout: hapus token saat ini.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Profil user login (me).
     */
    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'force_password_change' => $user->force_password_change,
            'created_at' => $user->created_at,
        ]);
    }

    /**
     * Ganti password user login.
     * - old_password wajib benar
     * - new_password wajib minimal 6 char
     * - Setelah berhasil, force_password_change = false
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data['old_password'], $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai.',
            ], 422);
        }

        $user->password = Hash::make($data['new_password']);
        $user->force_password_change = false;
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diganti.',
        ]);
    }
}
