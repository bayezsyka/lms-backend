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
        // Versi sederhana dulu: tanpa filter, ambil semua kelas.
        $instances = CourseInstance::with(['template', 'lecturer'])
            ->orderBy('id', 'desc')
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

        // Pastikan lecturer adalah user dengan role "dosen"
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

        // PLACEHOLDER: copy struktur default dari template kalau nanti dibutuhkan.

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
        $instance = CourseInstance::with(['template', 'lecturer'])->findOrFail($id);

        return response()->json($instance);
    }

    /**
     * Update informasi umum kelas (tanpa mengatur flow status).
     *
     * PUT /api/admin/course-instances/{id}
     */
    public function update(Request $request, int $id)
    {
        $instance = CourseInstance::findOrFail($id);

        $data = $request->validate([
            'class_name' => ['sometimes', 'required', 'string', 'max:50'],
            'semester' => ['sometimes', 'required', 'string', 'max:50'],
            'lecturer_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
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

        $instance->save();

        $instance->load(['template', 'lecturer']);

        return response()->json($instance);
    }

    /**
     * Ubah status kelas (draft → active → finished).
     *
     * POST /api/admin/course-instances/{id}/status
     */
    public function updateStatus(Request $request, int $id)
    {
        $instance = CourseInstance::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:draft,active,finished'],
        ]);

        $current = $instance->status;
        $next = $data['status'];

        // Jika status tidak berubah, langsung return
        if ($current === $next) {
            $instance->load(['template', 'lecturer']);

            return response()->json($instance);
        }

        $allowedTransitions = [
            'draft' => ['active', 'finished'],
            'active' => ['finished'],
            'finished' => [], // tidak boleh diubah lagi
        ];

        $allowedNext = $allowedTransitions[$current] ?? [];

        if (! in_array($next, $allowedNext, true)) {
            return response()->json([
                'message' => "Perubahan status dari '{$current}' ke '{$next}' tidak diizinkan.",
            ], 422);
        }

        $instance->status = $next;
        $instance->save();

        $instance->load(['template', 'lecturer']);

        return response()->json($instance);
    }

    /**
     * Lihat daftar mahasiswa di sebuah kelas.
     *
     * GET /api/admin/course-instances/{id}/students
     */
    public function students(int $id)
    {
        $instance = CourseInstance::with([
            'template:id,code,name',
            'lecturer:id,name,username',
            'enrollments.student:id,name,username,nim,email,role,status',
        ])->findOrFail($id);

        // Susun response yang rapi
        $students = $instance->enrollments->map(function (Enrollment $enrollment) {
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
            'course' => [
                'id' => $instance->id,
                'class_name' => $instance->class_name,
                'semester' => $instance->semester,
                'status' => $instance->status,
                'template' => [
                    'id' => $instance->template->id,
                    'code' => $instance->template->code,
                    'name' => $instance->template->name,
                ],
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
     * Tambah mahasiswa ke kelas (by user_id atau NIM).
     *
     * POST /api/admin/course-instances/{id}/students
     *
     * Body bisa salah satu:
     *  - { "student_id": 10 }
     *  - { "nim": "221234567" }
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
                'message' => 'Harus mengirim salah satu: student_id atau nim.',
            ], 422);
        }

        // Cari user mahasiswa
        if (isset($data['student_id'])) {
            $studentQuery = User::where('id', $data['student_id']);
        } else {
            $studentQuery = User::where('nim', $data['nim']);
        }

        $student = $studentQuery
            ->where('role', 'mahasiswa')
            ->where('status', 'active')
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'Mahasiswa tidak ditemukan, bukan role "mahasiswa", atau status user tidak aktif.',
            ], 422);
        }

        // Cek apakah sudah pernah enroll sebelumnya
        $enrollment = Enrollment::where('course_instance_id', $instance->id)
            ->where('student_id', $student->id)
            ->first();

        if ($enrollment) {
            if ($enrollment->status === 'active') {
                return response()->json([
                    'message' => 'Mahasiswa sudah ter-enroll di kelas ini.',
                ], 409);
            }

            // Jika sebelumnya dropped, aktifkan kembali
            $enrollment->status = 'active';
            $enrollment->enrolled_at = now();
            $enrollment->dropped_at = null;
            $enrollment->save();
        } else {
            // Buat enrollment baru
            $enrollment = Enrollment::create([
                'course_instance_id' => $instance->id,
                'student_id' => $student->id,
                'status' => 'active',
                'enrolled_at' => now(),
            ]);
        }

        $enrollment->load('student');

        return response()->json([
            'message' => 'Mahasiswa berhasil ditambahkan ke kelas.',
            'enrollment' => [
                'id' => $enrollment->id,
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
     * Drop mahasiswa dari kelas.
     *
     * DELETE /api/admin/course-instances/{id}/students/{studentId}
     *
     * Catatan:
     *  - Kita tidak menghapus row enrollment, hanya set status = dropped dan isi dropped_at.
     */
    public function removeStudent(int $id, int $studentId)
    {
        $instance = CourseInstance::findOrFail($id);

        $enrollment = Enrollment::where('course_instance_id', $instance->id)
            ->where('student_id', $studentId)
            ->first();

        if (! $enrollment) {
            return response()->json([
                'message' => 'Enrollment mahasiswa di kelas ini tidak ditemukan.',
            ], 404);
        }

        if ($enrollment->status === 'dropped') {
            // Sudah dropped, tidak perlu apa-apa
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
