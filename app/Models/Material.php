<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
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
        'file_path',
        'url',
        'subject',
    ];

    /**
     * Casts.
     */
    protected $casts = [
        'section_id' => 'integer',
    ];

    /**
     * Relasi ke Section (minggu/topik perkuliahan).
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }
}
