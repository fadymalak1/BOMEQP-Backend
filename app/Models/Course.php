<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'sub_category_id',
        'acc_id',
        'name',
        'name_ar',
        'code',
        'description',
        'duration_hours',
        'level',
        'status',
    ];

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(SubCategory::class, 'sub_category_id');
    }

    public function acc(): BelongsTo
    {
        return $this->belongsTo(ACC::class, 'acc_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class, 'course_id');
    }

    public function certificatePricing(): HasMany
    {
        return $this->hasMany(CertificatePricing::class, 'course_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'course_id');
    }

    public function certificateCodes(): HasMany
    {
        return $this->hasMany(CertificateCode::class, 'course_id');
    }

    public function trainingClasses(): HasMany
    {
        return $this->hasMany(TrainingClass::class, 'course_id');
    }

    public function instructorAuthorizations(): HasMany
    {
        return $this->hasMany(InstructorCourseAuthorization::class, 'course_id');
    }
}

