<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GradeController extends Controller
{
    /**
     * Grades per kelas untuk mahasiswa (hanya dirinya sendiri).
     */
    public function courseGrades(CourseInstance $courseInstance): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Cek enrollment mahasiswa
        $isEnrolled = $courseInstance->enrollments()
            ->where('student_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            return response()->json([
                'message' => 'Anda tidak terdaftar di kelas ini.',
            ], 403);
        }

        $courseInstance->load([
            'template',
            'lecturer',
            'sections.assignments.submissions',
            'sections.quizzes.attempts',
        ]);

        $assignments = $courseInstance->sections
            ->flatMap(function ($section) {
                return $section->assignments;
            })
            ->values();

        $quizzes = $courseInstance->sections
            ->flatMap(function ($section) {
                return $section->quizzes;
            })
            ->values();

        $sid = $user->id;

        $assignmentRows = [];
        $assignmentTotal = 0;

        foreach ($assignments as $assignment) {
            $submission = $assignment->submissions
                ->firstWhere('student_id', $sid);

            $score = $submission ? $submission->score : null;

            $assignmentRows[] = [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'max_score' => $assignment->max_score,
                'score' => $score,
            ];

            $assignmentTotal += $score ?? 0;
        }

        $quizRows = [];
        $quizTotal = 0;

        foreach ($quizzes as $quiz) {
            $attempts = $quiz->attempts
                ->where('student_id', $sid);

            $bestScore = $attempts
                ->pluck('score')
                ->filter(function ($s) {
                    return $s !== null;
                })
                ->max();

            $quizRows[] = [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'max_score' => $quiz->max_score,
                'best_score' => $bestScore,
                'attempt_count' => $attempts->count(),
            ];

            $quizTotal += $bestScore ?? 0;
        }

        return response()->json([
            'course' => [
                'id' => $courseInstance->id,
                'class_name' => $courseInstance->class_name,
                'semester' => $courseInstance->semester,
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
            'assignments' => $assignmentRows,
            'quizzes' => $quizRows,
            'total_score' => $assignmentTotal + $quizTotal,
            'total_assignment_score' => $assignmentTotal,
            'total_quiz_score' => $quizTotal,
        ]);
    }
}
