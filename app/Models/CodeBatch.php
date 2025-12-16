<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodeBatch extends Model
{
    use HasFactory;

    protected $table = 'code_batches';

    protected $fillable = [
        'training_center_id',
        'acc_id',
        'quantity',
        'total_amount',
        'payment_method',
        'transaction_id',
        'purchase_date',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'purchase_date' => 'datetime',
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

    public function certificateCodes(): HasMany
    {
        return $this->hasMany(CertificateCode::class, 'batch_id');
    }
}

