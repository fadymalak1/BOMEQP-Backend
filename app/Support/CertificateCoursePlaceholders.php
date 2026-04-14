<?php

namespace App\Support;

/**
 * Placeholders for certificate templates (HTML / designer).
 * Use in markup as {{variable_key}}.
 */
class CertificateCoursePlaceholders
{
    /**
     * Shared by course and instructor templates: training provider, instructor name, delivery.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function sharedTrainingProviderPlaceholders(): array
    {
        return [
            [
                'key' => 'training_provider_name',
                'label' => 'Training provider name',
                'description' => 'Name of the training provider (from the instructor\'s or issuing training center profile).',
            ],
            [
                'key' => 'training_provider_phone',
                'label' => 'Training provider phone',
                'description' => 'Phone number of the training provider (training center phone).',
            ],
            [
                'key' => 'training_provider_id_number',
                'label' => 'Training provider ID number',
                'description' => 'Government / company registry ID from the training center profile (`company_gov_registry_number`).',
            ],
            [
                'key' => 'instructor_name',
                'label' => 'Instructor name',
                'description' => 'Course certificates: instructor selected at issue. Instructor certificates: the authorized instructor\'s full name.',
            ],
            [
                'key' => 'delivery_method',
                'label' => 'Delivery method',
                'description' => 'For course certificates: from class location when linked. For instructor certificates: usually empty unless you add class context later.',
            ],
        ];
    }

    /**
     * Course completion certificate placeholders (full list).
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function definitions(): array
    {
        return array_merge(
            self::sharedTrainingProviderPlaceholders(),
            [
                [
                    'key' => 'trainee_name',
                    'label' => 'Trainee name',
                    'description' => 'Student / trainee full name.',
                ],
                [
                    'key' => 'student_name',
                    'label' => 'Student name',
                    'description' => 'Alias for trainee name.',
                ],
                [
                    'key' => 'course_name',
                    'label' => 'Course name',
                    'description' => 'Course title.',
                ],
                [
                    'key' => 'course_code',
                    'label' => 'Course code',
                    'description' => 'Course code.',
                ],
                [
                    'key' => 'training_center_name',
                    'label' => 'Training center name',
                    'description' => 'Training center trading name.',
                ],
                [
                    'key' => 'acc_name',
                    'label' => 'ACC name',
                    'description' => 'Accreditation body name.',
                ],
                [
                    'key' => 'issue_date',
                    'label' => 'Issue date',
                    'description' => 'Certificate issue date.',
                ],
                [
                    'key' => 'issue_date_formatted',
                    'label' => 'Issue date (formatted)',
                    'description' => 'Human-readable issue date.',
                ],
                [
                    'key' => 'certificate_number',
                    'label' => 'Certificate number',
                    'description' => 'Certificate / serial number.',
                ],
                [
                    'key' => 'verification_code',
                    'label' => 'Verification code',
                    'description' => 'Code for QR / verification.',
                ],
                [
                    'key' => 'training_center_logo',
                    'label' => 'Training center logo',
                    'description' => 'Image — use in <img src="{{training_center_logo}}">',
                ],
                [
                    'key' => 'acc_logo',
                    'label' => 'ACC logo',
                    'description' => 'Image — use in <img src="{{acc_logo}}">',
                ],
                [
                    'key' => 'qr_code',
                    'label' => 'QR code',
                    'description' => 'QR image URL for verification.',
                ],
            ]
        );
    }

    /**
     * Instructor authorization certificate placeholders (shared provider fields + instructor variables).
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function instructorDefinitions(): array
    {
        return array_merge(
            self::sharedTrainingProviderPlaceholders(),
            [
                [
                    'key' => 'instructor_first_name',
                    'label' => 'Instructor first name',
                    'description' => 'Instructor first name.',
                ],
                [
                    'key' => 'instructor_last_name',
                    'label' => 'Instructor last name',
                    'description' => 'Instructor last name.',
                ],
                [
                    'key' => 'instructor_email',
                    'label' => 'Instructor email',
                    'description' => 'Instructor email address.',
                ],
                [
                    'key' => 'instructor_id_number',
                    'label' => 'Instructor ID number',
                    'description' => 'Government or national ID number.',
                ],
                [
                    'key' => 'instructor_country',
                    'label' => 'Instructor country',
                    'description' => 'Instructor country.',
                ],
                [
                    'key' => 'instructor_city',
                    'label' => 'Instructor city',
                    'description' => 'Instructor city.',
                ],
                [
                    'key' => 'instructor_photo',
                    'label' => 'Instructor photo',
                    'description' => 'Profile photo — use in <img src="{{instructor_photo}}">',
                ],
                [
                    'key' => 'course_name',
                    'label' => 'Course name',
                    'description' => 'Authorized course title.',
                ],
                [
                    'key' => 'course_name_ar',
                    'label' => 'Course name (Arabic)',
                    'description' => 'Course title in Arabic if set.',
                ],
                [
                    'key' => 'course_code',
                    'label' => 'Course code',
                    'description' => 'Course code.',
                ],
                [
                    'key' => 'training_center_name',
                    'label' => 'Training center name',
                    'description' => 'Training center linked to the instructor.',
                ],
                [
                    'key' => 'acc_name',
                    'label' => 'ACC name',
                    'description' => 'Accreditation body name.',
                ],
                [
                    'key' => 'acc_legal_name',
                    'label' => 'ACC legal name',
                    'description' => 'Accreditation body legal name.',
                ],
                [
                    'key' => 'acc_registration_number',
                    'label' => 'ACC registration number',
                    'description' => 'ACC registration number.',
                ],
                [
                    'key' => 'acc_country',
                    'label' => 'ACC country',
                    'description' => 'ACC country.',
                ],
                [
                    'key' => 'issue_date',
                    'label' => 'Issue date',
                    'description' => 'Certificate issue date (Y-m-d).',
                ],
                [
                    'key' => 'issue_date_formatted',
                    'label' => 'Issue date (formatted)',
                    'description' => 'Human-readable issue date.',
                ],
                [
                    'key' => 'expiry_date',
                    'label' => 'Expiry date',
                    'description' => 'Default authorization expiry (e.g. three years from issue).',
                ],
                [
                    'key' => 'verification_code',
                    'label' => 'Verification code',
                    'description' => 'Verification / serial code.',
                ],
                [
                    'key' => 'serial_number',
                    'label' => 'Serial number',
                    'description' => 'Same as verification code when issued.',
                ],
                [
                    'key' => 'training_center_logo',
                    'label' => 'Training center logo',
                    'description' => 'Image — use in <img src="{{training_center_logo}}">',
                ],
                [
                    'key' => 'acc_logo',
                    'label' => 'ACC logo',
                    'description' => 'Image — use in <img src="{{acc_logo}}">',
                ],
                [
                    'key' => 'qr_code',
                    'label' => 'QR code',
                    'description' => 'QR image for verification.',
                ],
            ]
        );
    }
}
