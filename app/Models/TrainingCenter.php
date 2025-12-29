<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected function casts(): array
    {
        return [
            'referred_by_group' => 'boolean',
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
}

