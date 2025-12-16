<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstructorAccAuthorization extends Model
{
    use HasFactory;

    protected $table = 'instructor_acc_authorization';

    protected $fillable = [
        'instructor_id',
        'acc_id',
        'training_center_id',
        'request_date',
        'status',
        'commission_percentage',
        'rejection_reason',
        'return_comment',
        'reviewed_by',
        'reviewed_at',
        'documents_json',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'datetime',
            'reviewed_at' => 'datetime',
            'commission_percentage' => 'decimal:2',
            'documents_json' => 'array',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

