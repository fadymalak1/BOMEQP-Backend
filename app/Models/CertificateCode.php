<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'batch_id',
        'training_center_id',
        'acc_id',
        'course_id',
        'purchased_price',
        'discount_applied',
        'discount_code_id',
        'status',
        'used_at',
        'used_for_certificate_id',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'purchased_price' => 'decimal:2',
            'discount_applied' => 'boolean',
            'used_at' => 'datetime',
            'purchased_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CodeBatch::class, 'batch_id');
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    public function usedForCertificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'used_for_certificate_id');
    }
}

