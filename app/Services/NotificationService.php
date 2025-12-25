<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to a user
     */
    public function send(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): Notification {
        try {
            return Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'is_read' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToMany(
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): void {
        foreach ($userIds as $userId) {
            try {
                $this->send($userId, $type, $title, $message, $data);
            } catch (\Exception $e) {
                Log::error('Failed to send notification to user', [
                    'user_id' => $userId,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other users even if one fails
            }
        }
    }

    /**
     * Send notification to all users with a specific role
     */
    public function sendToRole(
        string $role,
        string $type,
        string $title,
        string $message,
        ?array $data = null
    ): void {
        $userIds = User::where('role', $role)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        $this->sendToMany($userIds, $type, $title, $message, $data);
    }

    // Specific notification methods

    /**
     * Notify admin about new ACC application
     */
    public function notifyAdminNewAccApplication(int $accId, string $accName): void
    {
        $this->sendToRole(
            'group_admin',
            'acc_application',
            'New ACC Application',
            "A new ACC application has been submitted: {$accName}",
            ['acc_id' => $accId]
        );
    }

    /**
     * Notify ACC about approval
     */
    public function notifyAccApproved(int $userId, int $accId, string $accName): void
    {
        $this->send(
            $userId,
            'acc_approved',
            'ACC Application Approved',
            "Your ACC application for '{$accName}' has been approved. You can now access your workspace.",
            ['acc_id' => $accId]
        );
    }

    /**
     * Notify ACC about rejection
     */
    public function notifyAccRejected(int $userId, int $accId, string $accName, string $reason): void
    {
        $this->send(
            $userId,
            'acc_rejected',
            'ACC Application Rejected',
            "Your ACC application for '{$accName}' has been rejected. Reason: {$reason}",
            ['acc_id' => $accId, 'reason' => $reason]
        );
    }

    /**
     * Notify ACC about subscription payment success
     */
    public function notifySubscriptionPaid(int $userId, int $subscriptionId, float $amount): void
    {
        $this->send(
            $userId,
            'subscription_paid',
            'Subscription Payment Successful',
            "Your subscription payment of $" . number_format($amount, 2) . " has been processed successfully.",
            ['subscription_id' => $subscriptionId, 'amount' => $amount]
        );
    }

    /**
     * Notify ACC about subscription expiring soon
     */
    public function notifySubscriptionExpiring(int $userId, int $subscriptionId, string $expiryDate): void
    {
        $this->send(
            $userId,
            'subscription_expiring',
            'Subscription Expiring Soon',
            "Your subscription will expire on {$expiryDate}. Please renew to continue using the platform.",
            ['subscription_id' => $subscriptionId, 'expiry_date' => $expiryDate]
        );
    }

    /**
     * Notify ACC about new instructor authorization request
     */
    public function notifyInstructorAuthorizationRequested(int $userId, int $authorizationId, string $instructorName, string $trainingCenterName): void
    {
        $this->send(
            $userId,
            'instructor_authorization_requested',
            'New Instructor Authorization Request',
            "{$trainingCenterName} has requested authorization for instructor: {$instructorName}",
            ['authorization_id' => $authorizationId, 'instructor_name' => $instructorName, 'training_center_name' => $trainingCenterName]
        );
    }

    /**
     * Notify Training Center about instructor authorization approval
     */
    public function notifyInstructorAuthorized(int $userId, int $authorizationId, string $instructorName, string $accName): void
    {
        $this->send(
            $userId,
            'instructor_authorized',
            'Instructor Authorization Approved',
            "Instructor '{$instructorName}' has been authorized by {$accName}. You can now proceed with payment.",
            ['authorization_id' => $authorizationId, 'instructor_name' => $instructorName, 'acc_name' => $accName]
        );
    }

    /**
     * Notify Training Center about instructor authorization rejection
     */
    public function notifyInstructorAuthorizationRejected(int $userId, int $authorizationId, string $instructorName, string $reason): void
    {
        $this->send(
            $userId,
            'instructor_authorization_rejected',
            'Instructor Authorization Rejected',
            "The authorization request for instructor '{$instructorName}' has been rejected. Reason: {$reason}",
            ['authorization_id' => $authorizationId, 'instructor_name' => $instructorName, 'reason' => $reason]
        );
    }

    /**
     * Notify Training Center about code purchase success
     */
    public function notifyCodePurchaseSuccess(int $userId, int $batchId, int $quantity, float $amount): void
    {
        $this->send(
            $userId,
            'code_purchased',
            'Certificate Codes Purchased',
            "You have successfully purchased {$quantity} certificate code(s) for $" . number_format($amount, 2) . ".",
            ['batch_id' => $batchId, 'quantity' => $quantity, 'amount' => $amount]
        );
    }

    /**
     * Notify Training Center about new authorization request from ACC
     */
    public function notifyTrainingCenterAuthorizationRequested(int $userId, int $authorizationId, string $accName): void
    {
        $this->send(
            $userId,
            'training_center_authorization_requested',
            'New Authorization Request',
            "{$accName} has requested authorization for your training center.",
            ['authorization_id' => $authorizationId, 'acc_name' => $accName]
        );
    }

    /**
     * Notify Training Center about authorization approval
     */
    public function notifyTrainingCenterAuthorized(int $userId, int $authorizationId, string $accName): void
    {
        $this->send(
            $userId,
            'training_center_authorized',
            'Authorization Approved',
            "Your authorization request with {$accName} has been approved.",
            ['authorization_id' => $authorizationId, 'acc_name' => $accName]
        );
    }

    /**
     * Notify Training Center about authorization rejection
     */
    public function notifyTrainingCenterAuthorizationRejected(int $userId, int $authorizationId, string $accName, string $reason): void
    {
        $this->send(
            $userId,
            'training_center_authorization_rejected',
            'Authorization Rejected',
            "Your authorization request with {$accName} has been rejected. Reason: {$reason}",
            ['authorization_id' => $authorizationId, 'acc_name' => $accName, 'reason' => $reason]
        );
    }

    /**
     * Notify Admin about instructor authorization payment
     */
    public function notifyInstructorAuthorizationPaid(int $authorizationId, string $instructorName, float $amount): void
    {
        $this->sendToRole(
            'group_admin',
            'instructor_authorization_paid',
            'Instructor Authorization Payment Received',
            "Payment of $" . number_format($amount, 2) . " received for instructor authorization: {$instructorName}",
            ['authorization_id' => $authorizationId, 'instructor_name' => $instructorName, 'amount' => $amount]
        );
    }

    /**
     * Notify Admin about code purchase
     */
    public function notifyAdminCodePurchase(int $batchId, string $trainingCenterName, int $quantity, float $amount): void
    {
        $this->sendToRole(
            'group_admin',
            'code_purchase_admin',
            'Certificate Codes Purchased',
            "{$trainingCenterName} purchased {$quantity} certificate code(s) for $" . number_format($amount, 2) . ".",
            ['batch_id' => $batchId, 'training_center_name' => $trainingCenterName, 'quantity' => $quantity, 'amount' => $amount]
        );
    }

    /**
     * Notify ACC about code purchase (commission)
     */
    public function notifyAccCodePurchase(int $userId, int $batchId, string $trainingCenterName, int $quantity, float $amount, float $commission): void
    {
        $this->send(
            $userId,
            'code_purchase_acc',
            'Certificate Codes Purchased',
            "{$trainingCenterName} purchased {$quantity} certificate code(s). Your commission: $" . number_format($commission, 2) . ".",
            ['batch_id' => $batchId, 'training_center_name' => $trainingCenterName, 'quantity' => $quantity, 'amount' => $amount, 'commission' => $commission]
        );
    }
}

