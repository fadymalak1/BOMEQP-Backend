<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_center_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'id_number',
        'cv_url',
        'certificates_json',
        'specializations',
        'status',
        'is_assessor',
    ];

    protected function casts(): array
    {
        return [
            'certificates_json' => 'array',
            'specializations' => 'array',
            'is_assessor' => 'boolean',
        ];
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(InstructorAccAuthorization::class, 'instructor_id');
    }

    public function courseAuthorizations(): HasMany
    {
        return $this->hasMany(InstructorCourseAuthorization::class, 'instructor_id');
    }

    public function trainingClasses(): HasMany
    {
        return $this->hasMany(TrainingClass::class, 'instructor_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'instructor_id');
    }
}

