<?php

namespace App\Services;

use App\Models\ACC;
use App\Models\TrainingCenter;
use App\Models\Instructor;
use App\Models\StripeConnectLog;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Services\StripeService;
use App\Services\NotificationService;
use App\Mail\StripeOnboardingMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Exception\ApiErrorException;

class StripeConnectService
{
    protected StripeService $stripeService;
    protected NotificationService $notificationService;

    public function __construct(
        StripeService $stripeService,
        NotificationService $notificationService
    ) {
        $this->stripeService = $stripeService;
        $this->notificationService = $notificationService;
    }

    /**
     * Initiate Stripe Connect for an account
     * 
     * @param string $accountType - acc, training_center, or instructor
     * @param int $accountId - Account ID
     * @param User $admin - Admin user who initiated
     * @param string|null $country - Country code (default: 'EG')
     * @return array
     */
    public function initiateStripeConnect(string $accountType, int $accountId, User $admin, ?string $country = 'EG'): array
    {
        DB::beginTransaction();
        
        try {
            // التحقق من صلاحيات Admin
            if (!$this->isAdmin($admin)) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized: Admin access required',
                ];
            }

            // الحصول على الحساب
            $account = $this->getAccountModel($accountType, $accountId);
            
            if (!$account) {
                return [
                    'success' => false,
                    'message' => 'Account not found',
                ];
            }

            // التحقق من أن الحساب لم يكن متصلاً مسبقاً
            if ($account->stripe_account_id && $account->stripe_connect_status === 'connected') {
                return [
                    'success' => false,
                    'message' => 'Account is already connected to Stripe',
                ];
            }

