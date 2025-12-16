<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'acc_id',
        'code',
        'discount_type',
        'discount_percentage',
        'applicable_course_ids',
        'start_date',
        'end_date',
        'total_quantity',
        'used_quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_percentage' => 'decimal:2',
            'applicable_course_ids' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function certificateCodes(): HasMany
    {
        return $this->hasMany(CertificateCode::class, 'discount_code_id');
    }
}

