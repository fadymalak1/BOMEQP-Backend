<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripeSetting extends Model
{
    use HasFactory;

    protected $table = 'stripe_settings';

    protected $fillable = [
        'environment',
        'publishable_key',
        'secret_key',
        'webhook_secret',
        'is_active',
        'description',
    ];

    protected $hidden = [
        'secret_key',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the active Stripe settings
     */
    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Get settings for a specific environment
     */
    public static function getForEnvironment(string $environment): ?self
    {
        return self::where('environment', $environment)
            ->where('is_active', true)
            ->first();
    }
}

