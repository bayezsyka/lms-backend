<?php

namespace App\Http\Controllers;

use App\Models\CourseInstance;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SectionController extends Controller
{
    /**
     * List semua section di sebuah kelas.
     */
    public function index(CourseInstance $courseInstance): JsonResponse
    {
        $this->authorizeCourseInstance($courseInstance);

        $courseInstance->load(['template', 'lecturer', 'sections']);

        return response()->json([
            'course' => [
                'id' => $courseInstance->id,
                'class_name' => $courseInstance->class_name,
                'semester' => $courseInstance->semester,
                'status' => $courseInstance->status,
                'template' => $courseInstance->template ? [
                    'id' => $courseInstance->template->id,
                    'code' => $courseInstance->template->code,
                    'name' => $courseInstance->template->name,
                ] : null,
                'lecturer' => $courseInstance->lecturer ? [
                    'id' => $courseInstance->lecturer->id,
                    'name' => $courseInstance->lecturer->name,
                    'username' => $courseInstance->lecturer->username,
                ] : null,
            ],
            'sections' => $courseInstance->sections->map(function (Section $section) {
                return [
                    'id' => $section->id,
                    'title' => $section->title,
                    'order' => $section->order,
                    'created_at' => $section->created_at,
                    'updated_at' => $section->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Tambah section baru di sebuah kelas.
     */
    public function store(Request $request, CourseInstance $courseInstance): JsonResponse
    {
        $this->authorizeCourseInstance($courseInstance);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = $data['order'] ?? null;

        // Jika order dikirim dan sudah terpakai → error 422
        if ($order !== null && $courseInstance->sections()->where('order', $order)->exists()) {
            return response()->json([
                'message' => 'Order sudah digunakan oleh section lain pada kelas ini.',
                'errors' => [
                    'order' => ['Order sudah digunakan oleh section lain pada kelas ini.'],
                ],
            ], 422);
        }

        // Jika order tidak dikirim → auto next number
        if ($order === null) {
            $maxOrder = $courseInstance->sections()->max('order') ?? 0;
            $order = $maxOrder + 1;
        }

        $section = Section::create([
            'course_instance_id' => $courseInstance->id,
            'title' => $data['title'],
            'order' => $order,
        ]);

        return response()->json([
            'message' => 'Section berhasil dibuat.',
            'data' => $section,
        ], 201);
    }

    /**
     * Update judul / order section.
     */
    public function update(Request $request, Section $section): JsonResponse
    {
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        // Handle perubahan order jika dikirim
        if (array_key_exists('order', $data)) {
            $newOrder = $data['order'];

            // Jika order di-set null → auto next number
            if ($newOrder === null) {
                $maxOrder = $courseInstance->sections()
                    ->where('id', '!=', $section->id)
                    ->max('order') ?? 0;

                $newOrder = $maxOrder + 1;
            } else {
                // Jika order baru bentrok dengan section lain → error 422
                $exists = $courseInstance->sections()
                    ->where('id', '!=', $section->id)
                    ->where('order', $newOrder)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'message' => 'Order sudah digunakan oleh section lain pada kelas ini.',
                        'errors' => [
                            'order' => ['Order sudah digunakan oleh section lain pada kelas ini.'],
                        ],
                    ], 422);
                }
            }

            $section->order = $newOrder;
        }

        if (array_key_exists('title', $data)) {
            $section->title = $data['title'];
        }

        $section->save();

        return response()->json([
            'message' => 'Section berhasil diperbarui.',
            'data' => $section,
        ]);
    }

    /**
     * Hapus section.
     */
    public function destroy(Section $section): JsonResponse
    {
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $section->delete();

        return response()->json([
            'message' => 'Section berhasil dihapus.',
        ], 200);
    }

    /**
     * Cek apakah user boleh mengakses kelas ini.
     * - superadmin: boleh semua kelas
     * - dosen: hanya kelas yang dia ampu
     */
    protected function authorizeCourseInstance(CourseInstance $courseInstance): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->role === 'superadmin') {
            return;
        }

        if ($user->role === 'dosen' && (int) $courseInstance->lecturer_id === (int) $user->id) {
            return;
        }

        abort(403, 'Anda tidak berhak mengelola section pada kelas ini.');
    }
}
