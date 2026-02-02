<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstructorAccAuthorization extends Model
{
    use HasFactory;

    protected $table = 'instructor_acc_authorization';

    protected $fillable = [
        'instructor_id',
        'acc_id',
        'sub_category_id',
        'training_center_id',
        'request_date',
        'status',
        'group_admin_status',
        'commission_percentage',
        'authorization_price',
        'payment_status',
        'payment_date',
        'payment_transaction_id',
        'rejection_reason',
        'return_comment',
        'reviewed_by',
        'reviewed_at',
        'group_commission_set_by',
        'group_commission_set_at',
        'documents_json',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'datetime',
            'reviewed_at' => 'datetime',
            'group_commission_set_at' => 'datetime',
            'payment_date' => 'datetime',
            'commission_percentage' => 'decimal:2',
            'authorization_price' => 'decimal:2',
            'documents_json' => 'array',
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class, 'sub_category_id');
    }

    public function courseAuthorizations(): HasMany
    {
        return $this->hasMany(InstructorCourseAuthorization::class, 'instructor_id', 'instructor_id')
            ->where('acc_id', $this->acc_id);
    }
}
