<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $table = 'enrollments';

    protected $fillable = [
        'course_instance_id',
        'student_id',
        'status',
        'enrolled_at',
        'dropped_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'dropped_at' => 'datetime',
    ];

    /**
     * Kelas yang diambil oleh mahasiswa ini.
     */
    public function courseInstance()
    {
        return $this->belongsTo(CourseInstance::class);
    }

    /**
     * Mahasiswa yang ter-enroll.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
