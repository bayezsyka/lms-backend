<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseInstance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssignmentSubmissionController extends Controller
{
    /**
     * Dosen melihat semua submission pada sebuah assignment.
     */
    public function index(Assignment $assignment): JsonResponse
    {
        $section = $assignment->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $assignment->load([
            'submissions.student',
            'submissions.grader',
        ]);

        $deadline = $assignment->deadline;

        return response()->json([
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'type' => $assignment->type,
                'deadline' => $assignment->deadline,
                'max_score' => $assignment->max_score,
                'allow_late' => $assignment->allow_late,
                'section' => [
                    'id' => $section->id,
                    'title' => $section->title,
                ],
                'course_instance' => [
                    'id' => $courseInstance->id,
                    'class_name' => $courseInstance->class_name,
                    'semester' => $courseInstance->semester,
                ],
            ],
            'submissions' => $assignment->submissions->map(function (AssignmentSubmission $submission) use ($deadline) {
                $isLate = false;
                if ($deadline && $submission->submitted_at) {
                    $isLate = $submission->submitted_at->greaterThan($deadline);
                }

                $fileUrl = null;
                if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
                    $fileUrl = Storage::disk('public')->url($submission->file_path);
                }

                return [
                    'id' => $submission->id,
                    'student' => $submission->student ? [
                        'id' => $submission->student->id,
                        'name' => $submission->student->name,
                        'username' => $submission->student->username,
                        'nim' => $submission->student->nim,
                        'email' => $submission->student->email,
                        'status' => $submission->student->status,
                    ] : null,
                    'submitted_at' => $submission->submitted_at,
                    'is_late' => $isLate,
                    'file_path' => $submission->file_path,
                    'file_url' => $fileUrl,
                    'url' => $submission->url, // kalau type=link
                    'score' => $submission->score,
                    'feedback' => $submission->feedback,
                    'graded_by' => $submission->grader ? [
                        'id' => $submission->grader->id,
                        'name' => $submission->grader->name,
                        'username' => $submission->grader->username,
                    ] : null,
                    'graded_at' => $submission->graded_at,
                    'created_at' => $submission->created_at,
                    'updated_at' => $submission->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Dosen memberi nilai & feedback pada satu submission.
     */
    public function grade(Request $request, AssignmentSubmission $submission): JsonResponse
    {
        $assignment = $submission->assignment;
        $section = $assignment->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeCourseInstance($courseInstance);

        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:' . $assignment->max_score],
            'feedback' => ['nullable', 'string'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $submission->score = $data['score'];
        $submission->feedback = $data['feedback'] ?? null;
        $submission->graded_by = $user->id;
        $submission->graded_at = Carbon::now();
        $submission->save();

        $deadline = $assignment->deadline;
        $isLate = false;
        if ($deadline && $submission->submitted_at) {
            $isLate = $submission->submitted_at->greaterThan($deadline);
        }

        $fileUrl = null;
        if ($submission->file_path && Storage::disk('public')->exists($submission->file_path)) {
            $fileUrl = Storage::disk('public')->url($submission->file_path);
        }

        return response()->json([
            'message' => 'Submission berhasil dinilai.',
            'data' => [
                'id' => $submission->id,
                'assignment' => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'max_score' => $assignment->max_score,
                ],
                'student' => $submission->student ? [
                    'id' => $submission->student->id,
                    'name' => $submission->student->name,
                    'username' => $submission->student->username,
                    'nim' => $submission->student->nim,
                ] : null,
                'submitted_at' => $submission->submitted_at,
                'is_late' => $isLate,
                'file_url' => $fileUrl,
                'url' => $submission->url,
                'score' => $submission->score,
                'feedback' => $submission->feedback,
                'graded_by' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                ],
                'graded_at' => $submission->graded_at,
            ],
        ]);
    }

    /**
     * Cek apakah user (dosen) boleh mengelola submission di kelas ini.
     * - superadmin (kalau nanti mau dipakai) bisa ditambah, tapi di route ini khusus role:dosen.
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

        abort(403, 'Anda tidak berhak mengakses submission untuk kelas ini.');
    }
}
