<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlySettlement;
use App\Services\FinancialService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FinancialController extends Controller
{
    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }
    #[OA\Get(
        path: "/admin/financial/dashboard",
        summary: "Get financial dashboard",
        description: "Get financial dashboard statistics for Group Admin.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Dashboard data retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "total_revenue", type: "number", format: "float", example: 50000.00),
                        new OA\Property(property: "pending_settlements", type: "number", format: "float", example: 5000.00),
                        new OA\Property(property: "this_month_revenue", type: "number", format: "float", example: 5000.00),
                        new OA\Property(property: "active_accs", type: "integer", example: 10)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function dashboard()
    {
        $stats = $this->financialService->getDashboardStats();

        return response()->json($stats);
    }

    #[OA\Get(
        path: "/admin/financial/transactions",
        summary: "Get all transactions",
        description: "Get all transactions for Group Admin with comprehensive details including payer, payee, commission ledger, and reference information.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "type", in: "query", schema: new OA\Schema(type: "string", enum: ["subscription", "code_purchase", "material_purchase", "course_purchase", "commission", "settlement"]), example: "subscription"),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string", enum: ["pending", "completed", "failed", "refunded"]), example: "completed"),
            new OA\Parameter(name: "payer_type", in: "query", schema: new OA\Schema(type: "string", enum: ["acc", "training_center", "group"]), example: "acc"),
            new OA\Parameter(name: "payee_type", in: "query", schema: new OA\Schema(type: "string", enum: ["group", "acc", "instructor"]), example: "group"),
            new OA\Parameter(name: "date_from", in: "query", schema: new OA\Schema(type: "string", format: "date"), example: "2024-01-01"),
            new OA\Parameter(name: "date_to", in: "query", schema: new OA\Schema(type: "string", format: "date"), example: "2024-12-31"),
            new OA\Parameter(name: "per_page", in: "query", schema: new OA\Schema(type: "integer"), example: 15),
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Transactions retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "summary", type: "object"),
                        new OA\Property(property: "current_page", type: "integer", example: 1),
                        new OA\Property(property: "per_page", type: "integer", example: 15),
                        new OA\Property(property: "total", type: "integer", example: 100)
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function transactions(Request $request)
    {
        $result = $this->financialService->getTransactions($request);

        return response()->json($result);
    }

    #[OA\Get(
        path: "/admin/financial/settlements",
        summary: "Get monthly settlements",
        description: "Get all monthly settlements.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Settlements retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "settlements", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function settlements(Request $request)
    {
        $settlements = MonthlySettlement::with('acc')
            ->orderBy('settlement_month', 'desc')
            ->get();

        return response()->json(['settlements' => $settlements]);
    }

    #[OA\Post(
        path: "/admin/financial/settlements/{id}/request-payment",
        summary: "Request payment for settlement",
        description: "Request payment for a monthly settlement.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"), example: 1)
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Payment request sent successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Payment request sent successfully")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Settlement not found")
        ]
    )]
    public function requestPayment(Request $request, $id)
    {
        $settlement = MonthlySettlement::findOrFail($id);
        
        $settlement->update([
            'status' => 'requested',
            'request_date' => now(),
        ]);

        // TODO: Send notification/email to ACC

        return response()->json(['message' => 'Payment request sent successfully']);
    }
}

