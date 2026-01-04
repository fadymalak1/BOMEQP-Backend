<?php

namespace App\Services;

use App\Models\ACC;
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
     * @return array
     */
    public function approveApplication(ACC $acc, int $approvedBy): array
    {
        try {
            DB::beginTransaction();

            $acc->update([
                'status' => 'active',
                'approved_at' => now(),
                'approved_by' => $approvedBy,
            ]);

            // Activate the user account associated with this ACC
            $user = User::where('email', $acc->email)->first();
            if ($user && $user->role === 'acc_admin') {
                $user->update(['status' => 'active']);
                
                // Send notification to ACC admin
                $this->notificationService->notifyAccApproved($user->id, $acc->id, $acc->name);
            }

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
}

