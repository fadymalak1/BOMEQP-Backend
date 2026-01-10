<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Trainee extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_center_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'id_number',
        'id_image_url',
        'card_image_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            // Add any casts if needed
        ];
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    public function trainingClasses(): BelongsToMany
    {
        return $this->belongsToMany(TrainingClass::class, 'trainee_training_class', 'trainee_id', 'training_class_id')
            ->withPivot('status', 'enrolled_at', 'completed_at')
            ->withTimestamps();
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassModel::class, 'class_trainee', 'trainee_id', 'class_id')
            ->withPivot('status', 'enrolled_at', 'completed_at')
            ->withTimestamps();
    }
}

