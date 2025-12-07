<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseInstance;
use App\Models\CourseTemplate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;

class CourseInstanceController extends Controller
{
    /**
     * List semua course instance untuk admin.
     *
     * GET /api/admin/course-instances
     */
    public function index(Request $request)
    {
        $query = CourseInstance::query()
            ->with([
                'template:id,code,name',
                'lecturer:id,name,username',
            ]);

        if ($request->filled('semester')) {
            $query->where('semester', $request->query('semester'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $instances = $query
            ->orderBy('semester')
            ->orderBy('course_template_id')
            ->orderBy('class_name')
            ->get();

        return response()->json($instances);
    }

    /**
     * Membuat kelas baru (course_instance) dari template.
     *
     * POST /api/admin/course-instances
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'course_template_id' => ['required', 'integer', 'exists:course_templates,id'],
            'class_name' => ['required', 'string', 'max:50'],
            'semester' => ['required', 'string', 'max:50'],
            'lecturer_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        // Pastikan template ada & aktif
        $template = CourseTemplate::where('id', $data['course_template_id'])
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return response()->json([
                'message' => 'Template mata kuliah tidak ditemukan atau tidak aktif.',
            ], 422);
        }

        // Pastikan lecturer adalah user dengan role dosen
        $lecturer = User::where('id', $data['lecturer_id'])
            ->where('role', 'dosen')
            ->first();

        if (! $lecturer) {
            return response()->json([
                'message' => 'Lecturer harus user dengan role "dosen".',
            ], 422);
        }

        // Buat instance baru
        $instance = CourseInstance::create([
            'course_template_id' => $template->id,
            'class_name' => $data['class_name'],
            'semester' => $data['semester'],
            'lecturer_id' => $lecturer->id,
            'status' => 'draft', // Default ketika baru dibuat
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        // Muat relasi untuk response
        $instance->load(['template', 'lecturer']);

        return response()->json($instance, 201);
    }

    /**
     * Detail satu course instance.
     *
     * GET /api/admin/course-instances/{id}
     */
    public function show(int $id)
    {
        $instance = CourseInstance::with([
            'template:id,code,name',
            'lecturer:id,name,username',
        ])->findOrFail($id);

        return response()->json($instance);
    }

    /**
     * Update informasi kelas (tanpa ubah status).
     *
     * PUT /api/admin/course-instances/{id}
     */
    public function update(Request $request, int $id)
    {
        $instance = CourseInstance::findOrFail($id);

        $data = $request->validate([
            'course_template_id' => ['sometimes', 'integer', 'exists:course_templates,id'],
            'class_name' => ['sometimes', 'string', 'max:50'],
            'semester' => ['sometimes', 'string', 'max:50'],
            'lecturer_id' => ['sometimes', 'integer', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        // Jika ingin ganti dosen, pastikan user tersebut adalah dosen
        if (array_key_exists('lecturer_id', $data)) {
            $lecturer = User::where('id', $data['lecturer_id'])
                ->where('role', 'dosen')
                ->first();

            if (! $lecturer) {
                return response()->json([
                    'message' => 'Lecturer harus user dengan role "dosen".',
                ], 422);
            }

            $instance->lecturer_id = $lecturer->id;
        }

        if (array_key_exists('class_name', $data)) {
            $instance->class_name = $data['class_name'];
        }

        if (array_key_exists('semester', $data)) {
            $instance->semester = $data['semester'];
        }

        if (array_key_exists('start_date', $data)) {
            $instance->start_date = $data['start_date'];
        }

        if (array_key_exists('end_date', $data)) {
            $instance->end_date = $data['end_date'];
        }

        if (array_key_exists('notes', $data)) {
            $instance->notes = $data['notes'];
        }

        // Kalau mau ubah template, pastikan template tersebut aktif
        if (array_key_exists('course_template_id', $data)) {
            $template = CourseTemplate::where('id', $data['course_template_id'])
                ->where('is_active', true)
                ->first();

            if (! $template) {
                return response()->json([
                    'message' => 'Template mata kuliah tidak ditemukan atau tidak aktif.',
                ], 422);
            }

            $instance->course_template_id = $template->id;
        }

        $instance->save();

        $instance->load(['template', 'lecturer']);

        return response()->json($instance);
    }

    /**
     * Update status kelas (draft / active / finished).
     *
     * POST /api/admin/course-instances/{id}/status
     */
    public function updateStatus(Request $request, int $id)
    {
        $instance = CourseInstance::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:draft,active,finished'],
        ]);

        $instance->status = $data['status'];
        $instance->save();

        return response()->json([
            'message' => 'Status kelas berhasil diubah.',
            'status' => $instance->status,
        ]);
    }

    /**
     * Hapus class instance.
     *
     * DELETE /api/admin/course-instances/{id}
     */
    public function destroy(int $id)
    {
        $instance = CourseInstance::findOrFail($id);

        $instance->delete();

        return response()->json([
            'message' => 'Kelas berhasil dihapus.',
        ]);
    }

    /**
     * Lihat daftar mahasiswa di sebuah kelas.
     *
     * GET /api/admin/course-instances/{id}/students
     */
    public function students(int $id)
    {
        // Ambil info kelas + relasi utama
        $instance = CourseInstance::with([
            'template:id,code,name',
            'lecturer:id,name,username',
        ])->findOrFail($id);

        // Ambil SEMUA enrollment (active + dropped) secara eksplisit,
        // supaya yang sudah di-drop tetap muncul di UI dengan status berbeda.
        $enrollments = Enrollment::with([
            'student:id,name,username,nim,email,role,status',
        ])
            ->where('course_instance_id', $instance->id)
            ->orderBy('id')
            ->get();

        $students = $enrollments->map(function (Enrollment $enrollment) {
            return [
                'enrollment_id' => $enrollment->id,
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at,
                'dropped_at' => $enrollment->dropped_at,
                'student' => [
                    'id' => $enrollment->student->id,
                    'name' => $enrollment->student->name,
                    'username' => $enrollment->student->username,
                    'nim' => $enrollment->student->nim,
                    'email' => $enrollment->student->email,
                    'role' => $enrollment->student->role,
                    'user_status' => $enrollment->student->status,
                ],
            ];
        });

        return response()->json([
            'class' => [
                'id' => $instance->id,
                'course_template_id' => $instance->course_template_id,
                'class_name' => $instance->class_name,
                'semester' => $instance->semester,
                'status' => $instance->status,
                'start_date' => $instance->start_date,
                'end_date' => $instance->end_date,
                'notes' => $instance->notes,
                'template' => $instance->template ? [
                    'id' => $instance->template->id,
                    'code' => $instance->template->code,
                    'name' => $instance->template->name,
                ] : null,
                'lecturer' => $instance->lecturer ? [
                    'id' => $instance->lecturer->id,
                    'name' => $instance->lecturer->name,
                    'username' => $instance->lecturer->username,
                ] : null,
            ],
            'students' => $students,
        ]);
    }

    /**
     * Tambah mahasiswa ke kelas (by user_id atau NIM / username).
     *
     * POST /api/admin/course-instances/{id}/students
     */
    public function addStudent(Request $request, int $id)
    {
        $instance = CourseInstance::findOrFail($id);

        $data = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:users,id'],
            'nim' => ['sometimes', 'string'],
        ]);

        if (! isset($data['student_id']) && ! isset($data['nim'])) {
            return response()->json([
                'message' => 'Harus mengirimkan student_id atau nim.',
            ], 422);
        }

        // Cari mahasiswa
        if (isset($data['student_id'])) {
            $student = User::where('id', $data['student_id'])
                ->where('role', 'mahasiswa')
                ->first();
        } else {
            // "nim" di sini artinya NIM ATAU username
            $identifier = $data['nim'];

            $student = User::where('role', 'mahasiswa')
                ->where(function ($q) use ($identifier) {
                    $q->where('nim', $identifier)
                        ->orWhere('username', $identifier);
                })
                ->first();
        }

        if (! $student) {
            return response()->json([
                'message' => 'Mahasiswa tidak ditemukan.',
            ], 404);
        }

        // Pastikan belum ada enrollment aktif di kelas ini
        $enrollment = Enrollment::where('course_instance_id', $instance->id)
            ->where('student_id', $student->id)
            ->first();

        if ($enrollment && $enrollment->status === 'active') {
            return response()->json([
                'message' => 'Mahasiswa sudah terdaftar aktif di kelas ini.',
            ], 422);
        }

        if (! $enrollment) {
            // Buat enrollment baru
            $enrollment = Enrollment::create([
                'course_instance_id' => $instance->id,
                'student_id' => $student->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
        } else {
            // Jika sebelumnya dropped, aktifkan lagi
            $enrollment->status = 'active';
            $enrollment->dropped_at = null;
            $enrollment->save();
        }

        $enrollment->load('student');

        return response()->json([
            'message' => 'Mahasiswa berhasil ditambahkan ke kelas.',
            'enrollment' => [
                'enrollment_id' => $enrollment->id,
                'status' => $enrollment->status,
                'enrolled_at' => $enrollment->enrolled_at,
                'dropped_at' => $enrollment->dropped_at,
                'student' => [
                    'id' => $enrollment->student->id,
                    'name' => $enrollment->student->name,
                    'username' => $enrollment->student->username,
                    'nim' => $enrollment->student->nim,
                    'email' => $enrollment->student->email,
                    'role' => $enrollment->student->role,
                    'user_status' => $enrollment->student->status,
                ],
            ],
        ], 201);
    }

    /**
     * Hapus / drop mahasiswa dari kelas.
     *
     * DELETE /api/admin/course-instances/{id}/students/{studentId}
     */
    public function removeStudent(int $id, int $studentId)
    {
        $instance = CourseInstance::findOrFail($id);

        $enrollment = Enrollment::where('course_instance_id', $instance->id)
            ->where('student_id', $studentId)
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'Mahasiswa tidak ditemukan di kelas ini.',
            ], 404);
        }

        if ($enrollment->status === 'dropped') {
            return response()->json([
                'message' => 'Mahasiswa sudah berstatus dropped di kelas ini.',
            ], 200);
        }

        $enrollment->status = 'dropped';
        $enrollment->dropped_at = now();
        $enrollment->save();

        return response()->json([
            'message' => 'Mahasiswa berhasil di-drop dari kelas.',
        ]);
    }
}
