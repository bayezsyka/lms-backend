<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\CourseInstance;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class GradeController extends Controller
{
    /**
     * Rekap grades per kelas untuk dosen.
     *
     * - Tabel mahasiswa vs assignment + quiz + total.
     */
    public function courseGrades(CourseInstance $courseInstance): JsonResponse
    {
        $this->authorizeCourseInstance($courseInstance);

        $courseInstance->load([
            'template',
            'lecturer',
            'enrollments.student',
            'sections.assignments.submissions',
            'sections.quizzes.attempts',
        ]);

        $students = $courseInstance->enrollments
            ->where('status', 'active')
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->values();

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

        // Map assignment_id => [student_id => score]
        $assignmentScores = [];
        foreach ($assignments as $assignment) {
            foreach ($assignment->submissions as $submission) {
                $studentId = $submission->student_id;
                $assignmentScores[$assignment->id][$studentId] = $submission->score;
            }
        }

        // Map quiz_id => [student_id => best_score]
        $quizScores = [];
        foreach ($quizzes as $quiz) {
            /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\QuizAttempt[] $attempts */
            $attempts = $quiz->attempts;

            foreach ($attempts as $attempt) {
                $studentId = $attempt->student_id;
                $score = $attempt->score ?? 0;

                if (! isset($quizScores[$quiz->id][$studentId])) {
                    $quizScores[$quiz->id][$studentId] = $score;
                } else {
                    $quizScores[$quiz->id][$studentId] = max($quizScores[$quiz->id][$studentId], $score);
                }
            }
        }

        $studentRows = [];

        foreach ($students as $student) {
            $sid = $student->id;

            $perAssignment = [];
            $assignmentTotal = 0;

            foreach ($assignments as $assignment) {
                $score = $assignmentScores[$assignment->id][$sid] ?? null;
                $perAssignment[$assignment->id] = [
                    'score' => $score,
                    'max_score' => $assignment->max_score,
                ];
                $assignmentTotal += $score ?? 0;
            }

            $perQuiz = [];
            $quizTotal = 0;

            foreach ($quizzes as $quiz) {
                $score = $quizScores[$quiz->id][$sid] ?? null;
                $perQuiz[$quiz->id] = [
                    'best_score' => $score,
                    'max_score' => $quiz->max_score,
                ];
                $quizTotal += $score ?? 0;
            }

            $studentRows[] = [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'username' => $student->username,
                    'nim' => $student->nim,
                    'email' => $student->email,
                ],
                'assignments' => $perAssignment,
                'quizzes' => $perQuiz,
                'total_score' => $assignmentTotal + $quizTotal,
                'total_assignment_score' => $assignmentTotal,
                'total_quiz_score' => $quizTotal,
            ];
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
            'assignments' => $assignments->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'max_score' => $assignment->max_score,
                ];
            })->values(),
            'quizzes' => $quizzes->map(function ($quiz) {
                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'max_score' => $quiz->max_score,
                ];
            })->values(),
            'students' => $studentRows,
        ]);
    }

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

        abort(403, 'Anda bukan dosen pengampu kelas ini.');
    }
}
