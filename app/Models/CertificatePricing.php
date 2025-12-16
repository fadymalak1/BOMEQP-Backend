<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificatePricing extends Model
{
    use HasFactory;

    protected $table = 'certificate_pricing';

    protected $fillable = [
        'acc_id',
        'course_id',
        'base_price',
        'currency',
        'group_commission_percentage',
        'training_center_commission_percentage',
        'instructor_commission_percentage',
        'effective_from',
        'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'group_commission_percentage' => 'decimal:2',
            'training_center_commission_percentage' => 'decimal:2',
            'instructor_commission_percentage' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}

