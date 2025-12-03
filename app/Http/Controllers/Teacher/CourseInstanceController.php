<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\CourseInstance;
use Illuminate\Http\Request;

class CourseInstanceController extends Controller
{
    /**
     * List semua kelas yang diampu dosen login.
     *
     * GET /api/teacher/course-instances
     *
     * Optional query:
     *  - ?status=draft|active|finished
     */
    public function index(Request $request)
    {
        $user = $request->user(); // dosen yang lagi login

        $query = CourseInstance::with([
            'template:id,code,name',
            'lecturer:id,name,username',
        ])->where('lecturer_id', $user->id);

        // Optional filter status
        $status = $request->query('status');
        if ($status && in_array($status, ['draft', 'active', 'finished'], true)) {
            $query->where('status', $status);
        }

        $instances = $query
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($instances);
    }

    /**
     * Dosen mengubah status kelas (hanya draft <-> active).
     *
     * POST /api/teacher/course-instances/{id}/status
     *
     * Body:
     *  - { "status": "draft" } atau { "status": "active" }
     *
     * Aturan:
     *  - Hanya boleh untuk kelas di mana lecturer_id = dosen login.
     *  - Tidak boleh mengubah kelas yang sudah "finished".
     *  - Tidak boleh set langsung ke "finished" (itu hak Superadmin).
     */
    public function updateStatus(Request $request, int $id)
    {
        $user = $request->user(); // dosen login

        // Pastikan kelas ini memang dia yang ampu
        $instance = CourseInstance::where('id', $id)
            ->where('lecturer_id', $user->id)
            ->first();

        if (! $instance) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan atau Anda bukan dosen pengampu.',
            ], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'in:draft,active'],
        ]);

        $current = $instance->status;
        $next = $data['status'];

        // Kalau kelas sudah finished, tidak boleh diapa-apakan
        if ($current === 'finished') {
            return response()->json([
                'message' => 'Kelas yang sudah finished tidak dapat diubah lagi oleh dosen.',
            ], 422);
        }

        // Kalau status sekarang bukan draft/active, anggap tidak valid buat dosen
        if (! in_array($current, ['draft', 'active'], true)) {
            return response()->json([
                'message' => "Status kelas saat ini ({$current}) tidak dapat diubah oleh dosen.",
            ], 422);
        }

        // Kalau status tidak berubah, balikin apa adanya
        if ($current === $next) {
            $instance->load([
                'template:id,code,name',
                'lecturer:id,name,username',
            ]);

            return response()->json($instance);
        }

        // ✅ Allowed: draft <-> active
        $instance->status = $next;
        $instance->save();

        $instance->load([
            'template:id,code,name',
            'lecturer:id,name,username',
        ]);

        return response()->json($instance);
    }
}
