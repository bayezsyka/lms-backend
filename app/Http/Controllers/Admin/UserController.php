<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * List user dengan optional filter:
     * - role
     * - status
     * - keyword (name / username / email)
     *
     * GET /api/admin/users?role=dosen&status=active&keyword=udin
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('keyword')) {
            $keyword = $request->query('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('username', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%');
            });
        }

        $users = $query
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'username',
                'nim',
                'email',
                'role',
                'status',
                'force_password_change',
                'created_at',
            ]);

        return response()->json($users);
    }

    /**
     * Detail user.
     * GET /api/admin/users/{id}
     */
    public function show(int $id)
    {
        $user = User::findOrFail($id);

        return response()->json($user);
    }

    /**
     * Helper: generate password berdasarkan nama.
     *
     * Pola:
     *  - ambil 6 karakter pertama dari nama (huruf/angka, lower, tanpa spasi)
     *  - tambah 3 karakter random (lowercase)
     *
     * Contoh:
     *  nama: "Farros Baskyailakh" → base: "farros" → password: "farrosabc"
     */
    protected function generatePasswordFromName(string $name): string
    {
        // bersihkan jadi huruf/angka saja
        $base = Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]/', '')
            ->substr(0, 6)
            ->__toString();

        if ($base === '') {
            // kalau namanya aneh (semua simbol), fallback random 6 char
            $base = Str::lower(Str::random(6));
        }

        $suffix = Str::lower(Str::random(3));

        return $base . $suffix;
    }

    /**
     * Create user baru.
     * POST /api/admin/users
     *
     * - Kalau request mengirim "password" → pakai itu.
     * - Kalau tidak mengirim "password" → generate dari nama:
     *   (6 huruf pertama nama) + (3 char random).
     *
     * Return: user + plain password agar bisa dibagikan oleh admin.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'nim' => ['nullable', 'string', 'max:50', 'unique:users,nim'],
            'role' => ['required', 'in:superadmin,dosen,mahasiswa'],
            'status' => ['required', 'in:active,inactive'],
            'password' => ['nullable', 'string', 'min:6'],
            'force_password_change' => ['sometimes', 'boolean'],
        ]);

        // Jika password dikirim dari frontend (misalnya via import Excel), pakai itu.
        // Kalau tidak ada, generate berdasarkan nama.
        $plainPassword = $data['password'] ?? $this->generatePasswordFromName($data['name']);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'nim' => $data['nim'] ?? null,
            'email' => $data['email'] ?? null,
            'role' => $data['role'],
            'status' => $data['status'],
            'password' => Hash::make($plainPassword),
            'force_password_change' => $data['force_password_change'] ?? true,
        ]);

        return response()->json([
            'user' => $user,
            'password' => $plainPassword,
        ], 201);
    }

    /**
     * Update data user (tanpa reset password).
     * PUT /api/admin/users/{id}
     *
     * Bisa dipakai untuk:
     * - ubah name
     * - ubah username / email
     * - ubah nim
     * - ubah role
     * - ubah status (active / inactive)
     * - ubah force_password_change
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => ['sometimes', 'required', 'string', 'max:50', 'unique:users,username,' . $user->id],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'nim' => ['nullable', 'string', 'max:50', 'unique:users,nim,' . $user->id],
            'role' => ['sometimes', 'required', 'in:superadmin,dosen,mahasiswa'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
            'force_password_change' => ['sometimes', 'boolean'],
        ]);

        $user->fill($data);
        $user->save();

        return response()->json($user);
    }

    /**
     * Reset password user menjadi password baru.
     * POST /api/admin/users/{id}/reset-password
     *
     * Pola password:
     *  (6 huruf pertama nama) + (3 char random)
     *
     * Return: password baru (plaintext) agar admin bisa kirim ke user.
     */
    public function resetPassword(int $id)
    {
        $user = User::findOrFail($id);

        $plainPassword = $this->generatePasswordFromName($user->name);

        $user->password = Hash::make($plainPassword);
        $user->force_password_change = true;
        $user->save();

        return response()->json([
            'message' => 'Password berhasil direset.',
            'password' => $plainPassword,
        ]);
    }

    /**
     * Hapus user.
     * DELETE /api/admin/users/{id}
     */
    public function destroy(int $id)
    {
        $user = User::findOrFail($id);

        $user->delete();

        return response()->json([
            'message' => 'User berhasil dihapus.',
        ]);
    }
}
