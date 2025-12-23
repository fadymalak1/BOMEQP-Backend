<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ACC extends Model
{
    use HasFactory;

    protected $table = 'accs';

    protected $fillable = [
        'name',
        'legal_name',
        'registration_number',
        'country',
        'address',
        'phone',
        'email',
        'website',
        'logo_url',
        'status',
        'registration_fee_paid',
        'registration_fee_amount',
        'registration_paid_at',
        'approved_at',
        'approved_by',
        'commission_percentage',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'registration_fee_paid' => 'boolean',
            'registration_fee_amount' => 'decimal:2',
            'registration_paid_at' => 'datetime',
            'approved_at' => 'datetime',
            'commission_percentage' => 'decimal:2',
        ];
    }

    // Relationships
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ACCSubscription::class, 'acc_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ACCDocument::class, 'acc_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'acc_id');
    }

    public function certificateTemplates(): HasMany
    {
        return $this->hasMany(CertificateTemplate::class, 'acc_id');
    }

    public function trainingCenterAuthorizations(): HasMany
    {
        return $this->hasMany(TrainingCenterAccAuthorization::class, 'acc_id');
    }

    public function instructorAuthorizations(): HasMany
    {
        return $this->hasMany(InstructorAccAuthorization::class, 'acc_id');
    }

    public function discountCodes(): HasMany
    {
        return $this->hasMany(DiscountCode::class, 'acc_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ACCMaterial::class, 'acc_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'acc_category', 'acc_id', 'category_id');
    }
}

