<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'course_instance_id',
        'title',
        'order',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'course_instance_id' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Relasi ke CourseInstance (kelas per semester).
     */
    public function courseInstance()
    {
        return $this->belongsTo(CourseInstance::class);
    }

    /**
     * Materi-materi yang ada di section ini.
     */
    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    /**
     * Tugas-tugas (assignments) di section ini.
     */
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Quiz-quiz di section ini.
     */
    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }
}
