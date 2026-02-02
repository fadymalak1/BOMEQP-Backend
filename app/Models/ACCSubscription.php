<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ACCSubscription extends Model
{
    use HasFactory;

    protected $table = 'acc_subscriptions';

    protected $fillable = [
        'acc_id',
        'subscription_start_date',
        'subscription_end_date',
        'renewal_date',
        'amount',
        'payment_status',
        'payment_date',
        'payment_method',
        'transaction_id',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'subscription_start_date' => 'date',
            'subscription_end_date' => 'date',
            'renewal_date' => 'date',
            'amount' => 'decimal:2',
            'payment_date' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }
}

