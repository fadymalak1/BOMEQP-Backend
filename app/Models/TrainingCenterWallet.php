<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCenterWallet extends Model
{
    use HasFactory;

    protected $table = 'training_center_wallet';

    protected $fillable = [
        'training_center_id',
        'balance',
        'currency',
        'last_updated',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'last_updated' => 'datetime',
        ];
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }
}

