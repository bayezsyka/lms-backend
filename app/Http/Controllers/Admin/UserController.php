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
     * List user dengan optional filter role.
     * GET /api/admin/users?role=dosen
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        $users = $query
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'username',
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
     * Create user baru dengan password random.
     * POST /api/admin/users
     *
     * Return: user + plain password agar bisa dibagikan oleh admin.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', 'in:superadmin,dosen,mahasiswa'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        // Generate password random
        $plainPassword = Str::random(10);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'role' => $data['role'],
            'status' => $data['status'],
            'password' => Hash::make($plainPassword),
            'force_password_change' => true,
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
     * - ubah role
     * - ubah status (active / inactive)
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => ['sometimes', 'required', 'string', 'max:50', 'unique:users,username,' . $user->id],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'role' => ['sometimes', 'required', 'in:superadmin,dosen,mahasiswa'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
        ]);

        $user->fill($data);
        $user->save();

        return response()->json($user);
    }

    /**
     * Reset password user menjadi password random baru.
     * POST /api/admin/users/{id}/reset-password
     *
     * Return: password baru (plaintext) agar admin bisa kirim ke user.
     */
    public function resetPassword(int $id)
    {
        $user = User::findOrFail($id);

        $plainPassword = Str::random(10);

        $user->password = Hash::make($plainPassword);
        $user->force_password_change = true;
        $user->save();

        return response()->json([
            'message' => 'Password berhasil direset.',
            'password' => $plainPassword,
        ]);
    }

    /**
     * Hapus user (opsional).
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
