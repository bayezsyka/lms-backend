<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'quiz_id',
        'student_id',
        'attempt_number',
        'answers',
        'score',
        'started_at',
        'finished_at',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'quiz_id' => 'integer',
        'student_id' => 'integer',
        'attempt_number' => 'integer',
        'answers' => 'array',      // meskipun kolom longText, Laravel tetap bisa cast ke array
        'score' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Quiz yang dikerjakan.
     */
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Mahasiswa yang mengerjakan quiz.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
