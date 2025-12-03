<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * Atribut yang boleh diisi mass-assignment.
     */
    protected $fillable = [
        'name',
        'username',
        'nim',
        'email',
        'password',
        'role',
        'status',
        'force_password_change',
    ];

    /**
     * Atribut yang disembunyikan ketika di-serialize ke array/JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting atribut.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'force_password_change' => 'boolean',
    ];

    /**
     * Helpers untuk role.
     */
    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isDosen(): bool
    {
        return $this->role === 'dosen';
    }

    public function isMahasiswa(): bool
    {
        return $this->role === 'mahasiswa';
    }

    /**
     * Enrollment yang dimiliki user ini sebagai mahasiswa.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    /**
     * Kelas-kelas yang diambil user ini sebagai mahasiswa.
     */
    public function studentCourseInstances()
    {
        return $this->belongsToMany(CourseInstance::class, 'enrollments', 'student_id', 'course_instance_id')
            ->withPivot(['status', 'enrolled_at', 'dropped_at'])
            ->withTimestamps();
    }

    /**
     * Kelas yang dia ampu sebagai dosen.
     */
    public function teachingCourseInstances()
    {
        return $this->hasMany(CourseInstance::class, 'lecturer_id');
    }
}
