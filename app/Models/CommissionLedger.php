<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionLedger extends Model
{
    use HasFactory;

    protected $table = 'commission_ledger';

    protected $fillable = [
        'transaction_id',
        'acc_id',
        'training_center_id',
        'instructor_id',
        'group_commission_amount',
        'group_commission_percentage',
        'acc_commission_amount',
        'acc_commission_percentage',
        'settlement_status',
        'settlement_date',
    ];

    protected function casts(): array
    {
        return [
            'group_commission_amount' => 'decimal:2',
            'group_commission_percentage' => 'decimal:2',
            'acc_commission_amount' => 'decimal:2',
            'acc_commission_percentage' => 'decimal:2',
            'settlement_date' => 'date',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }
}

