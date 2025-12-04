<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CourseInstance;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizAttemptController extends Controller
{
    /**
     * Mahasiswa memulai quiz.
     *
     * - Cek role via middleware (mahasiswa)
     * - Cek enrollment di kelas
     * - Cek window start_time / end_time
     * - Cek kalau sudah ada attempt yang belum selesai → kembalikan attempt itu (resume)
     */
    public function start(Quiz $quiz): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $section = $quiz->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeEnrollment($courseInstance, $user->id);

        $now = Carbon::now();
        $start = $quiz->start_time;
        $end = $quiz->end_time;

        if ($start && $now->lt($start)) {
            return response()->json([
                'message' => 'Quiz ini belum dibuka.',
            ], 422);
        }

        if ($end && $now->gt($end)) {
            return response()->json([
                'message' => 'Quiz ini sudah ditutup.',
            ], 422);
        }

        // Cek unfinished attempt untuk quiz ini
        $existing = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $user->id)
            ->whereNull('finished_at')
            ->first();

        if ($existing) {
            $deadline = $this->computeAttemptDeadline($quiz, $existing);

            return response()->json([
                'message' => 'Quiz telah dimulai sebelumnya. Melanjutkan attempt yang belum selesai.',
                'data' => [
                    'attempt_id' => $existing->id,
                    'quiz_id' => $quiz->id,
                    'student_id' => $user->id,
                    'attempt_number' => $existing->attempt_number,
                    'started_at' => $existing->started_at,
                    'deadline_at' => $deadline,
                ],
            ]);
        }

        // Hitung nomor attempt berikutnya
        $lastAttemptNumber = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $user->id)
            ->max('attempt_number') ?? 0;

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $user->id,
            'attempt_number' => $lastAttemptNumber + 1,
            'answers' => null,
            'score' => null,
            'started_at' => $now,
            'finished_at' => null,
        ]);

        $deadline = $this->computeAttemptDeadline($quiz, $attempt);

        return response()->json([
            'message' => 'Quiz berhasil dimulai.',
            'data' => [
                'attempt_id' => $attempt->id,
                'quiz_id' => $quiz->id,
                'student_id' => $user->id,
                'attempt_number' => $attempt->attempt_number,
                'started_at' => $attempt->started_at,
                'deadline_at' => $deadline,
            ],
        ], 201);
    }

    /**
     * Mahasiswa mengirim jawaban quiz.
     *
     * Input:
     * - answers: array of { question_id, answer }
     *
     * Hitung skor minimal untuk MCQ berdasarkan quiz->questions.
     */
    public function submit(Request $request, QuizAttempt $attempt): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Pastikan attempt milik user ini
        if ((int) $attempt->student_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Attempt ini bukan milik Anda.',
            ], 403);
        }

        $quiz = $attempt->quiz;
        $section = $quiz->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeEnrollment($courseInstance, $user->id);

        if ($attempt->finished_at) {
            return response()->json([
                'message' => 'Attempt ini sudah diselesaikan sebelumnya.',
            ], 422);
        }

        $data = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $now = Carbon::now();

        // Cek window quiz (start / end time)
        $start = $quiz->start_time;
        $end = $quiz->end_time;

        if ($start && $now->lt($start)) {
            return response()->json([
                'message' => 'Quiz ini belum dibuka.',
            ], 422);
        }

        if ($end && $now->gt($end)) {
            return response()->json([
                'message' => 'Quiz ini sudah ditutup.',
            ], 422);
        }

        // Cek durasi attempt (duration_minutes sejak started_at)
        if ($quiz->duration_minutes && $attempt->started_at) {
            $deadline = $attempt->started_at->copy()->addMinutes($quiz->duration_minutes);
            if ($now->gt($deadline)) {
                return response()->json([
                    'message' => 'Waktu pengerjaan quiz ini sudah habis.',
                ], 422);
            }
        }

        $answersInput = $data['answers'];

        // Normalisasi jawaban mahasiswa jadi map [question_id => answer]
        $answerMap = [];
        foreach ($answersInput as $item) {
            if (is_array($item) && isset($item['question_id'])) {
                $answerMap[$item['question_id']] = $item['answer'] ?? null;
            }
        }

        // Hitung skor MCQ berdasarkan quiz->questions
        $questions = $quiz->questions ?? [];
        $totalScore = 0;

        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $qId = $question['id'] ?? null;
            $type = $question['type'] ?? null;
            $correctAnswer = $question['answer'] ?? null;
            $points = $question['points'] ?? 0;

            if (! $qId || $type !== 'mcq') {
                // untuk sekarang, hanya MCQ yang otomatis dinilai
                continue;
            }

            $studentAnswer = $answerMap[$qId] ?? null;
            if ($studentAnswer !== null && $studentAnswer === $correctAnswer) {
                $totalScore += (int) $points;
            }
        }

        // Jangan sampai melebihi max_score quiz
        if ($quiz->max_score !== null) {
            $totalScore = min($totalScore, $quiz->max_score);
        }

        // Simpan jawaban & skor
        $attempt->answers = $answersInput;
        $attempt->score = $totalScore;
        $attempt->finished_at = $now;
        $attempt->save();

        return response()->json([
            'message' => 'Jawaban quiz berhasil dikirim.',
            'data' => [
                'attempt_id' => $attempt->id,
                'quiz_id' => $quiz->id,
                'student_id' => $user->id,
                'attempt_number' => $attempt->attempt_number,
                'score' => $attempt->score,
                'max_score' => $quiz->max_score,
                'started_at' => $attempt->started_at,
                'finished_at' => $attempt->finished_at,
            ],
        ]);
    }

    /**
     * Mahasiswa melihat semua attempt miliknya untuk satu quiz.
     */
    public function myAttempts(Quiz $quiz): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $section = $quiz->section;
        $courseInstance = $section->courseInstance;

        $this->authorizeEnrollment($courseInstance, $user->id);

        $attempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $user->id)
            ->orderByDesc('attempt_number')
            ->get();

        return response()->json([
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'max_score' => $quiz->max_score,
            ],
            'attempts' => $attempts->map(function (QuizAttempt $attempt) {
                return [
                    'id' => $attempt->id,
                    'attempt_number' => $attempt->attempt_number,
                    'score' => $attempt->score,
                    'started_at' => $attempt->started_at,
                    'finished_at' => $attempt->finished_at,
                ];
            }),
        ]);
    }

    /**
     * Hitung deadline attempt berdasarkan duration_minutes.
     */
    protected function computeAttemptDeadline(Quiz $quiz, QuizAttempt $attempt): ?Carbon
    {
        if (! $quiz->duration_minutes || ! $attempt->started_at) {
            return null;
        }

        return $attempt->started_at->copy()->addMinutes($quiz->duration_minutes);
    }

    /**
     * Cek apakah student ter-enroll aktif di courseInstance.
     */
    protected function authorizeEnrollment(CourseInstance $courseInstance, int $studentId): void
    {
        $isEnrolled = $courseInstance->enrollments()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            abort(403, 'Anda tidak terdaftar sebagai mahasiswa aktif di kelas ini.');
        }
    }
}
