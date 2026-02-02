<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StripeConnectLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_type',
        'account_id',
        'account_name',
        'action',
        'status',
        'stripe_connected_account_id',
        'error_message',
        'details',
        'performed_by_admin',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    /**
     * Get the admin who performed the action
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_admin');
    }

    /**
     * Get the account model (ACC, TrainingCenter, or Instructor)
     */
    public function account(): MorphTo
    {
        return $this->morphTo('account', 'account_type', 'account_id');
    }

    /**
     * Scope to filter by account type
     */
    public function scopeForAccountType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope to filter by action
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by status
     */
    public function scopeForStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}

