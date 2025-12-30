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
     * Notify admin about new Training Center application
     */
    public function notifyAdminNewTrainingCenterApplication(int $trainingCenterId, string $trainingCenterName): void
    {
        $this->sendToRole(
            'group_admin',
            'training_center_application',
            'New Training Center Application',
            "A new Training Center application has been submitted: {$trainingCenterName}",
            ['training_center_id' => $trainingCenterId]
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
     * Notify Admin about ACC subscription payment
     */
    public function notifyAdminSubscriptionPaid(int $accId, string $accName, float $amount, bool $isRenewal = false): void
    {
        $type = $isRenewal ? 'subscription_renewal' : 'subscription_payment';
        $title = $isRenewal ? 'ACC Subscription Renewed' : 'ACC Subscription Payment Received';
        $message = $isRenewal 
            ? "{$accName} has renewed their subscription. Payment amount: $" . number_format($amount, 2) . "."
            : "{$accName} has paid their subscription. Payment amount: $" . number_format($amount, 2) . ".";

        $this->sendToRole(
            'group_admin',
            $type,
            $title,
            $message,
            ['acc_id' => $accId, 'acc_name' => $accName, 'amount' => $amount, 'is_renewal' => $isRenewal]
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
     * Notify ACC about new instructor authorization request (Enhanced with course/sub-category details)
     */
    public function notifyInstructorAuthorizationRequested(int $userId, int $authorizationId, string $instructorName, string $trainingCenterName, ?int $subCategoryId = null, ?array $courseIds = null, ?string $subCategoryName = null, ?int $coursesCount = null): void
    {
        $coursesInfo = '';
        if ($subCategoryId && $subCategoryName) {
            $coursesInfo = " for all courses in sub-category: {$subCategoryName}";
        } elseif ($coursesCount) {
            $coursesInfo = " for {$coursesCount} course(s)";
        }

        $message = "{$trainingCenterName} has requested authorization for instructor: {$instructorName}{$coursesInfo}.";
        
        $data = [
            'authorization_id' => $authorizationId,
            'instructor_name' => $instructorName,
            'training_center_name' => $trainingCenterName,
        ];
        
        if ($subCategoryId) {
            $data['sub_category_id'] = $subCategoryId;
            $data['sub_category_name'] = $subCategoryName;
        }
        
        if ($courseIds) {
            $data['course_ids'] = $courseIds;
            $data['courses_count'] = count($courseIds);
        }

        $this->send(
            $userId,
            'instructor_authorization_requested',
            'New Instructor Authorization Request',
            $message,
            $data
        );
    }

    /**
     * Notify Training Center about instructor authorization approval (Enhanced with commission details)
     */
    public function notifyInstructorAuthorized(int $userId, int $authorizationId, string $instructorName, string $accName, ?float $authorizationPrice = null, ?float $commissionPercentage = null, ?int $coursesCount = null): void
    {
        $priceInfo = $authorizationPrice ? " Authorization price: $" . number_format($authorizationPrice, 2) . "." : "";
        $commissionInfo = $commissionPercentage ? " Commission: {$commissionPercentage}%." : "";
        $coursesInfo = $coursesCount ? " Courses authorized: {$coursesCount}." : "";
        
        $message = "Instructor '{$instructorName}' has been authorized by {$accName}.{$priceInfo}{$commissionInfo}{$coursesInfo} You can now proceed with payment.";
        
        $data = [
            'authorization_id' => $authorizationId,
            'instructor_name' => $instructorName,
            'acc_name' => $accName,
        ];
        
        if ($authorizationPrice) {
            $data['authorization_price'] = $authorizationPrice;
        }
        
        if ($commissionPercentage) {
            $data['commission_percentage'] = $commissionPercentage;
        }
        
        if ($coursesCount) {
            $data['courses_count'] = $coursesCount;
        }

        $this->send(
            $userId,
            'instructor_authorized',
            'Instructor Authorization Approved',
            $message,
            $data
        );
    }

    /**
     * Notify Training Center about successful instructor authorization payment
     */
    public function notifyInstructorAuthorizationPaymentSuccess(int $userId, int $authorizationId, string $instructorName, float $amount): void
    {
        $this->send(
            $userId,
            'instructor_authorization_payment_success',
            'Payment Successful',
            "Payment of $" . number_format($amount, 2) . " for instructor '{$instructorName}' authorization has been processed successfully. The instructor is now officially authorized.",
            ['authorization_id' => $authorizationId, 'instructor_name' => $instructorName, 'amount' => $amount]
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
     * Notify Training Center about authorization return
     */
    public function notifyTrainingCenterAuthorizationReturned(int $userId, int $authorizationId, string $accName, string $comment): void
    {
        $this->send(
            $userId,
            'training_center_authorization_returned',
            'Authorization Request Returned',
            "Your authorization request with {$accName} has been returned for revision. Comment: {$comment}",
            ['authorization_id' => $authorizationId, 'acc_name' => $accName, 'comment' => $comment]
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
     * Notify Admin that instructor authorization is approved and needs commission setting
     */
    public function notifyAdminInstructorNeedsCommission(int $authorizationId, string $instructorName, string $accName, float $authorizationPrice): void
    {
        $this->sendToRole(
            'group_admin',
            'instructor_needs_commission',
            'New Instructor Authorization - Commission Required',
            "Instructor '{$instructorName}' has been approved by {$accName}. Please set the commission percentage. Authorization price: $" . number_format($authorizationPrice, 2) . ".",
            [
                'authorization_id' => $authorizationId,
                'instructor_name' => $instructorName,
                'acc_name' => $accName,
                'authorization_price' => $authorizationPrice
            ]
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

    /**
     * Notify Training Center about approval
     */
    public function notifyTrainingCenterApproved(int $userId, int $trainingCenterId, string $trainingCenterName): void
    {
        $this->send(
            $userId,
            'training_center_approved',
            'Training Center Application Approved',
            "Your Training Center application for '{$trainingCenterName}' has been approved. You can now access your workspace.",
            ['training_center_id' => $trainingCenterId]
        );
    }

    /**
     * Notify Training Center about rejection
     */
    public function notifyTrainingCenterRejected(int $userId, int $trainingCenterId, string $trainingCenterName, string $reason): void
    {
        $this->send(
            $userId,
            'training_center_rejected',
            'Training Center Application Rejected',
            "Your Training Center application for '{$trainingCenterName}' has been rejected. Reason: {$reason}",
            ['training_center_id' => $trainingCenterId, 'reason' => $reason]
        );
    }

    /**
     * Notify Training Center about instructor authorization return
     */
    public function notifyInstructorAuthorizationReturned(int $userId, int $authorizationId, string $instructorName, string $accName, string $comment): void
    {
        $this->send(
            $userId,
            'instructor_authorization_returned',
            'Instructor Authorization Request Returned',
            "Your authorization request for instructor '{$instructorName}' with {$accName} has been returned for revision. Comment: {$comment}",
            ['authorization_id' => $authorizationId, 'instructor_name' => $instructorName, 'acc_name' => $accName, 'comment' => $comment]
        );
    }

    /**
     * Notify Training Center about commission set (ready for payment)
     */
    public function notifyInstructorCommissionSet(int $userId, int $authorizationId, string $instructorName, string $accName, float $authorizationPrice, float $commissionPercentage, int $coursesCount): void
    {
        $this->send(
            $userId,
            'instructor_commission_set',
            'Commission Set - Ready for Payment',
            "Commission has been set for instructor '{$instructorName}' authorization with {$accName}. Authorization price: $" . number_format($authorizationPrice, 2) . ", Commission: {$commissionPercentage}%, Courses: {$coursesCount}. You can now proceed with payment.",
            [
                'authorization_id' => $authorizationId,
                'instructor_name' => $instructorName,
                'acc_name' => $accName,
                'authorization_price' => $authorizationPrice,
                'commission_percentage' => $commissionPercentage,
                'courses_count' => $coursesCount
            ]
        );
    }

    /**
     * Notify ACC about certificate generation
     */
    public function notifyCertificateGenerated(int $userId, int $certificateId, string $certificateNumber, string $traineeName, string $courseName, string $trainingCenterName): void
    {
        $this->send(
            $userId,
            'certificate_generated',
            'Certificate Generated',
            "A new certificate has been generated by {$trainingCenterName}. Certificate Number: {$certificateNumber}, Trainee: {$traineeName}, Course: {$courseName}.",
            [
                'certificate_id' => $certificateId,
                'certificate_number' => $certificateNumber,
                'trainee_name' => $traineeName,
                'course_name' => $courseName,
                'training_center_name' => $trainingCenterName
            ]
        );
    }

    /**
     * Notify Instructor about certificate generation
     */
    public function notifyInstructorCertificateGenerated(int $userId, int $certificateId, string $certificateNumber, string $traineeName, string $courseName, string $trainingCenterName): void
    {
        $this->send(
            $userId,
            'certificate_generated_instructor',
            'Certificate Generated for Your Class',
            "A certificate has been generated for trainee {$traineeName} in course {$courseName} by {$trainingCenterName}. Certificate Number: {$certificateNumber}.",
            [
                'certificate_id' => $certificateId,
                'certificate_number' => $certificateNumber,
                'trainee_name' => $traineeName,
                'course_name' => $courseName,
                'training_center_name' => $trainingCenterName
            ]
        );
    }

    /**
     * Notify Instructor about class completion
     */
    public function notifyInstructorClassCompleted(int $userId, int $classId, string $className, string $courseName, string $trainingCenterName, int $completionRate = 100): void
    {
        $this->send(
            $userId,
            'class_completed',
            'Class Completed',
            "Class '{$className}' for course '{$courseName}' has been marked as completed by {$trainingCenterName}. Completion rate: {$completionRate}%.",
            [
                'class_id' => $classId,
                'class_name' => $className,
                'course_name' => $courseName,
                'training_center_name' => $trainingCenterName,
                'completion_rate' => $completionRate
            ]
        );
    }

    /**
     * Notify Admin about certificate generation
     */
    public function notifyAdminCertificateGenerated(int $certificateId, string $certificateNumber, string $traineeName, string $courseName, string $trainingCenterName, string $accName): void
    {
        $this->sendToRole(
            'group_admin',
            'certificate_generated_admin',
            'Certificate Generated',
            "A new certificate has been generated. Certificate Number: {$certificateNumber}, Trainee: {$traineeName}, Course: {$courseName}, Training Center: {$trainingCenterName}, ACC: {$accName}.",
            [
                'certificate_id' => $certificateId,
                'certificate_number' => $certificateNumber,
                'trainee_name' => $traineeName,
                'course_name' => $courseName,
                'training_center_name' => $trainingCenterName,
                'acc_name' => $accName
            ]
        );
    }

    /**
     * Notify ACC Admin about new training center authorization request (Enhanced)
     */
    public function notifyTrainingCenterAuthorizationRequested(int $userId, int $authorizationId, string $trainingCenterName, ?string $country = null, ?string $city = null): void
    {
        $locationInfo = '';
        if ($country || $city) {
            $locationParts = array_filter([$city, $country]);
            $locationInfo = ' (' . implode(', ', $locationParts) . ')';
        }
        
        $message = "{$trainingCenterName}{$locationInfo} has requested authorization with your ACC.";
        
        $data = [
            'authorization_id' => $authorizationId,
            'training_center_name' => $trainingCenterName,
        ];
        
        if ($country) {
            $data['country'] = $country;
        }
        
        if ($city) {
            $data['city'] = $city;
        }

        $this->send(
            $userId,
            'training_center_authorization_requested',
            'New Authorization Request',
            $message,
            $data
        );
    }

    /**
     * Notify ACC about status change
     */
    public function notifyAccStatusChanged(int $userId, int $accId, string $accName, string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        $statusMessages = [
            'suspended' => 'suspended',
            'active' => 'reactivated',
            'expired' => 'expired',
            'rejected' => 'rejected',
        ];
        
        $action = $statusMessages[$newStatus] ?? $newStatus;
        $message = "Your ACC '{$accName}' has been {$action}.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }
        
        $this->send(
            $userId,
            'acc_status_changed',
            'ACC Status Changed',
            $message,
            [
                'acc_id' => $accId,
                'acc_name' => $accName,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason
            ]
        );
    }

    /**
     * Notify Training Center about status change
     */
    public function notifyTrainingCenterStatusChanged(int $userId, int $trainingCenterId, string $trainingCenterName, string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        $statusMessages = [
            'suspended' => 'suspended',
            'active' => 'reactivated',
            'inactive' => 'deactivated',
        ];
        
        $action = $statusMessages[$newStatus] ?? $newStatus;
        $message = "Your Training Center '{$trainingCenterName}' has been {$action}.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }
        
        $this->send(
            $userId,
            'training_center_status_changed',
            'Training Center Status Changed',
            $message,
            [
                'training_center_id' => $trainingCenterId,
                'training_center_name' => $trainingCenterName,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason
            ]
        );
    }

    /**
     * Notify Instructor about status change
     */
    public function notifyInstructorStatusChanged(int $userId, int $instructorId, string $instructorName, string $trainingCenterName, string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        $statusMessages = [
            'suspended' => 'suspended',
            'active' => 'reactivated',
            'inactive' => 'deactivated',
        ];
        
        $action = $statusMessages[$newStatus] ?? $newStatus;
        $message = "Your instructor account '{$instructorName}' at {$trainingCenterName} has been {$action}.";
        if ($reason) {
            $message .= " Reason: {$reason}";
        }
        
        $this->send(
            $userId,
            'instructor_status_changed',
            'Instructor Status Changed',
            $message,
            [
                'instructor_id' => $instructorId,
                'instructor_name' => $instructorName,
                'training_center_name' => $trainingCenterName,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason
            ]
        );
    }
}

