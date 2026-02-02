<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TrainingCenterPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_center_id',
        'acc_id',
        'purchase_type',
        'item_id',
        'amount',
        'group_commission_percentage',
        'group_commission_amount',
        'transaction_id',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'group_commission_percentage' => 'decimal:2',
            'group_commission_amount' => 'decimal:2',
            'purchased_at' => 'datetime',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function item(): MorphTo
    {
        return $this->morphTo('item');
    }
}

