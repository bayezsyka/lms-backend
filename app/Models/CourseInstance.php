<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseInstance extends Model
{
    use HasFactory;

    protected $table = 'course_instances';

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
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Template mata kuliah (master MK).
     */
    public function template()
    {
        return $this->belongsTo(CourseTemplate::class, 'course_template_id');
    }

    /**
     * Dosen pengampu kelas ini.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /**
     * Enrollment (keikutsertaan mahasiswa) untuk kelas ini.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Mahasiswa yang ter-enroll di kelas ini.
     *
     * Catatan:
     *  - Menggunakan tabel pivot "enrollments"
     *  - Pivot menyimpan status & waktu enroll/drop
     */
    public function students()
    {
        return $this->belongsToMany(User::class, 'enrollments', 'course_instance_id', 'student_id')
            ->withPivot(['status', 'enrolled_at', 'dropped_at'])
            ->withTimestamps();
    }
}
