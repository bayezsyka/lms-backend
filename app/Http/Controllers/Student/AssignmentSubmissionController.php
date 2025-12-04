<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssignmentSubmissionController extends Controller
{
    /**
     * Mahasiswa submit / resubmit tugas.
     *
     * - Hanya mahasiswa dengan role=mahasiswa (di-middleware).
     * - Harus ter-enroll aktif di kelas assignment.
     * - Hanya boleh submit kalau assignment masih menerima pengumpulan
     *   (deadline + allow_late).
     */
    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $section = $assignment->section;
        $courseInstance = $section->courseInstance;

        // Cek enrollment mahasiswa di kelas ini (status active)
        $isEnrolled = $courseInstance->enrollments()
            ->where('student_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            return response()->json([
                'message' => 'Anda tidak terdaftar sebagai mahasiswa aktif di kelas ini.',
            ], 403);
        }

        // Cek apakah masih boleh submit (deadline + allow_late)
        $now = Carbon::now();
        $deadline = $assignment->deadline;
        $allowLate = $assignment->allow_late;

        $isPastDeadline = $deadline ? $now->greaterThan($deadline) : false;
        $canSubmitNow = ! $isPastDeadline || ($isPastDeadline && $allowLate);

        if (! $canSubmitNow) {
            return response()->json([
                'message' => 'Tugas ini sudah melewati deadline dan tidak menerima pengumpulan terlambat.',
            ], 422);
        }

        // Validasi input sesuai type assignment
        $rules = [
            'note' => ['nullable', 'string'],
        ];

        if ($assignment->type === 'file') {
            $rules['file'] = ['required', 'file', 'max:51200']; // 50MB
        } elseif ($assignment->type === 'link') {
            $rules['url'] = ['required', 'url', 'max:2048'];
        }

        $data = $request->validate($rules);

        // Cek apakah sudah pernah submit (kita akan overwrite submission lama)
        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('student_id', $user->id)
            ->first();

        $filePath = $submission ? $submission->file_path : null;
        $url = $submission ? $submission->url : null;

        if ($assignment->type === 'file') {
            // Upload file baru
            $uploadedFile = $request->file('file');

            // Hapus file lama jika ada
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }

            $filePath = $uploadedFile->store(
                'assignments/' . $assignment->id . '/student-' . $user->id,
                'public'
            );

            $url = null;
        } elseif ($assignment->type === 'link') {
            $url = $data['url'];

            // Kalau sebelumnya pernah upload file, hapus file lama
            if ($filePath) {
                Storage::disk('public')->delete($filePath);
            }

            $filePath = null;
        }

        if (! $submission) {
            $submission = new AssignmentSubmission();
            $submission->assignment_id = $assignment->id;
            $submission->student_id = $user->id;
        }

        $submission->file_path = $filePath;
        $submission->url = $url;
        $submission->submitted_at = $now;
        // score, feedback, graded_* jangan diutak-atik di sisi mahasiswa
        $submission->save();

        $isLate = $deadline
            ? $submission->submitted_at->greaterThan($deadline)
            : false;

        return response()->json([
            'message' => $submission->wasRecentlyCreated
                ? 'Submission tugas berhasil dibuat.'
                : 'Submission tugas berhasil diperbarui.',
            'data' => [
                'id' => $submission->id,
                'assignment_id' => $submission->assignment_id,
                'student_id' => $submission->student_id,
                'file_path' => $submission->file_path,
                'url' => $submission->url,
                'submitted_at' => $submission->submitted_at,
                'is_late' => $isLate,
            ],
        ], $submission->wasRecentlyCreated ? 201 : 200);
    }
}
