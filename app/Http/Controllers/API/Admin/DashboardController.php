<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ACC;
use App\Models\TrainingCenter;
use App\Models\Instructor;
use App\Models\Transaction;
use App\Models\Trainee;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: "/admin/dashboard",
        summary: "Get Group Admin dashboard data",
        description: "Get dashboard statistics and data for the authenticated group admin user.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dashboard data retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "accreditation_bodies", type: "integer", example: 12, description: "Total number of ACCs (all statuses)"),
                        new OA\Property(property: "training_centers", type: "integer", example: 17, description: "Total number of training centers (all statuses)"),
                        new OA\Property(property: "instructors", type: "integer", example: 15, description: "Total number of instructors (all statuses)"),
                        new OA\Property(property: "trainees", type: "integer", example: 150, description: "Total number of trainees (all statuses)"),
                        new OA\Property(property: "revenue", type: "object", properties: [
                            new OA\Property(property: "monthly", type: "number", format: "float", example: 0.00, description: "Revenue for current month"),
                            new OA\Property(property: "total", type: "number", format: "float", example: 91254.00, description: "Total revenue")
                        ]),
                        new OA\Property(property: "charts", type: "object", properties: [
                            new OA\Property(property: "revenue_over_time", type: "array", items: new OA\Items(type: "object"), description: "Revenue data for last 6 months"),
                            new OA\Property(property: "entity_distribution", type: "array", items: new OA\Items(type: "object"), description: "Distribution of entities (ACCs, Training Centers, Instructors, Trainees)")
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        // Get total counts (all records regardless of status)
        $accreditationBodies = ACC::count();
        $trainingCenters = TrainingCenter::count();
        $instructors = Instructor::count();
        $trainees = Trainee::count();

        // Calculate revenue: Use commission_amount if available (destination charge), otherwise use amount for group transactions
        $monthlyRevenue = Transaction::where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->get()
            ->sum(function ($transaction) {
                // If transaction is to group, use commission_amount if available
                if ($transaction->payee_type === 'group') {
                    return $transaction->commission_amount ?? $transaction->amount;
                }
                // For other transactions, use commission_amount from commission ledgers or transaction
                return $transaction->commission_amount ?? 0;
            });

        $totalRevenue = Transaction::where('status', 'completed')
            ->get()
            ->sum(function ($transaction) {
                // If transaction is to group, use commission_amount if available
                if ($transaction->payee_type === 'group') {
                    return $transaction->commission_amount ?? $transaction->amount;
                }
                // For other transactions, use commission_amount from commission ledgers or transaction
                return $transaction->commission_amount ?? 0;
            });

        // Revenue chart data (last 6 months)
        $revenueChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthRevenue = Transaction::where('status', 'completed')
                ->whereMonth('completed_at', $month->month)
                ->whereYear('completed_at', $month->year)
                ->get()
                ->sum(function ($transaction) {
                    if ($transaction->payee_type === 'group') {
                        return $transaction->commission_amount ?? $transaction->amount;
                    }
                    return $transaction->commission_amount ?? 0;
                });
            
            $revenueChart[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->format('M Y'),
                'revenue' => (float) $monthRevenue,
            ];
        }

        // Entity distribution chart
        $entityDistribution = [
            ['label' => 'Accreditation Bodies', 'value' => $accreditationBodies],
            ['label' => 'Training Centers', 'value' => $trainingCenters],
            ['label' => 'Instructors', 'value' => $instructors],
            ['label' => 'Trainees', 'value' => $trainees],
        ];

        return response()->json([
            'accreditation_bodies' => $accreditationBodies,
            'training_centers' => $trainingCenters,
            'instructors' => $instructors,
            'trainees' => $trainees,
            'revenue' => [
                'monthly' => (float) $monthlyRevenue,
                'total' => (float) $totalRevenue,
            ],
            'charts' => [
                'revenue_over_time' => $revenueChart,
                'entity_distribution' => $entityDistribution,
            ],
        ]);
    }
}

