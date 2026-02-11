<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_number',
        'course_id',
        'class_id', // Kept for backward compatibility
        'training_class_id', // New field for training class reference
        'training_center_id',
        'instructor_id',
        'type',
        'trainee_name',
        'trainee_id_number',
        'issue_date',
        'expiry_date',
        'template_id',
        'certificate_pdf_url',
        'verification_code',
        'status',
        'code_used_id',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function classModel(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function trainingClass(): BelongsTo
    {
        return $this->belongsTo(TrainingClass::class, 'training_class_id');
    }

    public function trainingCenter(): BelongsTo
    {
        return $this->belongsTo(TrainingCenter::class, 'training_center_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    public function codeUsed(): BelongsTo
    {
        return $this->belongsTo(CertificateCode::class, 'code_used_id');
    }

    /**
     * Boot method to automatically set type when saving
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($certificate) {
            // Auto-determine type if not set or if it's being created
            if (empty($certificate->type) || $certificate->isDirty(['instructor_id', 'trainee_name'])) {
                // Only auto-determine if type is empty or if this is a new record
                // Don't override manually set types unless fields changed
                if (empty($certificate->type) || $certificate->wasRecentlyCreated) {
                    $certificate->type = static::determineType(
                        $certificate->instructor_id,
                        $certificate->trainee_name ?? ''
                    );
                }
            }
        });
    }

    /**
     * Determine if this certificate is for an instructor based on trainee_name matching instructor's name
     */
    public static function determineType(?int $instructorId, string $traineeName): string
    {
        if (!$instructorId || empty($traineeName)) {
            return 'trainee';
        }

        $instructor = \App\Models\Instructor::find($instructorId);
        if (!$instructor) {
            return 'trainee';
        }

        // Build instructor full name
        $instructorFirstName = trim($instructor->first_name ?? '');
        $instructorLastName = trim($instructor->last_name ?? '');
        $instructorFullName = trim($instructorFirstName . ' ' . $instructorLastName);
        
        // Normalize both names for comparison
        // Remove extra spaces, convert to lowercase, trim
        $normalizedInstructorName = preg_replace('/\s+/', ' ', strtolower(trim($instructorFullName)));
        $normalizedTraineeName = preg_replace('/\s+/', ' ', strtolower(trim($traineeName)));

        // Check if names match exactly
        if (!empty($normalizedInstructorName) && $normalizedTraineeName === $normalizedInstructorName) {
            return 'instructor';
        }

        // Also check if trainee_name matches just first name or last name (edge case)
        // But only if instructor has both names
        if (!empty($instructorFirstName) && !empty($instructorLastName)) {
            $normalizedFirstName = preg_replace('/\s+/', ' ', strtolower(trim($instructorFirstName)));
            $normalizedLastName = preg_replace('/\s+/', ' ', strtolower(trim($instructorLastName)));
            
            // Check if trainee_name matches first name + last name in any order
            if ($normalizedTraineeName === $normalizedFirstName . ' ' . $normalizedLastName ||
                $normalizedTraineeName === $normalizedLastName . ' ' . $normalizedFirstName) {
                return 'instructor';
            }
        }

        return 'trainee';
    }
}

