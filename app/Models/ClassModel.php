<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'course_id',
        'name',
        'created_by',
        'status',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function trainingClasses(): HasMany
    {
        return $this->hasMany(TrainingClass::class, 'class_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'class_id');
    }

    public function trainees(): BelongsToMany
    {
        return $this->belongsToMany(Trainee::class, 'class_trainee', 'class_id', 'trainee_id')
            ->withPivot('status', 'enrolled_at', 'completed_at')
            ->withTimestamps();
    }
}

