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
                        new OA\Property(property: "authorizations", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "code_inventory", type: "object"),
                        new OA\Property(property: "active_classes", type: "integer", example: 5)
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

        // Get authorizations
        $authorizations = TrainingCenterAccAuthorization::where('training_center_id', $trainingCenter->id)
            ->with('acc')
            ->get()
            ->map(function ($auth) {
                return [
                    'acc' => [
                        'name' => $auth->acc->name,
                    ],
                    'status' => $auth->status,
                ];
            });

        // Get code inventory summary
        $codeInventory = [
            'total' => CertificateCode::where('training_center_id', $trainingCenter->id)->count(),
            'used' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'used')->count(),
            'available' => CertificateCode::where('training_center_id', $trainingCenter->id)
                ->where('status', 'available')->count(),
        ];

        $activeClasses = TrainingClass::where('training_center_id', $trainingCenter->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->count();

        return response()->json([
            'authorizations' => $authorizations,
            'code_inventory' => $codeInventory,
            'active_classes' => $activeClasses,
        ]);
    }
}

