<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingClass extends Model
{
    use HasFactory;

    protected $table = 'training_classes';

    protected $fillable = [
        'training_center_id',
        'course_id',
        'class_id',
        'instructor_id',
        'start_date',
        'end_date',
        'schedule_json',
        'enrolled_count',
        'status',
        'location',
        'location_details',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'schedule_json' => 'array',
        ];
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function classModel(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    public function completion(): HasOne
    {
        return $this->hasOne(ClassCompletion::class, 'training_class_id');
    }

    public function trainees(): BelongsToMany
    {
        return $this->belongsToMany(Trainee::class, 'trainee_training_class', 'training_class_id', 'trainee_id')
            ->withPivot('status', 'enrolled_at', 'completed_at')
            ->withTimestamps();
    }
}

