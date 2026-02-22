<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_center_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'id_number',
        'country',
        'city',
        'cv_url',
        'passport_image_url',
        'photo_url',
        'certificates_json',
        'specializations',
        'status',
        'is_assessor',
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
            'date_of_birth' => 'date',
            'certificates_json' => 'array',
            'specializations' => 'array',
            'is_assessor' => 'boolean',
            'stripe_requirements' => 'array',
            'stripe_onboarding_completed' => 'boolean',
            'stripe_onboarding_completed_at' => 'datetime',
            'stripe_connected_at' => 'datetime',
            'stripe_last_status_check_at' => 'datetime',
        ];
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    /**
     * Additional training centers this instructor is linked to (besides primary training_center_id).
     * Used when a TC "adds" an existing instructor by email.
     */
    public function linkedTrainingCenters(): BelongsToMany
    {
        return $this->belongsToMany(TrainingCenter::class, 'instructor_training_center', 'instructor_id', 'training_center_id')
            ->withTimestamps();
    }

    /**
     * Training centers this instructor has worked with: primary TC, linked TCs, TCs from authorizations, TCs from classes.
     */
    public function getTrainingCentersWorkedWith(): \Illuminate\Support\Collection
    {
        $ids = collect([$this->training_center_id])->filter();
        $ids = $ids->merge(
            $this->linkedTrainingCenters()->select('training_centers.id')->pluck('training_centers.id')
        );
        $ids = $ids->merge(
            InstructorAccAuthorization::where('instructor_id', $this->id)->distinct()->pluck('training_center_id')
        );
        $ids = $ids->merge(
            $this->trainingClasses()->distinct()->pluck('training_center_id')
        );
        $ids = $ids->unique()->filter()->values();
        return TrainingCenter::whereIn('id', $ids)->get();
    }

    /**
     * ACCs this instructor has worked with (from approved authorizations and classes).
     */
    public function getAccsWorkedWith(): \Illuminate\Support\Collection
    {
        $ids = InstructorAccAuthorization::where('instructor_id', $this->id)->distinct()->pluck('acc_id');
        $ids = $ids->merge(
            $this->trainingClasses()->with('course:id,acc_id')->get()->pluck('course.acc_id')->filter()->unique()
        );
        $ids = $ids->unique()->filter()->values();
        return ACC::whereIn('id', $ids)->get();
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(InstructorAccAuthorization::class, 'instructor_id');
    }

    public function courseAuthorizations(): HasMany
    {
        return $this->hasMany(InstructorCourseAuthorization::class, 'instructor_id');
    }

    public function trainingClasses(): HasMany
    {
        return $this->hasMany(TrainingClass::class, 'instructor_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'instructor_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'instructor_course_authorization', 'instructor_id', 'course_id')
            ->wherePivot('status', 'active')
            ->withPivot('acc_id', 'authorized_at', 'authorized_by', 'status')
            ->withTimestamps()
            ->orderBy('courses.name');
    }
}