            // التحقق من إعداد Stripe
            if (!$this->stripeService->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Stripe is not configured',
                ];
            }

            // إنشاء Stripe Connected Account
            $accountData = $this->prepareAccountData($account, $accountType);
            
            $stripeAccount = Account::create([
                'type' => 'standard', // Can be changed to 'express' if needed
                'country' => strtoupper($country),
                'email' => $accountData['email'],
                'business_profile' => [
                    'name' => $accountData['name'],
                    'url' => $accountData['website'] ?? null,
                ],
                'metadata' => [
                    'account_type' => $accountType,
                    'account_id' => $accountId,
                    'account_name' => $accountData['name'],
                    'admin_id' => $admin->id,
                    'platform_name' => config('app.name', 'BOMEQP'),
                ],
            ]);

            // إنشاء رابط Onboarding
            $onboardingUrl = $this->createOnboardingLink($stripeAccount->id);

            // تحديث الحساب في قاعدة البيانات
            $this->updateAccountStripeData($account, $accountType, [
                'stripe_account_id' => $stripeAccount->id,
                'stripe_connect_status' => 'pending',
                'stripe_onboarding_url' => $onboardingUrl,
                'stripe_onboarding_completed' => false,
                'stripe_connected_by_admin' => $admin->id,
                'stripe_connected_at' => now(),
                'stripe_last_status_check_at' => now(),
            ]);

            // تسجيل العملية
            $this->logStripeConnectAction(
                $accountType,
                $accountId,
                $this->getAccountName($account, $accountType),
                'initiated',
                'success',
                $stripeAccount->id,
                null,
                $admin->id
            );

            // تسجيل نشاط Admin
            $this->logAdminActivity(
                $admin,
                'initiate',
                $accountType,
                $accountId,
                $this->getAccountName($account, $accountType),
                'success'
            );

            DB::commit();

            // إرسال بريد إلكتروني برابط Onboarding
            $this->sendOnboardingEmail($account, $accountType, $onboardingUrl);

            Log::info('Stripe Connect initiated successfully', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'stripe_account_id' => $stripeAccount->id,
                'admin_id' => $admin->id,
            ]);

            return [
                'success' => true,
                'message' => 'Stripe Connect initiated successfully',
                'data' => [
                    'stripe_connected_account_id' => $stripeAccount->id,
                    'onboarding_url' => $onboardingUrl,
                    'status' => 'pending',
                    'created_at' => now()->toIso8601String(),
                ],
            ];

        } catch (ApiErrorException $e) {
            DB::rollBack();
            
            $errorMessage = $e->getMessage();
            
            // تحديث الحساب بحالة الفشل
            if (isset($account)) {
                $this->updateAccountStripeData($account, $accountType, [
                    'stripe_connect_status' => 'failed',
                    'stripe_last_error_message' => $errorMessage,
                    'stripe_last_status_check_at' => now(),
                ]);

                // تسجيل الفشل
                $this->logStripeConnectAction(
                    $accountType,
                    $accountId,
                    $this->getAccountName($account, $accountType),
                    'initiated',
                    'failed',
                    null,
                    $errorMessage,
                    $admin->id
                );
            }

            Log::error('Stripe Connect initiation failed', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'error' => $errorMessage,
                'error_code' => $e->getStripeCode(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initiate Stripe Connect: ' . $errorMessage,
                'error_code' => $e->getStripeCode(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Exception in initiateStripeConnect', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get Stripe Connect status for an account
     */
    public function getStripeConnectStatus(string $accountType, int $accountId): array
    {
        try {
            $account = $this->getAccountModel($accountType, $accountId);
            
            if (!$account) {
                return [
                    'success' => false,
                    'message' => 'Account not found',
                ];
            }

            if (!$account->stripe_account_id) {
                return [
                    'success' => true,
                    'data' => [
                        'status' => null,
                        'message' => 'Stripe Connect not initiated',
                    ],
                ];
            }

            // الحصول على أحدث حالة من Stripe
            $stripeAccount = Account::retrieve($account->stripe_account_id);

            // تحديث الحالة في قاعدة البيانات
            $this->updateAccountFromStripe($account, $accountType, $stripeAccount);

            // استخراج المتطلبات
            $requirements = [
                'currently_due' => $stripeAccount->requirements->currently_due ?? [],
                'eventually_due' => $stripeAccount->requirements->eventually_due ?? [],
                'pending_verification' => $stripeAccount->requirements->pending_verification ?? [],
            ];

            // الحصول على معلومات البنك (مخفية جزئياً)
            $bankInfo = $this->getBankInfo($stripeAccount);

            return [
                'success' => true,
                'data' => [
                    'status' => $account->stripe_connect_status,
                    'stripe_account_id' => $account->stripe_account_id,
                    'connected_at' => $account->stripe_connected_at?->toIso8601String(),
                    'requirements' => $requirements,
                    'bank_info' => $bankInfo,
                    'onboarding_url' => $account->stripe_onboarding_url,
                    'onboarding_completed' => $account->stripe_onboarding_completed,
                    'last_error' => $account->stripe_last_error_message,
                    'last_status_check' => $account->stripe_last_status_check_at?->toIso8601String(),
                ],
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to get Stripe Connect status', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('Exception in getStripeConnectStatus', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retry failed Stripe Connect initiation
     */
    public function retryStripeConnect(string $accountType, int $accountId, User $admin): array
    {
        try {
            $account = $this->getAccountModel($accountType, $accountId);
            
            if (!$account) {
                return [
                    'success' => false,
                    'message' => 'Account not found',
                ];
            }

            // إذا كان لديه stripe_account_id بالفعل، حاول إنشاء onboarding link جديد
            if ($account->stripe_account_id) {
                $onboardingUrl = $this->createOnboardingLink($account->stripe_account_id);
                
                $this->updateAccountStripeData($account, $accountType, [
                    'stripe_connect_status' => 'pending',
                    'stripe_onboarding_url' => $onboardingUrl,
                    'stripe_last_error_message' => null,
                    'stripe_last_status_check_at' => now(),
                ]);

                // تسجيل العملية
                $this->logStripeConnectAction(
                    $accountType,
                    $accountId,
                    $this->getAccountName($account, $accountType),
                    'retry',
                    'success',
                    $account->stripe_account_id,
                    null,
                    $admin->id
                );

                $this->logAdminActivity($admin, 'retry', $accountType, $accountId, $this->getAccountName($account, $accountType), 'success');

                return [
                    'success' => true,
                    'message' => 'Retry initiated successfully',
                    'data' => [
                        'onboarding_url' => $onboardingUrl,
                        'status' => 'pending',
                    ],
                ];
            }

            // إذا لم يكن لديه stripe_account_id، أعد المحاولة من البداية
            return $this->initiateStripeConnect($accountType, $accountId, $admin);

        } catch (\Exception $e) {
            Log::error('Exception in retryStripeConnect', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Retry failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Disconnect Stripe Connect account
     */
    public function disconnectStripeConnect(string $accountType, int $accountId, User $admin): array
    {
        DB::beginTransaction();
        
        try {
            $account = $this->getAccountModel($accountType, $accountId);
            
            if (!$account || !$account->stripe_account_id) {
                return [
                    'success' => false,
                    'message' => 'Account not connected to Stripe',
                ];
            }

            $stripeAccountId = $account->stripe_account_id;

            // حذف الحساب من Stripe (أو تعطيله)
            try {
                Account::delete($stripeAccountId);
            } catch (ApiErrorException $e) {
                // إذا فشل الحذف، فقط قم بتحديث الحالة
                Log::warning('Failed to delete Stripe account, marking as inactive', [
                    'stripe_account_id' => $stripeAccountId,
                    'error' => $e->getMessage(),
                ]);
            }

            // تحديث الحساب
            $this->updateAccountStripeData($account, $accountType, [
                'stripe_account_id' => null,
                'stripe_connect_status' => 'inactive',
                'stripe_onboarding_url' => null,
                'stripe_onboarding_completed' => false,
                'stripe_onboarding_completed_at' => null,
                'stripe_requirements' => null,
                'stripe_last_error_message' => 'Disconnected by admin',
            ]);

            // تسجيل العملية
            $this->logStripeConnectAction(
                $accountType,
                $accountId,
                $this->getAccountName($account, $accountType),
                'disconnected',
                'success',
                $stripeAccountId,
                null,
                $admin->id
            );

            $this->logAdminActivity($admin, 'disconnect', $accountType, $accountId, $this->getAccountName($account, $accountType), 'success');

            DB::commit();

            return [
                'success' => true,
                'message' => 'Stripe Connect disconnected successfully',
                'data' => [
                    'status' => 'inactive',
                    'disconnected_at' => now()->toIso8601String(),
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Exception in disconnectStripeConnect', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Disconnect failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Resend onboarding link
     */
    public function resendOnboardingLink(string $accountType, int $accountId, User $admin): array
    {
        try {
            $account = $this->getAccountModel($accountType, $accountId);
            
            if (!$account || !$account->stripe_account_id) {
                return [
                    'success' => false,
                    'message' => 'Stripe Connect not initiated',
                ];
            }

            // إنشاء رابط onboarding جديد
            $onboardingUrl = $this->createOnboardingLink($account->stripe_account_id);
            
            $this->updateAccountStripeData($account, $accountType, [
                'stripe_onboarding_url' => $onboardingUrl,
                'stripe_last_status_check_at' => now(),
            ]);

            // إرسال بريد إلكتروني
            $this->sendOnboardingEmail($account, $accountType, $onboardingUrl);

            // تسجيل النشاط
            $this->logAdminActivity($admin, 'resend_link', $accountType, $accountId, $this->getAccountName($account, $accountType), 'success');

            return [
                'success' => true,
                'message' => 'Onboarding link sent successfully',
                'data' => [
                    'onboarding_url' => $onboardingUrl,
                    'email_sent' => true,
                    'sent_to' => $this->getAccountEmail($account, $accountType),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Exception in resendOnboardingLink', [
                'account_type' => $accountType,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to resend link: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle Stripe webhook events for Connect
     */
    public function handleStripeConnectWebhook(array $event): void
    {
        try {
            $eventType = $event['type'];
            $eventData = $event['data']['object'] ?? [];

            switch ($eventType) {
                case 'account.updated':
                    $this->handleAccountUpdated($eventData);
                    break;
                    
                case 'account.external_account.created':
                case 'account.external_account.updated':
                    $this->handleExternalAccountUpdated($eventData);
                    break;
                    
                case 'account.application.deauthorized':
                    $this->handleAccountDeauthorized($eventData);
                    break;
                    
                default:
                    Log::info('Unhandled Stripe Connect webhook event', [
                        'event_type' => $eventType,
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception in handleStripeConnectWebhook', [
                'event_type' => $event['type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all accounts with Stripe Connect status
     */
    public function getAllAccounts(Request $request): array
    {
        $accounts = [];

        // ACCs
        $accs = ACC::select('id', 'name', 'email', 'phone', 'stripe_account_id', 'stripe_connect_status', 'stripe_connected_at')
            ->when($request->has('search'), function($q) use ($request) {
                $search = $request->search;
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->has('status'), function($q) use ($request) {
                $q->where('stripe_connect_status', $request->status);
            })
            ->get()
            ->map(function($acc) {
                return [
                    'id' => $acc->id,
                    'name' => $acc->name,
                    'email' => $acc->email,
                    'phone' => $acc->phone,
                    'type' => 'acc',
                    'stripe_account_id' => $acc->stripe_account_id,
                    'stripe_connect_status' => $acc->stripe_connect_status,
                    'stripe_connected_at' => $acc->stripe_connected_at?->toIso8601String(),
                ];
            });

        // Training Centers
        $trainingCenters = TrainingCenter::select('id', 'name', 'email', 'phone', 'stripe_account_id', 'stripe_connect_status', 'stripe_connected_at')
            ->when($request->has('search'), function($q) use ($request) {
                $search = $request->search;
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->has('status'), function($q) use ($request) {
                $q->where('stripe_connect_status', $request->status);
            })
            ->get()
            ->map(function($tc) {
                return [
                    'id' => $tc->id,
                    'name' => $tc->name,
                    'email' => $tc->email,
                    'phone' => $tc->phone,
                    'type' => 'training_center',
                    'stripe_account_id' => $tc->stripe_account_id,
                    'stripe_connect_status' => $tc->stripe_connect_status,
                    'stripe_connected_at' => $tc->stripe_connected_at?->toIso8601String(),
                ];
            });

        // Instructors
        $instructors = Instructor::select('id', 'first_name', 'last_name', 'email', 'phone', 'stripe_account_id', 'stripe_connect_status', 'stripe_connected_at')
            ->when($request->has('search'), function($q) use ($request) {
                $search = $request->search;
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->has('status'), function($q) use ($request) {
                $q->where('stripe_connect_status', $request->status);
            })
            ->get()
            ->map(function($instructor) {
                return [
                    'id' => $instructor->id,
                    'name' => trim(($instructor->first_name ?? '') . ' ' . ($instructor->last_name ?? '')),
                    'email' => $instructor->email,
                    'phone' => $instructor->phone,
                    'type' => 'instructor',
                    'stripe_account_id' => $instructor->stripe_account_id,
                    'stripe_connect_status' => $instructor->stripe_connect_status,
                    'stripe_connected_at' => $instructor->stripe_connected_at?->toIso8601String(),
                ];
            });

        return array_merge(
            $accs->toArray(),
            $trainingCenters->toArray(),
            $instructors->toArray()
        );
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        $stats = [
            'total' => 0,
            'connected' => 0,
            'pending' => 0,
            'failed' => 0,
            'inactive' => 0,
            'updating' => 0,
        ];

        // Count ACCs
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

        // Count Training Centers
        $trainingCenters = TrainingCenter::selectRaw('stripe_connect_status, COUNT(*) as count')
            ->groupBy('stripe_connect_status')
            ->get();
        
        foreach ($trainingCenters as $tc) {
            $count = (int) $tc->count;
            $stats['total'] += $count;
            if ($tc->stripe_connect_status) {
                $status = $tc->stripe_connect_status;
                $stats[$status] = ($stats[$status] ?? 0) + $count;
            }
        }

        // Count Instructors
        $instructors = Instructor::selectRaw('stripe_connect_status, COUNT(*) as count')
            ->groupBy('stripe_connect_status')
            ->get();
        
        foreach ($instructors as $instructor) {
            $count = (int) $instructor->count;
            $stats['total'] += $count;
            if ($instructor->stripe_connect_status) {
                $status = $instructor->stripe_connect_status;
                $stats[$status] = ($stats[$status] ?? 0) + $count;
            }
        }

        // Calculate success rate
        $successRate = $stats['total'] > 0 
            ? round(($stats['connected'] / $stats['total']) * 100, 2)
            : 0;

        return [
            'total' => $stats['total'],
            'connected' => $stats['connected'] ?? 0,
            'pending' => $stats['pending'] ?? 0,
            'failed' => $stats['failed'] ?? 0,
            'inactive' => $stats['inactive'] ?? 0,
            'updating' => $stats['updating'] ?? 0,
            'success_rate' => $successRate,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    // ========== Helper Methods ==========

    protected function isAdmin(User $user): bool
    {
        return in_array($user->role, ['admin', 'group_admin']);
    }

    public function getAccountModel(string $accountType, int $accountId)
    {
        return match($accountType) {
            'acc' => ACC::find($accountId),
            'training_center' => TrainingCenter::find($accountId),
            'instructor' => Instructor::find($accountId),
            default => null,
        };
    }

    protected function getAccountName($account, string $accountType): string
    {
        return match($accountType) {
            'acc' => $account->name,
            'training_center' => $account->name,
            'instructor' => trim(($account->first_name ?? '') . ' ' . ($account->last_name ?? '')),
            default => 'Unknown',
        };
    }

    protected function getAccountEmail($account, string $accountType): ?string
    {
        return match($accountType) {
            'acc' => $account->email,
            'training_center' => $account->email,
            'instructor' => $account->email,
            default => null,
        };
    }

    protected function prepareAccountData($account, string $accountType): array
    {
        return match($accountType) {
            'acc' => [
                'name' => $account->legal_name ?? $account->name,
                'email' => $account->email,
                'website' => $account->website,
            ],
            'training_center' => [
                'name' => $account->legal_name ?? $account->name,
                'email' => $account->email,
                'website' => $account->website,
            ],
            'instructor' => [
                'name' => trim(($account->first_name ?? '') . ' ' . ($account->last_name ?? '')),
                'email' => $account->email,
                'website' => null,
            ],
            default => [],
        };
    }

    protected function createOnboardingLink(string $stripeAccountId): string
    {
        $accountLink = AccountLink::create([
            'account' => $stripeAccountId,
            'type' => 'account_onboarding',
            'return_url' => config('app.url') . '/stripe/connect/return',
            'refresh_url' => config('app.url') . '/stripe/connect/refresh',
        ]);

        return $accountLink->url;
    }

    protected function updateAccountStripeData($account, string $accountType, array $data): void
    {
        foreach ($data as $key => $value) {
            $account->$key = $value;
        }
        $account->save();
    }

    protected function updateAccountFromStripe($account, string $accountType, Account $stripeAccount): void
    {
        $status = 'pending';
        
        if ($stripeAccount->details_submitted && $stripeAccount->charges_enabled) {
            $status = 'connected';
        } elseif (!empty($stripeAccount->requirements->currently_due)) {
            $status = 'pending';
        }

        $requirements = [
            'currently_due' => $stripeAccount->requirements->currently_due ?? [],
            'eventually_due' => $stripeAccount->requirements->eventually_due ?? [],
            'pending_verification' => $stripeAccount->requirements->pending_verification ?? [],
        ];

        $this->updateAccountStripeData($account, $accountType, [
            'stripe_connect_status' => $status,
            'stripe_onboarding_completed' => $stripeAccount->details_submitted ?? false,
            'stripe_onboarding_completed_at' => $stripeAccount->details_submitted ? now() : null,
            'stripe_requirements' => $requirements,
            'stripe_last_status_check_at' => now(),
        ]);
    }

    protected function getBankInfo(Account $stripeAccount): ?array
    {
        try {
            $externalAccounts = \Stripe\Account::allExternalAccounts(
                $stripeAccount->id,
                ['object' => 'bank_account', 'limit' => 1]
            );

            if (!empty($externalAccounts->data)) {
                $bankAccount = $externalAccounts->data[0];
                return [
                    'bank_name' => $bankAccount->bank_name ?? 'N/A',
                    'account_number' => '****' . substr($bankAccount->last4 ?? '', -4),
                    'routing_number' => '****' . substr($bankAccount->routing_number ?? '', -3),
                    'currency' => $bankAccount->currency ?? 'USD',
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get bank info', [
                'stripe_account_id' => $stripeAccount->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function logStripeConnectAction(
        string $accountType,
        int $accountId,
        string $accountName,
        string $action,
        string $status,
        ?string $stripeAccountId,
        ?string $errorMessage,
        ?int $adminId
    ): void {
        StripeConnectLog::create([
            'account_type' => $accountType,
            'account_id' => $accountId,
            'account_name' => $accountName,
            'action' => $action,
            'status' => $status,
            'stripe_connected_account_id' => $stripeAccountId,
            'error_message' => $errorMessage,
            'performed_by_admin' => $adminId,
            'performed_at' => now(),
        ]);
    }

    protected function logAdminActivity(
        User $admin,
        string $action,
        ?string $accountType,
        ?int $accountId,
        ?string $accountName,
        string $status,
        ?string $errorMessage = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        AdminActivityLog::create([
            'admin_id' => $admin->id,
            'action' => $action,
            'account_type' => $accountType,
            'target_account_id' => $accountId,
            'target_account_name' => $accountName,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'status' => $status,
            'error_message' => $errorMessage,
            'timestamp' => now(),
        ]);
    }

    protected function sendOnboardingEmail($account, string $accountType, string $onboardingUrl): void
    {
        try {
            $email = $this->getAccountEmail($account, $accountType);
            $name = $this->getAccountName($account, $accountType);

            if ($email) {
                Mail::to($email)->send(new StripeOnboardingMail($name, $accountType, $onboardingUrl));
                
                Log::info('Stripe onboarding email sent successfully', [
                    'email' => $email,
                    'account_type' => $accountType,
                    'account_name' => $name,
                ]);
            } else {
                Log::warning('Cannot send onboarding email: no email address found', [
                    'account_type' => $accountType,
                    'account_id' => $account->id ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send onboarding email', [
                'email' => $email ?? null,
                'account_type' => $accountType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function handleAccountUpdated(array $accountData): void
    {
        $stripeAccountId = $accountData['id'] ?? null;
        
        if (!$stripeAccountId) {
            return;
        }

        // البحث عن الحساب في قاعدة البيانات
        $acc = ACC::where('stripe_account_id', $stripeAccountId)->first();
        if ($acc) {
            $stripeAccount = Account::retrieve($stripeAccountId);
            $this->updateAccountFromStripe($acc, 'acc', $stripeAccount);
            
            $this->logStripeConnectAction(
                'acc',
                $acc->id,
                $acc->name,
                'updated',
                'success',
                $stripeAccountId,
                null,
                null
            );
            return;
        }

        $tc = TrainingCenter::where('stripe_account_id', $stripeAccountId)->first();
        if ($tc) {
            $stripeAccount = Account::retrieve($stripeAccountId);
            $this->updateAccountFromStripe($tc, 'training_center', $stripeAccount);
            
            $this->logStripeConnectAction(
                'training_center',
                $tc->id,
                $tc->name,
                'updated',
                'success',
                $stripeAccountId,
                null,
                null
            );
            return;
        }

        $instructor = Instructor::where('stripe_account_id', $stripeAccountId)->first();
        if ($instructor) {
            $stripeAccount = Account::retrieve($stripeAccountId);
            $this->updateAccountFromStripe($instructor, 'instructor', $stripeAccount);
            
            $this->logStripeConnectAction(
                'instructor',
                $instructor->id,
                trim(($instructor->first_name ?? '') . ' ' . ($instructor->last_name ?? '')),
                'updated',
                'success',
                $stripeAccountId,
                null,
                null
            );
        }
    }

    protected function handleExternalAccountUpdated(array $accountData): void
    {
        // عند إضافة/تحديث حساب بنكي، نقوم بتحديث الحالة
        $stripeAccountId = $accountData['account'] ?? null;
        
        if ($stripeAccountId) {
            $this->handleAccountUpdated(['id' => $stripeAccountId]);
        }
    }

    protected function handleAccountDeauthorized(array $accountData): void
    {
        $stripeAccountId = $accountData['id'] ?? null;
        
        if (!$stripeAccountId) {
            return;
        }

        // البحث عن الحساب وتعطيله
        $acc = ACC::where('stripe_account_id', $stripeAccountId)->first();
        if ($acc) {
            $this->updateAccountStripeData($acc, 'acc', [
                'stripe_connect_status' => 'inactive',
                'stripe_last_error_message' => 'Account deauthorized',
            ]);
            return;
        }

        $tc = TrainingCenter::where('stripe_account_id', $stripeAccountId)->first();
        if ($tc) {
            $this->updateAccountStripeData($tc, 'training_center', [
                'stripe_connect_status' => 'inactive',
                'stripe_last_error_message' => 'Account deauthorized',
            ]);
            return;
        }

        $instructor = Instructor::where('stripe_account_id', $stripeAccountId)->first();
        if ($instructor) {
            $this->updateAccountStripeData($instructor, 'instructor', [
                'stripe_connect_status' => 'inactive',
                'stripe_last_error_message' => 'Account deauthorized',
            ]);
        }
    }
}

