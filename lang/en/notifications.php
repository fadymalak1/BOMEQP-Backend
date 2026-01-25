<?php

return [
    // ACC Notifications
    'acc_application' => [
        'title' => 'New ACC Application',
        'message' => 'A new ACC application has been submitted: :accName',
    ],
    'acc_approved' => [
        'title' => 'ACC Application Approved',
        'message' => 'Your ACC application for \':accName\' has been approved. You can now access your workspace.',
    ],
    'acc_rejected' => [
        'title' => 'ACC Application Rejected',
        'message' => 'Your ACC application for \':accName\' has been rejected. Reason: :reason',
    ],
    'acc_status_changed' => [
        'title' => 'ACC Status Changed',
        'message' => 'Your ACC \':accName\' has been :action.:reason',
    ],

    // Training Center Notifications
    'training_center_application' => [
        'title' => 'New Training Center Application',
        'message' => 'A new Training Center application has been submitted: :trainingCenterName',
    ],
    'training_center_approved' => [
        'title' => 'Training Center Application Approved',
        'message' => 'Your Training Center application for \':trainingCenterName\' has been approved. You can now access your workspace.',
    ],
    'training_center_rejected' => [
        'title' => 'Training Center Application Rejected',
        'message' => 'Your Training Center application for \':trainingCenterName\' has been rejected. Reason: :reason',
    ],
    'training_center_status_changed' => [
        'title' => 'Training Center Status Changed',
        'message' => 'Your Training Center \':trainingCenterName\' has been :action.:reason',
    ],

    // Subscription Notifications
    'subscription_paid' => [
        'title' => 'Subscription Payment Successful',
        'message' => 'Your subscription payment of $:amount has been processed successfully.',
    ],
    'subscription_payment' => [
        'title' => 'ACC Subscription Payment Received',
        'message' => ':accName has paid their subscription. Payment amount: $:amount.',
    ],
    'subscription_renewal' => [
        'title' => 'ACC Subscription Renewed',
        'message' => ':accName has renewed their subscription. Payment amount: $:amount.',
    ],
    'subscription_expiring' => [
        'title' => 'Subscription Expiring Soon',
        'message' => 'Your subscription will expire on :expiryDate. Please renew to continue using the platform.',
    ],

    // Instructor Authorization Notifications
    'instructor_authorization_requested' => [
        'title' => 'New Instructor Authorization Request',
        'message' => ':trainingCenterName has requested authorization for instructor: :instructorName:coursesInfo.',
    ],
    'instructor_authorized' => [
        'title' => 'Instructor Authorization Approved',
        'message' => 'Instructor \':instructorName\' has been authorized by :accName.:priceInfo:commissionInfo:coursesInfo You can now proceed with payment.',
    ],
    'instructor_authorization_payment_success' => [
        'title' => 'Payment Successful',
        'message' => 'Payment of $:amount for instructor \':instructorName\' authorization has been processed successfully. The instructor is now officially authorized.',
    ],
    'instructor_authorization_rejected' => [
        'title' => 'Instructor Authorization Rejected',
        'message' => 'The authorization request for instructor \':instructorName\' has been rejected. Reason: :reason',
    ],
    'instructor_authorization_returned' => [
        'title' => 'Instructor Authorization Request Returned',
        'message' => 'Your authorization request for instructor \':instructorName\' with :accName has been returned for revision. Comment: :comment',
    ],
    'instructor_commission_set' => [
        'title' => 'Commission Set - Ready for Payment',
        'message' => 'Commission has been set for instructor \':instructorName\' authorization with :accName. Authorization price: $:authorizationPrice, Commission: :commissionPercentage%, Courses: :coursesCount. You can now proceed with payment.',
    ],
    'instructor_needs_commission' => [
        'title' => 'New Instructor Authorization - Commission Required',
        'message' => 'Instructor \':instructorName\' has been approved by :accName. Please set the commission percentage. Authorization price: $:authorizationPrice.',
    ],
    'instructor_authorization_paid' => [
        'title' => 'Instructor Authorization Payment Received',
        'message' => 'Payment of $:amount received for instructor authorization: :instructorName:commissionInfo',
    ],
    'instructor_status_changed' => [
        'title' => 'Instructor Status Changed',
        'message' => 'Your instructor account \':instructorName\' at :trainingCenterName has been :action.:reason',
    ],

    // Training Center Authorization Notifications
    'training_center_authorization_requested' => [
        'title' => 'New Authorization Request',
        'message' => ':trainingCenterName:locationInfo has requested authorization with your ACC.',
    ],
    'training_center_authorized' => [
        'title' => 'Authorization Approved',
        'message' => 'Your authorization request with :accName has been approved.',
    ],
    'training_center_authorization_rejected' => [
        'title' => 'Authorization Rejected',
        'message' => 'Your authorization request with :accName has been rejected. Reason: :reason',
    ],
    'training_center_authorization_returned' => [
        'title' => 'Authorization Request Returned',
        'message' => 'Your authorization request with :accName has been returned for revision. Comment: :comment',
    ],

    // Code Purchase Notifications
    'code_purchased' => [
        'title' => 'Certificate Codes Purchased',
        'message' => 'You have successfully purchased :quantity certificate code(s) for $:amount.',
    ],
    'code_purchase_admin' => [
        'title' => 'Certificate Codes Purchased',
        'message' => ':trainingCenterName purchased :quantity certificate code(s) for $:amount.:commissionInfo',
    ],
    'code_purchase_acc' => [
        'title' => 'Certificate Codes Purchased',
        'message' => ':trainingCenterName purchased :quantity certificate code(s). Your commission: $:commission.',
    ],

    // Certificate Notifications
    'certificate_generated' => [
        'title' => 'Certificate Generated',
        'message' => 'A new certificate has been generated by :trainingCenterName. Certificate Number: :certificateNumber, Trainee: :traineeName, Course: :courseName.',
    ],
    'certificate_generated_instructor' => [
        'title' => 'Certificate Generated for Your Class',
        'message' => 'A certificate has been generated for trainee :traineeName in course :courseName by :trainingCenterName. Certificate Number: :certificateNumber.',
    ],
    'certificate_generated_admin' => [
        'title' => 'Certificate Generated',
        'message' => 'A new certificate has been generated. Certificate Number: :certificateNumber, Trainee: :traineeName, Course: :courseName, Training Center: :trainingCenterName, ACC: :accName.',
    ],

    // Class Notifications
    'class_completed' => [
        'title' => 'Class Completed',
        'message' => 'Class \':className\' for course \':courseName\' has been marked as completed by :trainingCenterName. Completion rate: :completionRate%.',
    ],

    // Commission Notifications
    'commission_received' => [
        'title' => 'Commission Received',
        'message' => 'Commission of $:commissionAmount received from :typeLabel:payerInfo:payeeInfo. Total transaction amount: $:totalAmount.',
    ],

    // Stripe Connect Notifications
    'stripe_onboarding_link_sent' => [
        'title' => 'Stripe Connect Setup Required',
        'message' => 'A Stripe Connect onboarding link has been sent to your email for your :accountTypeLabel account. Please check your email and complete the setup to start receiving payments.',
    ],

    // Manual Payment Notifications
    'manual_payment_request' => [
        'title' => 'Manual Payment Request',
        'message' => ':trainingCenterName has submitted a manual payment request for :quantity certificate code(s) totaling $:amount. Please review and verify the payment receipt.',
    ],
    'manual_payment_request_admin' => [
        'title' => 'Manual Payment Request',
        'message' => ':trainingCenterName has submitted a manual payment request for :quantity certificate code(s) totaling $:amount. Please review and verify the payment receipt.',
    ],
    'manual_payment_pending' => [
        'title' => 'Payment Request Submitted',
        'message' => 'Your payment request for :quantity certificate code(s) totaling $:amount has been submitted and is pending approval. You will be notified once it\'s reviewed.',
    ],
    'manual_payment_approved' => [
        'title' => 'Payment Approved',
        'message' => 'Your payment request for :quantity certificate code(s) totaling $:amount has been approved. Your codes are now available.',
    ],
    'manual_payment_rejected' => [
        'title' => 'Payment Rejected',
        'message' => 'Your payment request for :quantity certificate code(s) totaling $:amount has been rejected. Reason: :reason',
    ],

    // Status actions
    'status_actions' => [
        'suspended' => 'suspended',
        'active' => 'reactivated',
        'inactive' => 'deactivated',
        'expired' => 'expired',
        'rejected' => 'rejected',
    ],
];

