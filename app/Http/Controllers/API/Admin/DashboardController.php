<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ACC;
use App\Models\TrainingCenter;
use App\Models\Instructor;
use App\Models\Transaction;
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
                        new OA\Property(property: "revenue", type: "object", properties: [
                            new OA\Property(property: "monthly", type: "number", format: "float", example: 0.00, description: "Revenue for current month"),
                            new OA\Property(property: "total", type: "number", format: "float", example: 91254.00, description: "Total revenue")
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

        // Calculate revenue
        $monthlyRevenue = Transaction::where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->sum('amount');

        $totalRevenue = Transaction::where('status', 'completed')
            ->sum('amount');

        return response()->json([
            'accreditation_bodies' => $accreditationBodies,
            'training_centers' => $trainingCenters,
            'instructors' => $instructors,
            'revenue' => [
                'monthly' => (float) $monthlyRevenue,
                'total' => (float) $totalRevenue,
            ],
        ]);
    }
}

