<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TrainingCenter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'registration_number',
        'country',
        'city',
        'address',
        'phone',
        'email',
        'website',
        'logo_url',
        'referred_by_group',
        'status',
        // Company Information
        'fax',
        'training_provider_type',
        // Physical Address
        'physical_postal_code',
        // Mailing Address
        'mailing_same_as_physical',
        'mailing_address',
        'mailing_city',
        'mailing_country',
        'mailing_postal_code',
        // Primary Contact
        'primary_contact_title',
        'primary_contact_first_name',
        'primary_contact_last_name',
        'primary_contact_email',
        'primary_contact_country',
        'primary_contact_mobile',
        // Secondary Contact
        'has_secondary_contact',
        'secondary_contact_title',
        'secondary_contact_first_name',
        'secondary_contact_last_name',
        'secondary_contact_email',
        'secondary_contact_country',
        'secondary_contact_mobile',
        // Additional Information
        'company_gov_registry_number',
        'company_registration_certificate_url',
        'facility_floorplan_url',
        'interested_fields',
        'how_did_you_hear_about_us',
        // Agreement Checkboxes
        'agreed_to_receive_communications',
        'agreed_to_terms_and_conditions',
        // Stripe Connect
        'stripe_account_id',
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
            'referred_by_group' => 'boolean',
            'mailing_same_as_physical' => 'boolean',
            'has_secondary_contact' => 'boolean',
            'interested_fields' => 'array',
            'agreed_to_receive_communications' => 'boolean',
            'agreed_to_terms_and_conditions' => 'boolean',
            'stripe_requirements' => 'array',
            'stripe_onboarding_completed' => 'boolean',
            'stripe_onboarding_completed_at' => 'datetime',
            'stripe_connected_at' => 'datetime',
            'stripe_last_status_check_at' => 'datetime',
        ];
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(TrainingCenterAccAuthorization::class, 'training_center_id');
    }

    public function instructors(): HasMany
    {
        return $this->hasMany(Instructor::class, 'training_center_id');
    }

    /**
     * Instructors linked to this TC via pivot (added by email when they already existed in system).
     */
    public function linkedInstructors(): BelongsToMany
    {
        return $this->belongsToMany(Instructor::class, 'instructor_training_center', 'training_center_id', 'instructor_id')
            ->withTimestamps();
    }

    public function certificateCodes(): HasMany
    {
        return $this->hasMany(CertificateCode::class, 'training_center_id');
    }

    public function codeBatches(): HasMany
    {
        return $this->hasMany(CodeBatch::class, 'training_center_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'training_center_id');
    }

    public function trainingClasses(): HasMany
    {
        return $this->hasMany(TrainingClass::class, 'training_center_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(TrainingCenterPurchase::class, 'training_center_id');
    }

    public function trainees(): HasMany
    {
        return $this->hasMany(Trainee::class, 'training_center_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(TrainingCenterWallet::class, 'training_center_id');
    }
}

