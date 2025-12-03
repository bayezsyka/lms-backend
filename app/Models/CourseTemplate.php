<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseTemplate extends Model
{
    use HasFactory;

    protected $table = 'course_templates';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sks',
        'semester_recommendation',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relasi: satu template bisa punya banyak kelas per semester.
     */
    public function courseInstances()
    {
        return $this->hasMany(CourseInstance::class, 'course_template_id');
    }
}
