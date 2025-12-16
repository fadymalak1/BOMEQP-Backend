<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassCompletion extends Model
{
    use HasFactory;

    protected $table = 'class_completion';

    protected $fillable = [
        'training_class_id',
        'completed_date',
        'completion_rate_percentage',
        'certificates_generated_count',
        'marked_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'completed_date' => 'date',
            'completion_rate_percentage' => 'decimal:2',
        ];
    }

    public function trainingClass(): BelongsTo
    {
        return $this->belongsTo(TrainingClass::class, 'training_class_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}

