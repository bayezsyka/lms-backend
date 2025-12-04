<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'section_id',
        'title',
        'description',
        'type',
        'instructions',
        'deadline',
        'max_score',
        'allow_late',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'section_id' => 'integer',
        'deadline' => 'datetime',
        'max_score' => 'integer',
        'allow_late' => 'boolean',
    ];

    /**
     * Section (minggu/topik) tempat tugas ini berada.
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * (Opsional) Akses CourseInstance lewat Section.
     */
    public function courseInstance()
    {
        return $this->hasOneThrough(
            CourseInstance::class,
            Section::class,
            'id',                 // key pada Section
            'id',                 // key pada CourseInstance
            'section_id',         // key pada Assignment
            'course_instance_id'  // key pada Section
        );
    }

    /**
     * Submission-submission mahasiswa untuk assignment ini.
     */
    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
