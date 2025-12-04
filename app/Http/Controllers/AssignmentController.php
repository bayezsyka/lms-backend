<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\CourseInstance;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    /**
     * List semua assignment di sebuah section.
     */
    public function index(Section $section): JsonResponse
    {
        $courseInstance = $section->courseInstance;
        $this->authorizeCourseInstance($courseInstance);

        $section->load('assignments');

        $now = Carbon::now();

        return response()->json([
            'section' => [
                'id' => $section->id,
                'title' => $section->title,
                'order' => $section->order,
                'course_instance' => [
                    'id' => $courseInstance->id,
                    'class_name' => $courseInstance->class_name,
                    'semester' => $courseInstance->semester,
                ],
            ],
            'assignments' => $section->assignments->map(function (Assignment $assignment) use ($now) {
                [$isPastDeadline, $canSubmitNow] = $this->computeDeadlineFlags($assignment, $now);

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'type' => $assignment->type,
                    'instructions' => $assignment->instructions,
                    'deadline' => $assignment->deadline,
                    'max_score' => $assignment->max_score,
                    'allow_late' => $assignment->allow_late,
                    'is_past_deadline' => $isPastDeadline,
                    'can_submit_now' => $canSubmitNow,
                    'created_at' => $assignment->created_at,
                    'updated_at' => $assignment->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Buat assignment baru di section.
     */
    public function store(Request $request, Section $section): JsonResponse
    {
        $courseInstance = $section->courseInstance;
        $this->authorizeCourseInstance($courseInstance);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:file,link'],
            'instructions' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'], // format bebas, Laravel parse
            'max_score' => ['nullable', 'integer', 'min:1'],
            'allow_late' => ['nullable', 'boolean'],
        ]);

        $assignment = Assignment::create([
            'section_id' => $section->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'instructions' => $data['instructions'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'max_score' => $data['max_score'] ?? 100,
            'allow_late' => $data['allow_late'] ?? false,
        ]);

        [$isPastDeadline, $canSubmitNow] = $this->computeDeadlineFlags($assignment, Carbon::now());

        return response()->json([
            'message' => 'Assignment berhasil dibuat.',
            'data' => [
                'id' => $assignment->id,
                'section_id' => $assignment->section_id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'type' => $assignment->type,
                'instructions' => $assignment->instructions,
                'deadline' => $assignment->deadline,
                'max_score' => $assignment->max_score,
                'allow_late' => $assignment->allow_late,
                'is_past_deadline' => $isPastDeadline,
                'can_submit_now' => $canSubmitNow,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ],
        ], 201);
    }

    /**
     * Detail satu assignment.
     */
    public function show(Assignment $assignment): JsonResponse
    {
        $section = $assignment->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        [$isPastDeadline, $canSubmitNow] = $this->computeDeadlineFlags($assignment, Carbon::now());

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'section_id' => $assignment->section_id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'type' => $assignment->type,
                'instructions' => $assignment->instructions,
                'deadline' => $assignment->deadline,
                'max_score' => $assignment->max_score,
                'allow_late' => $assignment->allow_late,
                'is_past_deadline' => $isPastDeadline,
                'can_submit_now' => $canSubmitNow,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ],
            'section' => [
                'id' => $section->id,
                'title' => $section->title,
                'order' => $section->order,
            ],
            'course_instance' => [
                'id' => $courseInstance->id,
                'class_name' => $courseInstance->class_name,
                'semester' => $courseInstance->semester,
            ],
        ]);
    }

    /**
     * Update assignment.
     */
    public function update(Request $request, Assignment $assignment): JsonResponse
    {
        $section = $assignment->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'required', 'in:file,link'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'max_score' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'allow_late' => ['sometimes', 'nullable', 'boolean'],
        ]);

        if (array_key_exists('title', $data)) {
            $assignment->title = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $assignment->description = $data['description'];
        }
        if (array_key_exists('type', $data)) {
            $assignment->type = $data['type'];
        }
        if (array_key_exists('instructions', $data)) {
            $assignment->instructions = $data['instructions'];
        }
        if (array_key_exists('deadline', $data)) {
            $assignment->deadline = $data['deadline'];
        }
        if (array_key_exists('max_score', $data)) {
            $assignment->max_score = $data['max_score'] ?? 100;
        }
        if (array_key_exists('allow_late', $data)) {
            $assignment->allow_late = $data['allow_late'] ?? false;
        }

        $assignment->save();

        [$isPastDeadline, $canSubmitNow] = $this->computeDeadlineFlags($assignment, Carbon::now());

        return response()->json([
            'message' => 'Assignment berhasil diperbarui.',
            'data' => [
                'id' => $assignment->id,
                'section_id' => $assignment->section_id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'type' => $assignment->type,
                'instructions' => $assignment->instructions,
                'deadline' => $assignment->deadline,
                'max_score' => $assignment->max_score,
                'allow_late' => $assignment->allow_late,
                'is_past_deadline' => $isPastDeadline,
                'can_submit_now' => $canSubmitNow,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ],
        ]);
    }

    /**
     * Hapus assignment.
     */
    public function destroy(Assignment $assignment): JsonResponse
    {
        $section = $assignment->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $assignment->delete();

        return response()->json([
            'message' => 'Assignment berhasil dihapus.',
        ], 200);
    }

    /**
     * Hitung flag deadline & allow_late untuk assignment pada waktu tertentu.
     *
     * @return array{0: bool, 1: bool} [isPastDeadline, canSubmitNow]
     */
    protected function computeDeadlineFlags(Assignment $assignment, Carbon $now): array
    {
        $deadline = $assignment->deadline;

        if (! $deadline) {
            // Tidak ada deadline → tidak pernah lewat, selalu boleh submit
            return [false, true];
        }

        $isPastDeadline = $now->greaterThan($deadline);
        $canSubmitNow = ! $isPastDeadline || ($isPastDeadline && $assignment->allow_late);

        return [$isPastDeadline, $canSubmitNow];
    }

    /**
     * Cek apakah user boleh mengelola assignment di kelas ini.
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

        abort(403, 'Anda tidak berhak mengelola assignment pada kelas ini.');
    }
}
