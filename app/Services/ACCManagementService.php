<?php

namespace App\Services;

use App\Models\ACC;
use App\Models\ACCSubscription;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ACCManagementService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Approve ACC application
     *
     * @param ACC $acc
     * @param int $approvedBy
     * @param float $commissionPercentage Commission percentage (required)
     * @param float $subscriptionPrice Subscription price (required)
     * @return array
     */
    public function approveApplication(ACC $acc, int $approvedBy, float $commissionPercentage, float $subscriptionPrice): array
    {
        try {
            DB::beginTransaction();

            // Ensure values are properly cast
            $commissionPercentage = (float) $commissionPercentage;
            $subscriptionPrice = (float) $subscriptionPrice;

            Log::info('Updating ACC with commission percentage and subscription price', [
                'acc_id' => $acc->id,
                'commission_percentage' => $commissionPercentage,
                'subscription_price' => $subscriptionPrice,
            ]);

            // Update using save() to ensure all attributes are saved
            // Set status to 'approved' (not 'active') - ACC needs activation before they can work
            $acc->status = 'approved';
            $acc->approved_at = now();
            $acc->approved_by = $approvedBy;
            $acc->commission_percentage = $commissionPercentage;
            $acc->subscription_price = $subscriptionPrice;
            $acc->save();

            // Refresh to get updated values from database
            $acc->refresh();

            Log::info('ACC approved (pending activation)', [
                'acc_id' => $acc->id,
                'commission_percentage' => $acc->commission_percentage,
                'subscription_price' => $acc->subscription_price,
                'status' => $acc->status,
            ]);

            // Don't activate user account yet - wait for activation step
            // Don't send approval notification yet - will be sent on activation

            DB::commit();

            return [
                'success' => true,
                'acc' => $acc->fresh(),
                'message' => 'ACC application approved'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve ACC application', [
                'acc_id' => $acc->id,
                'commission_percentage' => $commissionPercentage,
                'subscription_price' => $subscriptionPrice,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Reject ACC application
     *
     * @param ACC $acc
     * @param string $rejectionReason
     * @param int $rejectedBy
     * @return array
     */
    public function rejectApplication(ACC $acc, string $rejectionReason, int $rejectedBy): array
    {
        try {
            DB::beginTransaction();

            $acc->update([
                'status' => 'rejected',
                'rejection_reason' => $rejectionReason,
                'approved_by' => $rejectedBy,
            ]);

            // Send notification to ACC admin
            $user = User::where('email', $acc->email)->first();
            if ($user && $user->role === 'acc_admin') {
                $this->notificationService->notifyAccRejected(
                    $user->id,
                    $acc->id,
                    $acc->name,
                    $rejectionReason
                );
            }

            DB::commit();

            return [
                'success' => true,
                'acc' => $acc->fresh(),
                'message' => 'ACC application rejected'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject ACC application', [
                'acc_id' => $acc->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Set commission percentage for ACC
     *
     * @param ACC $acc
     * @param float $commissionPercentage
     * @return array
     */
    public function setCommissionPercentage(ACC $acc, float $commissionPercentage): array
    {
        try {
            $acc->update([
                'commission_percentage' => $commissionPercentage,
            ]);

            return [
                'success' => true,
                'acc' => $acc->fresh(),
                'message' => 'Commission percentage set successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to set commission percentage', [
                'acc_id' => $acc->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Assign category to ACC
     *
     * @param ACC $acc
     * @param Category $category
     * @return array
     */
    public function assignCategory(ACC $acc, Category $category): array
    {
        // Check if already assigned
        if ($acc->categories()->where('category_id', $category->id)->exists()) {
            return [
                'success' => false,
                'message' => 'Category is already assigned to this ACC',
                'code' => 400
            ];
        }

        try {
            DB::beginTransaction();

            $acc->categories()->attach($category->id);

            DB::commit();

            return [
                'success' => true,
                'acc' => $acc->fresh()->load('categories'),
                'message' => 'Category assigned successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign category to ACC', [
                'acc_id' => $acc->id,
                'category_id' => $category->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Remove category from ACC
     *
     * @param ACC $acc
     * @param Category $category
     * @return array
     */
    public function removeCategory(ACC $acc, Category $category): array
    {
        // Check if category is assigned
        if (!$acc->categories()->where('category_id', $category->id)->exists()) {
            return [
                'success' => false,
                'message' => 'Category is not assigned to this ACC',
                'code' => 400
            ];
        }

        try {
            DB::beginTransaction();

            $acc->categories()->detach($category->id);

            DB::commit();

            return [
                'success' => true,
                'acc' => $acc->fresh()->load('categories'),
                'message' => 'Category removed successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove category from ACC', [
                'acc_id' => $acc->id,
                'category_id' => $category->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check if ACC is one of the special accounts that get lifetime subscriptions
     *
     * @param ACC $acc
     * @return bool
     */
    private function isSpecialAccAccount(ACC $acc): bool
    {
        $specialEmails = [
            'support@iaoshuk.com',
            'support@rsles.com',
            'support@bseca.com',
            'support@bilpm.com',
            'support@bsape.com',
            'support@bocaq.com',
            'support@bihhm.com',
        ];

        return in_array($acc->email, $specialEmails);
    }

    /**
     * Activate ACC (second step after approval)
     * This allows the ACC to start working
     *
     * @param ACC $acc
     * @param int $activatedBy
     * @return array
     */
    public function activateACC(ACC $acc, int $activatedBy): array
    {
        try {
            DB::beginTransaction();

            // Check if ACC is in 'approved' status
            if ($acc->status !== 'approved') {
                return [
                    'success' => false,
                    'message' => 'ACC must be approved before activation. Current status: ' . $acc->status,
                    'code' => 400
                ];
            }

            // Activate the ACC
            $acc->status = 'active';
            $acc->save();

            // Activate the user account associated with this ACC
            $user = User::where('email', $acc->email)->first();
            if ($user && $user->role === 'acc_admin') {
                $user->update(['status' => 'active']);
                
                // Send notification to ACC admin
                $this->notificationService->notifyAccApproved($user->id, $acc->id, $acc->name);
            }

            // Check if this is a special ACC account that should get lifetime subscription
            if ($this->isSpecialAccAccount($acc)) {
                // Check if subscription already exists
                $existingSubscription = ACCSubscription::where('acc_id', $acc->id)
                    ->where('payment_status', 'paid')
                    ->first();

                if (!$existingSubscription) {
                    // Create lifetime subscription (set end date to far future: 2099-12-31)
                    $lifetimeEndDate = \Carbon\Carbon::create(2099, 12, 31);
                    
                    ACCSubscription::create([
                        'acc_id' => $acc->id,
                        'subscription_start_date' => now(),
                        'subscription_end_date' => $lifetimeEndDate,
                        'renewal_date' => $lifetimeEndDate,
                        'amount' => 0.00, // Free lifetime subscription
                        'payment_status' => 'paid',
                        'payment_date' => now(),
                        'payment_method' => 'bank_transfer', // Marked as bank transfer (free)
                        'transaction_id' => 'LIFETIME-' . strtoupper(uniqid()),
                        'auto_renew' => false, // No need to auto-renew lifetime subscription
                    ]);

                    Log::info('Lifetime subscription created for special ACC account', [
                        'acc_id' => $acc->id,
                        'acc_email' => $acc->email,
                    ]);
                }
            }

            DB::commit();

            Log::info('ACC activated successfully', [
                'acc_id' => $acc->id,
                'activated_by' => $activatedBy,
                'is_special_account' => $this->isSpecialAccAccount($acc),
            ]);

            return [
                'success' => true,
                'acc' => $acc->fresh(),
                'message' => 'ACC activated successfully. ACC can now start working.'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to activate ACC', [
                'acc_id' => $acc->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

