<?php

namespace App\Http\Controllers\API\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\Transaction;
use App\Models\CommissionLedger;
use Illuminate\Http\Request;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class EarningController extends Controller
{
    #[OA\Get(
        path: "/instructor/earnings",
        summary: "Get instructor earnings",
        description: "Get earnings and transactions for the authenticated instructor with optional filtering by month/year.",
        tags: ["Instructor"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "month", in: "query", schema: new OA\Schema(type: "string", format: "date", pattern: "^\\d{4}-\\d{2}$"), example: "2024-01", description: "Filter by month (YYYY-MM)"),
            new OA\Parameter(name: "year", in: "query", schema: new OA\Schema(type: "integer"), example: 2024, description: "Filter by year")
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Earnings retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "earnings", type: "object", properties: [
                            new OA\Property(property: "total", type: "number", format: "float", example: 5000.00),
                            new OA\Property(property: "this_month", type: "number", format: "float", example: 500.00),
                            new OA\Property(property: "pending", type: "number", format: "float", example: 200.00),
                            new OA\Property(property: "paid", type: "number", format: "float", example: 4800.00)
                        ]),
                        new OA\Property(property: "transactions", type: "array", items: new OA\Items(type: "object"))
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated"),
            new OA\Response(response: 404, description: "Instructor not found")
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $instructor = Instructor::where('email', $user->email)->first();

        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }

        $query = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed');

        if ($request->has('month')) {
            $month = Carbon::createFromFormat('Y-m', $request->month);
            $query->whereMonth('completed_at', $month->month)
                  ->whereYear('completed_at', $month->year);
        }

        if ($request->has('year')) {
            $query->whereYear('completed_at', $request->year);
        }

        $transactions = $query->orderBy('completed_at', 'desc')->get();

        $total = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->sum('amount');

        $thisMonth = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->whereMonth('completed_at', now()->month)
            ->whereYear('completed_at', now()->year)
            ->sum('amount');

        $pending = CommissionLedger::where('instructor_id', $instructor->id)
            ->where('settlement_status', 'pending')
            ->sum('group_commission_amount'); // TODO: Should be instructor commission

        $paid = Transaction::where('payee_type', 'instructor')
            ->where('payee_id', $instructor->id)
            ->where('status', 'completed')
            ->sum('amount');

        return response()->json([
            'earnings' => [
                'total' => $total,
                'this_month' => $thisMonth,
                'pending' => $pending,
                'paid' => $paid,
            ],
            'transactions' => $transactions,
        ]);
    }
}

