<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'user_type',
        'user_type_id',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'stripe_transfer_id',
        'stripe_account_id',
        'status',
        'retry_count',
        'error_message',
        'processed_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'retry_count' => 'integer',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Get the transaction that triggered this transfer
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the user who owns the transfer
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user type model (ACC, TrainingCenter, or Instructor)
     */
    public function userTypeModel(): MorphTo
    {
        return $this->morphTo('user_type', 'user_type', 'user_type_id');
    }

    /**
     * Scope to get pending transfers
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get failed transfers
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get retryable transfers (failed with retry_count < 3)
     */
    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->where('retry_count', '<', 3);
    }

    /**
     * Check if transfer can be retried
     */
    public function canRetry(): bool
    {
        return $this->status === 'failed' && $this->retry_count < 3;
    }

    /**
     * Mark transfer as processing
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark transfer as completed
     */
    public function markAsCompleted(string $stripeTransferId): void
    {
        $this->update([
            'status' => 'completed',
            'stripe_transfer_id' => $stripeTransferId,
            'completed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark transfer as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'failed_at' => now(),
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark transfer as retrying
     */
    public function markAsRetrying(): void
    {
        $this->update([
            'status' => 'retrying',
        ]);
    }
}

