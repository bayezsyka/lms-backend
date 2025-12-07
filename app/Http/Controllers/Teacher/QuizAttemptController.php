<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\CourseInstance;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class QuizAttemptController extends Controller
{
    /**
     * Dosen melihat daftar attempt quiz per mahasiswa.
     *
     * GET /api/teacher/quizzes/{quiz}/attempts
     *
     * Response yang diharapkan frontend:
     * {
     *   "quiz": { ...detail quiz + section + course_instance + template... },
     *   "attempts": [
     *     {
     *       "id": 1,
     *       "score": 80,
     *       "started_at": "...",
     *       "submitted_at": "...",
     *       "student": {
     *         "id": ...,
     *         "name": "...",
     *         "username": "...",
     *         "nim": "...",
     *         "email": "..."
     *       }
     *     },
     *     ...
     *   ]
     * }
     */
    public function index(Quiz $quiz): JsonResponse
    {
        // load relasi secukupnya buat header halaman
        $quiz->load([
            'section.courseInstance.template',
            'section.courseInstance.lecturer',
        ]);

        $section = $quiz->section;
        if (! $section || ! $section->courseInstance) {
            abort(400, 'Quiz tidak terhubung ke kelas manapun.');
        }

        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstanceAccess($courseInstance);

        // ambil attempt + info mahasiswa
        $attempts = QuizAttempt::with([
                'student:id,name,username,nim,email',
            ])
            ->where('quiz_id', $quiz->id)
            ->orderBy('started_at')
            ->get()
            ->map(function (QuizAttempt $attempt) {
                return [
                    'id' => $attempt->id,
                    'score' => $attempt->score,
                    'started_at' => $attempt->started_at,
                    'submitted_at' => $attempt->submitted_at,
                    'student' => $attempt->student ? [
                        'id' => $attempt->student->id,
                        'name' => $attempt->student->name,
                        'username' => $attempt->student->username,
                        'nim' => $attempt->student->nim,
                        'email' => $attempt->student->email,
                    ] : null,
                ];
            })
            ->values();

        // bentuk payload quiz buat header + editor soal
        $quizPayload = [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'start_time' => $quiz->start_time,
            'end_time' => $quiz->end_time,
            'duration_minutes' => $quiz->duration_minutes,
            'max_score' => $quiz->max_score,
            // kalau lu punya accessor / kolom time_status, silakan pakai; kalau enggak, null aja
            'time_status' => $quiz->time_status ?? null,
            // kolom questions (JSON) dari model Quiz
            'questions' => $quiz->questions,
            'section' => [
                'id' => $section->id,
                'title' => $section->title,
                'course_instance' => [
                    'id' => $courseInstance->id,
                    'class_name' => $courseInstance->class_name,
                    'semester' => $courseInstance->semester,
                    'template' => $courseInstance->template ? [
                        'code' => $courseInstance->template->code,
                        'name' => $courseInstance->template->name,
                    ] : null,
                    'lecturer' => $courseInstance->lecturer ? [
                        'id'   => $courseInstance->lecturer->id,
                        'name' => $courseInstance->lecturer->name,
                    ] : null,
                ],
            ],
        ];

        return response()->json([
            'quiz' => $quizPayload,
            'attempts' => $attempts,
        ]);
    }

    /**
     * Pastikan dosen yang login adalah pengampu kelas ini.
     */
    protected function authorizeCourseInstanceAccess(CourseInstance $courseInstance): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Route ini lewat middleware role:dosen, jadi yang masuk pasti dosen.
        if ((int) $courseInstance->lecturer_id === (int) $user->id) {
            return;
        }

        abort(403, 'Anda tidak berhak mengakses quiz untuk kelas ini.');
    }
}
