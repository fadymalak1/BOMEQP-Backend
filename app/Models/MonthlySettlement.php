<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlySettlement extends Model
{
    use HasFactory;

    protected $table = 'monthly_settlements';

    protected $fillable = [
        'settlement_month',
        'acc_id',
        'total_revenue',
        'group_commission_amount',
        'status',
        'request_date',
        'payment_date',
        'payment_method',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'total_revenue' => 'decimal:2',
            'group_commission_amount' => 'decimal:2',
            'request_date' => 'datetime',
            'payment_date' => 'datetime',
        ];
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}

