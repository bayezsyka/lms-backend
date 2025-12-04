<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_template_id',
        'class_name',
        'semester',
        'lecturer_id',
        'status',
        'start_date',
        'end_date',
        'notes',
    ];

    protected $casts = [
        'course_template_id' => 'integer',
        'lecturer_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Template mata kuliah (course_templates).
     */
    public function template()
    {
        return $this->belongsTo(CourseTemplate::class, 'course_template_id');
    }

    /**
     * Dosen pengampu.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /**
     * Enrollment (pivot mahasiswa di kelas ini).
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Mahasiswa yang terdaftar di kelas ini.
     */
    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments', 'course_instance_id', 'student_id')
            ->withPivot(['status', 'enrolled_at', 'dropped_at'])
            ->withTimestamps();
    }

    /**
     * Sections (minggu/topik perkuliahan) di dalam kelas ini.
     * Default diurutkan berdasarkan kolom 'order'.
     */
    public function sections()
    {
        return $this->hasMany(Section::class)
            ->orderBy('order');
    }
}
