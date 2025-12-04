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
     */
    public function index(Quiz $quiz): JsonResponse
    {
        $section = $quiz->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $quiz->load([
            'attempts.student',
        ]);

        // Kelompokkan attempt per mahasiswa
        $grouped = $quiz->attempts
            ->sortByDesc('attempt_number')
            ->groupBy('student_id');

        $students = $grouped->map(function ($attempts, $studentId) {
            /** @var \Illuminate\Support\Collection|\App\Models\QuizAttempt[] $attempts */
            $first = $attempts->first();

            $student = $first->student;

            $bestScore = $attempts
                ->pluck('score')
                ->filter(function ($s) {
                    return $s !== null;
                })
                ->max();

            return [
                'student' => $student ? [
                    'id' => $student->id,
                    'name' => $student->name,
                    'username' => $student->username,
                    'nim' => $student->nim,
                    'email' => $student->email,
                ] : null,
                'attempts' => $attempts->map(function (QuizAttempt $attempt) {
                    return [
                        'id' => $attempt->id,
                        'attempt_number' => $attempt->attempt_number,
                        'score' => $attempt->score,
                        'started_at' => $attempt->started_at,
                        'finished_at' => $attempt->finished_at,
                    ];
                })->values(),
                'best_score' => $bestScore,
            ];
        })->values();

        return response()->json([
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'max_score' => $quiz->max_score,
                'start_time' => $quiz->start_time,
                'end_time' => $quiz->end_time,
                'duration_minutes' => $quiz->duration_minutes,
            ],
            'students' => $students,
        ]);
    }

    /**
     * Cek dosen pengampu.
     */
    protected function authorizeCourseInstance(CourseInstance $courseInstance): void
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->role === 'dosen' && (int) $courseInstance->lecturer_id === (int) $user->id) {
            return;
        }

        abort(403, 'Anda tidak berhak mengakses quiz untuk kelas ini.');
    }
}
