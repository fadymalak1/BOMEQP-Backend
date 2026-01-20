<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'action',
        'account_type',
        'target_account_id',
        'target_account_name',
        'ip_address',
        'user_agent',
        'status',
        'error_message',
        'metadata',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'timestamp' => 'datetime',
        ];
    }

    /**
     * Get the admin who performed the action
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Scope to filter by admin
     */
    public function scopeForAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    /**
     * Scope to filter by action
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by account type
     */
    public function scopeForAccountType($query, string $type)
    {
        return $query->where('account_type', $type);
    }
}

