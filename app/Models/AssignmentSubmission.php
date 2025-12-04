<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'assignment_id',
        'student_id',
        'file_path',
        'url',
        'submitted_at',
        'score',
        'feedback',
        'graded_by',
        'graded_at',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'assignment_id' => 'integer',
        'student_id' => 'integer',
        'submitted_at' => 'datetime',
        'score' => 'integer',
        'graded_by' => 'integer',
        'graded_at' => 'datetime',
    ];

    /**
     * Assignment yang disubmit.
     */
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Mahasiswa yang mengirim submission.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Dosen yang memberikan nilai.
     */
    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
