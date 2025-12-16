<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_type',
        'payer_type',
        'payer_id',
        'payee_type',
        'payee_id',
        'amount',
        'currency',
        'payment_method',
        'payment_gateway_transaction_id',
        'status',
        'description',
        'reference_id',
        'reference_type',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function payer(): MorphTo
    {
        return $this->morphTo('payer');
    }

    public function payee(): MorphTo
    {
        return $this->morphTo('payee');
    }

    public function commissionLedgers(): HasMany
    {
        return $this->hasMany(CommissionLedger::class, 'transaction_id');
    }
}

