<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'section_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'duration_minutes',
        'max_score',
        'questions',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'section_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'max_score' => 'integer',
        'questions' => 'array',
    ];

    /**
     * Section (minggu/topik) tempat quiz ini berada.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Attempt- attempt quiz oleh mahasiswa.
     */
    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
