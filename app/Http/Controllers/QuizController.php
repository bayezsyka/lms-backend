<?php

namespace App\Http\Controllers;

use App\Models\CourseInstance;
use App\Models\Quiz;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    /**
     * List semua quiz di sebuah section.
     */
    public function index(Section $section): JsonResponse
    {
        $courseInstance = $section->courseInstance;
        $this->authorizeCourseInstance($courseInstance);

        $section->load('quizzes');

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
            'quizzes' => $section->quizzes->map(function (Quiz $quiz) use ($now) {
                $status = $this->computeTimeStatus($quiz, $now);

                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'start_time' => $quiz->start_time,
                    'end_time' => $quiz->end_time,
                    'duration_minutes' => $quiz->duration_minutes,
                    'max_score' => $quiz->max_score,
                    'time_status' => $status,
                    'created_at' => $quiz->created_at,
                    'updated_at' => $quiz->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Buat quiz baru di section.
     */
    public function store(Request $request, Section $section): JsonResponse
    {
        $courseInstance = $section->courseInstance;
        $this->authorizeCourseInstance($courseInstance);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'max_score' => ['nullable', 'integer', 'min:1'],
            'questions' => ['nullable', 'array'],
        ]);

        // Validasi manual: jika keduanya ada, end_time tidak boleh sebelum start_time
        $start = isset($data['start_time']) ? Carbon::parse($data['start_time']) : null;
        $end = isset($data['end_time']) ? Carbon::parse($data['end_time']) : null;

        if ($start && $end && $end->lessThan($start)) {
            return response()->json([
                'message' => 'end_time tidak boleh lebih awal dari start_time.',
                'errors' => [
                    'end_time' => ['end_time harus >= start_time.'],
                ],
            ], 422);
        }

        $quiz = Quiz::create([
            'section_id' => $section->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'max_score' => $data['max_score'] ?? 100,
            'questions' => $data['questions'] ?? null,
        ]);

        $status = $this->computeTimeStatus($quiz, Carbon::now());

        return response()->json([
            'message' => 'Quiz berhasil dibuat.',
            'data' => [
                'id' => $quiz->id,
                'section_id' => $quiz->section_id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'start_time' => $quiz->start_time,
                'end_time' => $quiz->end_time,
                'duration_minutes' => $quiz->duration_minutes,
                'max_score' => $quiz->max_score,
                'questions' => $quiz->questions,
                'time_status' => $status,
                'created_at' => $quiz->created_at,
                'updated_at' => $quiz->updated_at,
            ],
        ], 201);
    }

    /**
     * Detail satu quiz.
     */
    public function show(Quiz $quiz): JsonResponse
    {
        $section = $quiz->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $status = $this->computeTimeStatus($quiz, Carbon::now());

        return response()->json([
            'quiz' => [
                'id' => $quiz->id,
                'section_id' => $quiz->section_id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'start_time' => $quiz->start_time,
                'end_time' => $quiz->end_time,
                'duration_minutes' => $quiz->duration_minutes,
                'max_score' => $quiz->max_score,
                'questions' => $quiz->questions,
                'time_status' => $status,
                'created_at' => $quiz->created_at,
                'updated_at' => $quiz->updated_at,
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
     * Update quiz.
     */
    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $section = $quiz->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'start_time' => ['sometimes', 'nullable', 'date'],
            'end_time' => ['sometimes', 'nullable', 'date'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_score' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'questions' => ['sometimes', 'nullable', 'array'],
        ]);

        // Hitung calon start & end baru untuk validasi
        $newStart = array_key_exists('start_time', $data)
            ? ($data['start_time'] ? Carbon::parse($data['start_time']) : null)
            : $quiz->start_time;

        $newEnd = array_key_exists('end_time', $data)
            ? ($data['end_time'] ? Carbon::parse($data['end_time']) : null)
            : $quiz->end_time;

        if ($newStart && $newEnd && $newEnd->lessThan($newStart)) {
            return response()->json([
                'message' => 'end_time tidak boleh lebih awal dari start_time.',
                'errors' => [
                    'end_time' => ['end_time harus >= start_time.'],
                ],
            ], 422);
        }

        if (array_key_exists('title', $data)) {
            $quiz->title = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $quiz->description = $data['description'];
        }
        if (array_key_exists('start_time', $data)) {
            $quiz->start_time = $newStart;
        }
        if (array_key_exists('end_time', $data)) {
            $quiz->end_time = $newEnd;
        }
        if (array_key_exists('duration_minutes', $data)) {
            $quiz->duration_minutes = $data['duration_minutes'] ?? null;
        }
        if (array_key_exists('max_score', $data)) {
            $quiz->max_score = $data['max_score'] ?? 100;
        }
        if (array_key_exists('questions', $data)) {
            $quiz->questions = $data['questions'];
        }

        $quiz->save();

        $status = $this->computeTimeStatus($quiz, Carbon::now());

        return response()->json([
            'message' => 'Quiz berhasil diperbarui.',
            'data' => [
                'id' => $quiz->id,
                'section_id' => $quiz->section_id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'start_time' => $quiz->start_time,
                'end_time' => $quiz->end_time,
                'duration_minutes' => $quiz->duration_minutes,
                'max_score' => $quiz->max_score,
                'questions' => $quiz->questions,
                'time_status' => $status,
                'created_at' => $quiz->created_at,
                'updated_at' => $quiz->updated_at,
            ],
        ]);
    }

    /**
     * Hapus quiz.
     */
    public function destroy(Quiz $quiz): JsonResponse
    {
        $section = $quiz->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $quiz->delete();

        return response()->json([
            'message' => 'Quiz berhasil dihapus.',
        ], 200);
    }

    /**
     * Hitung status waktu quiz berdasarkan start/end dan waktu sekarang.
     *
     * @return array{
     *     is_future: bool,
     *     is_ongoing: bool,
     *     is_finished: bool,
     *     can_attempt_now: bool
     * }
     */
    protected function computeTimeStatus(Quiz $quiz, Carbon $now): array
    {
        $start = $quiz->start_time;
        $end = $quiz->end_time;

        $isFuture = false;
        $isOngoing = false;
        $isFinished = false;

        if ($start && $now->lt($start)) {
            $isFuture = true;
        }

        if ($end && $now->gt($end)) {
            $isFinished = true;
        }

        if (! $isFuture && ! $isFinished) {
            // Di antara start dan end, atau salah satu/both null → anggap ongoing
            $isOngoing = true;
        }

        // can_attempt_now = hanya kalau ongoing
        $canAttemptNow = $isOngoing;

        return [
            'is_future' => $isFuture,
            'is_ongoing' => $isOngoing,
            'is_finished' => $isFinished,
            'can_attempt_now' => $canAttemptNow,
        ];
    }

    /**
     * Cek apakah user boleh mengelola quiz di kelas ini.
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

        abort(403, 'Anda tidak berhak mengelola quiz pada kelas ini.');
    }
}
