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
        'mailing_street',
        'mailing_city',
        'mailing_country',
        'mailing_postal_code',
        'physical_street',
        'physical_city',
        'physical_country',
        'physical_postal_code',
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
        'stripe_account_id',
        'rejection_reason',
        // Company Information
        'fax',
        // Mailing Address
        'mailing_same_as_physical',
        // Primary Contact
        'primary_contact_title',
        'primary_contact_first_name',
        'primary_contact_last_name',
        'primary_contact_email',
        'primary_contact_country',
        'primary_contact_mobile',
        'primary_contact_passport_url',
        // Secondary Contact
        'secondary_contact_title',
        'secondary_contact_first_name',
        'secondary_contact_last_name',
        'secondary_contact_email',
        'secondary_contact_country',
        'secondary_contact_mobile',
        'secondary_contact_passport_url',
        // Additional Information
        'company_gov_registry_number',
        'company_registration_certificate_url',
        'how_did_you_hear_about_us',
        // Agreement Checkboxes
        'agreed_to_receive_communications',
        'agreed_to_terms_and_conditions',
        // Stripe Connect
        'stripe_connect_status',
        'stripe_onboarding_url',
        'stripe_onboarding_completed',
        'stripe_onboarding_completed_at',
        'stripe_requirements',
        'stripe_connected_by_admin',
        'stripe_connected_at',
        'stripe_last_status_check_at',
        'stripe_last_error_message',
    ];

    protected function casts(): array
    {
        return [
            'registration_fee_paid' => 'boolean',
            'registration_fee_amount' => 'decimal:2',
            'registration_paid_at' => 'datetime',
            'approved_at' => 'datetime',
            'commission_percentage' => 'decimal:2',
            'mailing_same_as_physical' => 'boolean',
            'agreed_to_receive_communications' => 'boolean',
            'agreed_to_terms_and_conditions' => 'boolean',
            'stripe_requirements' => 'array',
            'stripe_onboarding_completed' => 'boolean',
            'stripe_onboarding_completed_at' => 'datetime',
            'stripe_connected_at' => 'datetime',
            'stripe_last_status_check_at' => 'datetime',
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

