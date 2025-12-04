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
     * Shortcut ke CourseInstance lewat Section (kalau nanti butuh).
     */
    public function courseInstance()
    {
        return $this->hasOneThrough(
            CourseInstance::class,
            Section::class,
            'id',                 // Foreign key di Section ke Assignment? (bukan, ini ke Assignment via section())
            'id',                 // Foreign key di CourseInstance
            'section_id',         // Local key di Assignment
            'course_instance_id'  // Local key di Section
        );
    }
}
