<?php

namespace App\Http\Controllers\API\TrainingCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\CertificateCode;
use App\Models\TrainingClass;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: "/training-center/dashboard",
        summary: "Get Training Center dashboard data",
        description: "Get dashboard statistics and data for the authenticated training center admin.",
        tags: ["Training Center"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dashboard data retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "authorized_accreditations", type: "integer", example: 3, description: "Total number of ACC authorizations (all statuses)"),
                        new OA\Property(property: "classes", type: "integer", example: 4, description: "Total number of classes (all statuses)"),
                        new OA\Property(property: "instructors", type: "integer", example: 10, description: "Total number of instructors (all statuses)"),
                        new OA\Property(property: "trainees", type: "integer", example: 25, description: "Total number of trainees (all statuses)"),
                        new OA\Property(property: "certificates", type: "integer", example: 0, description: "Total number of certificates (all statuses)"),
                        new OA\Property(property: "training_center_state", type: "object", properties: [
                            new OA\Property(property: "status", type: "string", example: "active", description: "Training center status"),
                            new OA\Property(property: "registration_date", type: "string", format: "date", nullable: true, example: "2024-01-15", description: "Registration date"),
                            new OA\Property(property: "accreditation_status", type: "string", example: "Verified", description: "Accreditation status")
                        ]),
                        new OA\Property(property: "charts", type: "object", properties: [
                            new OA\Property(property: "classes_over_time", type: "array", items: new OA\Items(type: "object"), description: "Classes count for last 6 months"),
                            new OA\Property(property: "classes_status_distribution", type: "array", items: new OA\Items(type: "object"), description: "Distribution of classes by status")
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Training center not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $trainingCenter = \App\Models\TrainingCenter::where('email', $user->email)->first();

        if (!$trainingCenter) {
            return response()->json(['message' => 'Training center not found'], 404);
        }

        // Get authorized accreditations count (all statuses)
        $authorizedAccreditations = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->count();

        // Get total classes
        $classes = TrainingClass::where('training_center_id', $trainingCenter->id)->count();

        // Get total instructors
        $instructors = \App\Models\Instructor::where('training_center_id', $trainingCenter->id)->count();

        // Get total trainees
        $trainees = \App\Models\Trainee::where('training_center_id', $trainingCenter->id)->count();

        // Get total certificates
        $certificates = \App\Models\Certificate::where('training_center_id', $trainingCenter->id)->count();

        // Get training center state
        $registrationDate = $trainingCenter->created_at ? $trainingCenter->created_at->format('Y-m-d') : null;
        
        // Check if training center has any approved authorizations (verified status)
        $hasApprovedAuthorization = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->where('status', 'approved')
            ->exists();
        
        $accreditationStatus = $hasApprovedAuthorization ? 'Verified' : 'Not Verified';

        // Classes chart data (last 6 months)
        $classesChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthClasses = TrainingClass::where('training_center_id', $trainingCenter->id)
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();
            
            $classesChart[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->format('M Y'),
                'count' => $monthClasses,
            ];
        }

        // Classes status distribution
        $classesStatusDistribution = [
            ['label' => 'Scheduled', 'value' => TrainingClass::where('training_center_id', $trainingCenter->id)->where('status', 'scheduled')->count()],
            ['label' => 'In Progress', 'value' => TrainingClass::where('training_center_id', $trainingCenter->id)->where('status', 'in_progress')->count()],
            ['label' => 'Completed', 'value' => TrainingClass::where('training_center_id', $trainingCenter->id)->where('status', 'completed')->count()],
            ['label' => 'Cancelled', 'value' => TrainingClass::where('training_center_id', $trainingCenter->id)->where('status', 'cancelled')->count()],
        ];

        return response()->json([
            'authorized_accreditations' => $authorizedAccreditations,
            'classes' => $classes,
            'instructors' => $instructors,
            'trainees' => $trainees,
            'certificates' => $certificates,
            'training_center_state' => [
                'status' => $trainingCenter->status,
                'registration_date' => $registrationDate,
                'accreditation_status' => $accreditationStatus,
            ],
            'charts' => [
                'classes_over_time' => $classesChart,
                'classes_status_distribution' => $classesStatusDistribution,
            ],
        ]);
    }
}

