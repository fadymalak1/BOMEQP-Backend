<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\Transaction;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class TransferController extends Controller
{
    protected TransferService $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }
    #[OA\Get(
        path: "/admin/transfers",
        summary: "Get all transfers with pagination and filters",
        description: "Get a paginated list of all transfers with optional filters for status, date range, and search.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "processing", "completed", "failed", "retrying"])),
            new OA\Parameter(name: "user_type", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["acc", "training_center", "instructor"])),
            new OA\Parameter(name: "date_from", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "date_to", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 15)),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Transfers retrieved successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(type: "object")),
                        new OA\Property(property: "statistics", type: "object"),
                        new OA\Property(property: "pagination", type: "object")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        $query = Transfer::with(['transaction', 'user', 'userTypeModel']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('stripe_transfer_id', 'like', "%{$search}%")
                  ->orWhere('stripe_account_id', 'like', "%{$search}%")
                  ->orWhereHas('transaction', function($tq) use ($search) {
                      $tq->where('payment_gateway_transaction_id', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Statistics
        $statistics = [
            'total' => Transfer::count(),
            'pending' => Transfer::where('status', 'pending')->count(),
            'processing' => Transfer::where('status', 'processing')->count(),
            'completed' => Transfer::where('status', 'completed')->count(),
            'failed' => Transfer::where('status', 'failed')->count(),
            'retrying' => Transfer::where('status', 'retrying')->count(),
            'total_gross_amount' => Transfer::sum('gross_amount'),
            'total_commission_amount' => Transfer::sum('commission_amount'),
            'total_net_amount' => Transfer::sum('net_amount'),
            'completed_gross_amount' => Transfer::where('status', 'completed')->sum('gross_amount'),
            'completed_commission_amount' => Transfer::where('status', 'completed')->sum('commission_amount'),
            'completed_net_amount' => Transfer::where('status', 'completed')->sum('net_amount'),
        ];

        return response()->json([
            'data' => $transfers->items(),
            'statistics' => $statistics,
            'pagination' => [
                'current_page' => $transfers->currentPage(),
                'last_page' => $transfers->lastPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
            ]
        ]);
    }

    #[OA\Get(
        path: "/admin/transfers/{id}",
        summary: "Get transfer details",
        description: "Get detailed information about a specific transfer.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Transfer retrieved successfully"),
            new OA\Response(response: 404, description: "Transfer not found")
        ]
    )]
    public function show($id)
    {
        $transfer = Transfer::with(['transaction', 'user', 'userTypeModel'])->findOrFail($id);
        
        return response()->json(['transfer' => $transfer]);
    }

    #[OA\Get(
        path: "/admin/transfers/reports/summary",
        summary: "Get transfer summary report",
        description: "Get summary statistics for transfers grouped by period (daily, weekly, monthly).",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "period", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["daily", "weekly", "monthly"], default: "monthly")),
            new OA\Parameter(name: "start_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "end_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Report retrieved successfully")
        ]
    )]
    public function summaryReport(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $startDate = $request->get('start_date') ? now()->parse($request->start_date)->startOfDay() : now()->startOfMonth();
        $endDate = $request->get('end_date') ? now()->parse($request->end_date)->endOfDay() : now()->endOfDay();

        $query = Transfer::whereBetween('created_at', [$startDate, $endDate]);

        switch ($period) {
            case 'daily':
                $query->selectRaw('DATE(created_at) as period, 
                    COUNT(*) as count,
                    SUM(gross_amount) as total_gross,
                    SUM(commission_amount) as total_commission,
                    SUM(net_amount) as total_net,
                    SUM(CASE WHEN status = "completed" THEN net_amount ELSE 0 END) as completed_net')
                    ->groupBy(DB::raw('DATE(created_at)'))
                    ->orderBy('period', 'desc');
                break;

            case 'weekly':
                $query->selectRaw('YEAR(created_at) as year, WEEK(created_at) as week,
                    COUNT(*) as count,
                    SUM(gross_amount) as total_gross,
                    SUM(commission_amount) as total_commission,
                    SUM(net_amount) as total_net,
                    SUM(CASE WHEN status = "completed" THEN net_amount ELSE 0 END) as completed_net')
                    ->groupBy(DB::raw('YEAR(created_at), WEEK(created_at)'))
                    ->orderBy('year', 'desc')
                    ->orderBy('week', 'desc');
                break;

            case 'monthly':
            default:
                $query->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month,
                    COUNT(*) as count,
                    SUM(gross_amount) as total_gross,
                    SUM(commission_amount) as total_commission,
                    SUM(net_amount) as total_net,
                    SUM(CASE WHEN status = "completed" THEN net_amount ELSE 0 END) as completed_net')
                    ->groupBy(DB::raw('YEAR(created_at), MONTH(created_at)'))
                    ->orderBy('year', 'desc')
                    ->orderBy('month', 'desc');
                break;
        }

        $report = $query->get();

        // Overall statistics
        $overall = [
            'total_transfers' => Transfer::whereBetween('created_at', [$startDate, $endDate])->count(),
            'completed_transfers' => Transfer::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')->count(),
            'failed_transfers' => Transfer::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'failed')->count(),
            'pending_transfers' => Transfer::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'pending')->count(),
            'total_gross_amount' => Transfer::whereBetween('created_at', [$startDate, $endDate])
                ->sum('gross_amount'),
            'total_commission_amount' => Transfer::whereBetween('created_at', [$startDate, $endDate])
                ->sum('commission_amount'),
            'total_net_amount' => Transfer::whereBetween('created_at', [$startDate, $endDate])
                ->sum('net_amount'),
            'completed_net_amount' => Transfer::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed')->sum('net_amount'),
        ];

        return response()->json([
            'period' => $period,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'overall' => $overall,
            'breakdown' => $report,
        ]);
    }

    #[OA\Post(
        path: "/admin/transfers/{id}/retry",
        summary: "Retry a failed transfer",
        description: "Manually retry a failed transfer. This will attempt to process the transfer again via Stripe.",
        tags: ["Admin"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Transfer retry initiated successfully"),
            new OA\Response(response: 404, description: "Transfer not found"),
            new OA\Response(response: 400, description: "Transfer cannot be retried")
        ]
    )]
    public function retry($id)
    {
        $transfer = Transfer::findOrFail($id);

        if (!$transfer->canRetry()) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer cannot be retried. Maximum retry attempts reached or transfer is not in failed status.',
            ], 400);
        }

        try {
            $result = $this->transferService->retryFailedTransfer($transfer);

            if ($result['success']) {
                $transfer->markAsCompleted($result['stripe_transfer_id']);
                $this->transferService->sendTransferNotification($transfer, 'completed');

                return response()->json([
                    'success' => true,
                    'message' => 'Transfer retry succeeded',
                    'transfer' => $transfer->fresh(),
                ]);
            } else {
                $transfer->markAsFailed($result['error'] ?? 'Retry failed');

                // جدولة إعادة محاولة تلقائية إذا أمكن
                if ($transfer->canRetry()) {
                    \App\Jobs\RetryFailedTransferJob::dispatch($transfer, 60)
                        ->delay(now()->addMinutes(1));
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Transfer retry failed',
                    'error' => $result['error'] ?? 'Unknown error',
                    'transfer' => $transfer->fresh(),
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Exception in transfer retry', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Transfer retry failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

