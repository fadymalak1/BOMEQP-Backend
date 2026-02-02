<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCenterAccAuthorization extends Model
{
    use HasFactory;

    protected $table = 'training_center_acc_authorization';

    protected $fillable = [
        'training_center_id',
        'acc_id',
        'request_date',
        'status',
        'group_commission_percentage',
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
            'group_commission_percentage' => 'decimal:2',
            'documents_json' => 'array',
        ];
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

