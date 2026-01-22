<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\StripeConnectService;
use App\Models\StripeConnectLog;
use App\Models\AdminActivityLog;
use App\Models\ACC;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class StripeConnectController extends Controller
{
    protected StripeConnectService $stripeConnectService;

    public function __construct(StripeConnectService $stripeConnectService)
    {
        $this->stripeConnectService = $stripeConnectService;
    }

    #[OA\Get(
        path: "/admin/stripe-connect/accounts",
        summary: "Get all ACCs with Stripe Connect status",
        description: "Get a list of all ACCs with their Stripe Connect status. Includes search and filter capabilities.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), description: "Search by name or email"),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["pending", "connected", "failed", "inactive", "updating"])),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 15)),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: "ACCs retrieved successfully"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function index(Request $request)
    {
        try 
        'phone' => $acc->phone,
                    'type' => 'acc',
                    'stripe_account_id' => $acc->stripe_account_id,
                    'stripe_connect_status' => $acc->stripe_connect_status,
                    'stripe_connected_at' => $acc->stripe_connected_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'accounts' => $accounts,
                    'total' => $accs->total(),
                    'page' => $accs->currentPage(),
                    'per_page' => $accs->perPage(),
                    'last_page' => $accs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get accounts', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accounts',
            ], 500);
        }
    }

    #[OA\Get(
        path: "/admin/stripe-connect/accounts/{accountType}/{accountId}",
        summary: "Get account details with Stripe Connect status",
        description: "Get detailed information about a specific account including Stripe Connect status, requirements, and bank information.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "accountType", in: "path", required: true, schema: new OA\Schema(type: "string", enum: ["acc", "training_center", "instructor"])),
            new OA\Parameter(name: "accountId", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Account details retrieved successfully"),
            new OA\Response(response: 404, description: "Account not found")
        ]
    )]
    public function show(string $accountType, int $accountId)
    {
        try {
            $result = $this->stripeConnectService->getStripeConnectStatus($accountType, $accountId);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            // Get account model for additional details
            $account = $this->stripeConnectService->getAccountModel($accountType, $accountId);

            // Get logs for this account
            $logs = StripeConnectLog::where('account_type', $accountType)
                ->where('account_id', $accountId)
                ->orderBy('performed_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'account' => $account,
                    'stripe_status' => $result['data'],
                    'logs' => $logs,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get account details', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve account details',
            ], 500);
        }
    }

    #[OA\Post(
        path: "/admin/stripe-connect/initiate",
        summary: "Initiate Stripe Connect for an account",
        description: "Create a Stripe Connect account and generate onboarding link for the specified account.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["account_type", "account_id"],
                properties: [
                    new OA\Property(property: "account_type", type: "string", enum: ["acc", "training_center", "instructor"], example: "acc"),
                    new OA\Property(property: "account_id", type: "integer", example: 1),
                    new OA\Property(property: "country", type: "string", nullable: true, example: "EG", description: "Country code (default: EG)")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Stripe Connect initiated successfully"),
            new OA\Response(response: 400, description: "Bad request"),
            new OA\Response(response: 401, description: "Unauthenticated")
        ]
    )]
    public function initiate(Request $request)
    {
        $request->validate([
            'account_type' => 'required|in:acc,training_center,instructor',
            'account_id' => 'required|integer',
            'country' => 'nullable|string|size:2',
        ]);

        try {
            $result = $this->stripeConnectService->initiateStripeConnect(
                $request->account_type,
                $request->account_id,
                $request->user(),
                $request->country ?? 'EG'
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Failed to initiate Stripe Connect', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate Stripe Connect',
            ], 500);
        }
    }

    #[OA\Get(
        path: "/admin/stripe-connect/status/{accountType}/{accountId}",
        summary: "Get Stripe Connect status for an account",
        description: "Get current Stripe Connect status, requirements, and bank information for a specific account.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "accountType", in: "path", required: true, schema: new OA\Schema(type: "string", enum: ["acc", "training_center", "instructor"])),
            new OA\Parameter(name: "accountId", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Status retrieved successfully"),
            new OA\Response(response: 404, description: "Account not found")
        ]
    )]
    public function status(string $accountType, int $accountId)
    {
        try {
            $result = $this->stripeConnectService->getStripeConnectStatus($accountType, $accountId);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Failed to get Stripe Connect status', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve status',
            ], 500);
        }
    }

    #[OA\Post(
        path: "/admin/stripe-connect/retry/{accountType}/{accountId}",
        summary: "Retry failed Stripe Connect initiation",
        description: "Retry initiating Stripe Connect for an account that previously failed.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "accountType", in: "path", required: true, schema: new OA\Schema(type: "string", enum: ["acc", "training_center", "instructor"])),
            new OA\Parameter(name: "accountId", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Retry initiated successfully"),
            new OA\Response(response: 404, description: "Account not found")
        ]
    )]
    public function retry(string $accountType, int $accountId, Request $request)
    {
        try {
            $result = $this->stripeConnectService->retryStripeConnect(
                $accountType,
                $accountId,
                $request->user()
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Failed to retry Stripe Connect', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retry Stripe Connect',
            ], 500);
        }
    }

    #[OA\Delete(
        path: "/admin/stripe-connect/disconnect/{accountType}/{accountId}",
        summary: "Disconnect Stripe Connect account",
        description: "Disconnect and delete Stripe Connect account for the specified account.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "accountType", in: "path", required: true, schema: new OA\Schema(type: "string", enum: ["acc", "training_center", "instructor"])),
            new OA\Parameter(name: "accountId", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Disconnected successfully"),
            new OA\Response(response: 404, description: "Account not found")
        ]
    )]
    public function disconnect(string $accountType, int $accountId, Request $request)
    {
        try {
            $result = $this->stripeConnectService->disconnectStripeConnect(
                $accountType,
                $accountId,
                $request->user()
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Failed to disconnect Stripe Connect', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect Stripe Connect',
            ], 500);
        }
    }

    #[OA\Post(
        path: "/admin/stripe-connect/resend-link",
        summary: "Resend onboarding link",
        description: "Generate a new onboarding link and send it to the account email.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["account_type", "account_id"],
                properties: [
                    new OA\Property(property: "account_type", type: "string", enum: ["acc", "training_center", "instructor"]),
                    new OA\Property(property: "account_id", type: "integer")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Link sent successfully"),
            new OA\Response(response: 400, description: "Bad request")
        ]
    )]
    public function resendLink(Request $request)
    {
        $request->validate([
            'account_type' => 'required|in:acc,training_center,instructor',
            'account_id' => 'required|integer',
        ]);

        try {
            $result = $this->stripeConnectService->resendOnboardingLink(
                $request->account_type,
                $request->account_id,
                $request->user()
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Failed to resend onboarding link', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend link',
            ], 500);
        }
    }

    #[OA\Get(
        path: "/admin/stripe-connect/logs",
        summary: "Get Stripe Connect logs",
        description: "Get all Stripe Connect action logs with optional filters.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "account_type", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["acc", "training_center", "instructor"])),
            new OA\Parameter(name: "account_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "action", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["initiated", "completed", "failed", "updated", "requirements_added", "disconnected", "retry"])),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string", enum: ["success", "failed", "pending"])),
            new OA\Parameter(name: "date_from", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "date_to", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date")),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 15)),
            new OA\Parameter(name: "page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: "Logs retrieved successfully")
        ]
    )]
    public function logs(Request $request)
    {
        try {
            $query = StripeConnectLog::with('admin');

            if ($request->has('account_type')) {
                $query->where('account_type', $request->account_type);
            }

            if ($request->has('account_id')) {
                $query->where('account_id', $request->account_id);
            }

            if ($request->has('action')) {
                $query->where('action', $request->action);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('performed_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('performed_at', '<=', $request->date_to);
            }

            $perPage = $request->get('per_page', 15);
            $logs = $query->orderBy('performed_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => $logs->items(),
                    'total' => $logs->total(),
                    'page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'last_page' => $logs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get logs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve logs',
            ], 500);
        }
    }

    #[OA\Get(
        path: "/admin/stripe-connect/stats",
        summary: "Get Stripe Connect statistics",
        description: "Get overall statistics about Stripe Connect ACCs including counts and success rate.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(response: 200, description: "Statistics retrieved successfully")
        ]
    )]
    public function stats()
    {
        try {
            // Calculate statistics for ACCs only (matching the accounts endpoint)
            $stats = [
                'total' => 0,
                'connected' => 0,
                'pending' => 0,
                'failed' => 0,
                'inactive' => 0,
                'updating' => 0,
            ];

            // Count ACCs by status
            $accs = ACC::selectRaw('stripe_connect_status, COUNT(*) as count')
                ->groupBy('stripe_connect_status')
                ->get();
            
            foreach ($accs as $acc) {
                $count = (int) $acc->count;
                $stats['total'] += $count;
                if ($acc->stripe_connect_status) {
                    $status = $acc->stripe_connect_status;
                    $stats[$status] = ($stats[$status] ?? 0) + $count;
                }
            }

            // Calculate success rate
            $successRate = $stats['total'] > 0 
                ? round(($stats['connected'] / $stats['total']) * 100, 2)
                : 0;r

            $statistics = [
                'total' => $stats['total'],
                'connected' => $stats['connected'] ?? 0,
                'pending' => $stats['pending'] ?? 0,
                'failed' => $stats['failed'] ?? 0,
                'inactive' => $stats['inactive'] ?? 0,
                'updating' => $stats['updating'] ?? 0,
                'success_rate' => $successRate,
                'updated_at' => now()->toIso8601String(),
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
            ], 500);
        }
    }

    #[OA\Get(
        path: "/admin/activity-logs",
        summary: "Get admin activity logs",
        description: "Get logs of all actions performed by admin users.",
        tags: ["Admin - Stripe Connect"],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(name: "action", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "limit", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 50)),
            new OA\Parameter(name: "offset", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 0))
        ],
        responses: [
            new OA\Response(response: 200, description: "Activity logs retrieved successfully")
        ]
    )]
    public function activityLogs(Request $request)
    {
        try {
            $query = AdminActivityLog::with('admin');

            if ($request->has('action')) {
                $query->where('action', $request->action);
            }

            $limit = $request->get('limit', 50);
            $offset = $request->get('offset', 0);
            
            $total = $query->count();
            $logs = $query->orderBy('timestamp', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'logs' => $logs,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get activity logs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity logs',
            ], 500);
        }
    }
}

