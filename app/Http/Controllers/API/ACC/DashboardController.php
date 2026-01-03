<?php

namespace App\Http\Controllers\API\ACC;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ACC;
use App\Models\TrainingCenterAccAuthorization;
use App\Models\Transaction;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: "/acc/dashboard",
        summary: "Get ACC dashboard data",
        description: "Get dashboard statistics and data for the authenticated ACC admin.",
        tags: ["ACC"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dashboard data retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "pending_requests", type: "integer", example: 2, description: "Total pending requests (training centers + instructors)"),
                        new OA\Property(property: "active_training_centers", type: "integer", example: 1, description: "Total number of training center authorizations (all statuses)"),
                        new OA\Property(property: "active_instructors", type: "integer", example: 9, description: "Total number of instructor authorizations (all statuses)"),
                        new OA\Property(property: "certificates_generated", type: "integer", example: 0, description: "Total certificates generated"),
                        new OA\Property(property: "revenue", type: "object", properties: [
                            new OA\Property(property: "monthly", type: "number", format: "float", example: 46700.00, description: "Revenue for current month"),
                            new OA\Property(property: "total", type: "number", format: "float", example: 46700.00, description: "Total revenue")
                        ]),
                        new OA\Property(
                            property: "mailing_address",
                            type: "object",
                            description: "Mailing address information",
                            properties: [
                                new OA\Property(property: "street", type: "string", nullable: true, example: "123 Main Street"),
                                new OA\Property(property: "city", type: "string", nullable: true, example: "Cairo"),
                                new OA\Property(property: "country", type: "string", nullable: true, example: "Egypt"),
                                new OA\Property(property: "postal_code", type: "string", nullable: true, example: "12345")
                            ]
                        ),
                        new OA\Property(
                            property: "physical_address",
                            type: "object",
                            description: "Physical address information",
                            properties: [
                                new OA\Property(property: "street", type: "string", nullable: true, example: "456 Business Avenue"),
                                new OA\Property(property: "city", type: "string", nullable: true, example: "Cairo"),
                                new OA\Property(property: "country", type: "string", nullable: true, example: "Egypt"),
                                new OA\Property(property: "postal_code", type: "string", nullable: true, example: "12345")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "ACC not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $acc = ACC::where('email', $user->email)->first();

        if (!$acc) {
            return response()->json(['message' => 'ACC not found'], 404);
        }

        $subscription = $acc->subscriptions()->latest()->first();
        $pendingRequests = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'pending')
            ->count();

        $activeTrainingCenters = TrainingCenterAccAuthorization::where('acc_id', $acc->id)
            ->count();

        // Calculate revenue: Use provider_amount if available (destination charge), otherwise use amount
        $revenueThisMonth = Transaction::where('payee_type', 'acc')
            ->where('payee_id', $acc->id)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->get()
            ->sum(function ($transaction) {
                return $transaction->provider_amount ?? $transaction->amount;
            });

        $pendingInstructorRequests = \App\Models\InstructorAccAuthorization::where('acc_id', $acc->id)
            ->where('status', 'pending')
            ->count();

        $activeInstructors = \App\Models\InstructorAccAuthorization::where('acc_id', $acc->id)
            ->count();

        $totalRevenue = Transaction::where('payee_type', 'acc')
            ->where('payee_id', $acc->id)
            ->where('status', 'completed')
            ->get()
            ->sum(function ($transaction) {
                return $transaction->provider_amount ?? $transaction->amount;
            });

        $certificatesGenerated = \App\Models\Certificate::whereHas('course', function($q) use ($acc) {
            $q->where('acc_id', $acc->id);
        })->count();

        // Calculate total pending requests
        $totalPendingRequests = $pendingRequests + $pendingInstructorRequests;

        return response()->json([
            'pending_requests' => $totalPendingRequests,
            'active_training_centers' => $activeTrainingCenters,
            'active_instructors' => $activeInstructors,
            'certificates_generated' => $certificatesGenerated,
            'revenue' => [
                'monthly' => (float) $revenueThisMonth,
                'total' => (float) $totalRevenue,
            ],
            'mailing_address' => [
                'street' => $acc->mailing_street,
                'city' => $acc->mailing_city,
                'country' => $acc->mailing_country,
                'postal_code' => $acc->mailing_postal_code,
            ],
            'physical_address' => [
                'street' => $acc->physical_street,
                'city' => $acc->physical_city,
                'country' => $acc->physical_country,
                'postal_code' => $acc->physical_postal_code,
            ],
        ]);
    }
}

