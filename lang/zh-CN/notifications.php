<?php

return [
    // ACC Notifications
    'acc_application' => [
        'title' => '新的 ACC 申请',
        'message' => '已提交新的 ACC 申请：:accName',
    ],
    'acc_approved' => [
        'title' => 'ACC 申请已批准',
        'message' => '您的 ACC 申请 \':accName\' 已被批准。您现在可以访问您的工作区。',
    ],
    'acc_rejected' => [
        'title' => 'ACC 申请被拒绝',
        'message' => '您的 ACC 申请 \':accName\' 已被拒绝。原因：:reason',
    ],
    'acc_status_changed' => [
        'title' => 'ACC 状态已更改',
        'message' => '您的 ACC \':accName\' 已被:action。:reason',
    ],

    // Training Center Notifications
    'training_center_application' => [
        'title' => '新的培训中心申请',
        'message' => '已提交新的培训中心申请：:trainingCenterName',
    ],
    'training_center_approved' => [
        'title' => '培训中心申请已批准',
        'message' => '您的培训中心申请 \':trainingCenterName\' 已被批准。您现在可以访问您的工作区。',
    ],
    'training_center_rejected' => [
        'title' => '培训中心申请被拒绝',
        'message' => '您的培训中心申请 \':trainingCenterName\' 已被拒绝。原因：:reason',
    ],
    'training_center_status_changed' => [
        'title' => '培训中心状态已更改',
        'message' => '您的培训中心 \':trainingCenterName\' 已被:action。:reason',
    ],

    // Subscription Notifications
    'subscription_paid' => [
        'title' => '订阅付款成功',
        'message' => '您的订阅付款 $:amount 已成功处理。',
    ],
    'subscription_payment' => [
        'title' => '收到 ACC 订阅付款',
        'message' => ':accName 已支付其订阅费用。付款金额：$:amount。',
    ],
    'subscription_renewal' => [
        'title' => 'ACC 订阅已续订',
        'message' => ':accName 已续订其订阅。付款金额：$:amount。',
    ],
    'subscription_expiring' => [
        'title' => '订阅即将到期',
        'message' => '您的订阅将于 :expiryDate 到期。请续订以继续使用该平台。',
    ],

    // Instructor Authorization Notifications
    'instructor_authorization_requested' => [
        'title' => '新的讲师授权请求',
        'message' => ':trainingCenterName 已请求讲师授权：:instructorName:coursesInfo。',
    ],
    'instructor_authorized' => [
        'title' => '讲师授权已批准',
        'message' => '讲师 \':instructorName\' 已被 :accName 授权。:priceInfo:commissionInfo:coursesInfo 您现在可以继续付款。',
    ],
    'instructor_authorization_payment_success' => [
        'title' => '付款成功',
        'message' => '讲师 \':instructorName\' 授权的 $:amount 付款已成功处理。讲师现已正式授权。',
    ],
    'instructor_authorization_rejected' => [
        'title' => '讲师授权被拒绝',
        'message' => '讲师 \':instructorName\' 的授权请求已被拒绝。原因：:reason',
    ],
    'instructor_authorization_returned' => [
        'title' => '讲师授权请求已退回',
        'message' => '您与 :accName 的讲师 \':instructorName\' 授权请求已退回以供修订。评论：:comment',
    ],
    'instructor_commission_set' => [
        'title' => '佣金已设置 - 准备付款',
        'message' => '已为与 :accName 的讲师 \':instructorName\' 授权设置佣金。授权价格：$:authorizationPrice，佣金：:commissionPercentage%，课程：:coursesCount。您现在可以继续付款。',
    ],
    'instructor_needs_commission' => [
        'title' => '新的讲师授权 - 需要佣金',
        'message' => '讲师 \':instructorName\' 已被 :accName 批准。请设置佣金百分比。授权价格：$:authorizationPrice。',
    ],
    'instructor_authorization_paid' => [
        'title' => '收到讲师授权付款',
        'message' => '收到讲师授权付款 $:amount：:instructorName:commissionInfo',
    ],
    'instructor_status_changed' => [
        'title' => '讲师状态已更改',
        'message' => '您在 :trainingCenterName 的讲师账户 \':instructorName\' 已被:action。:reason',
    ],

    // Training Center Authorization Notifications
    'training_center_authorization_requested' => [
        'title' => '新的授权请求',
        'message' => ':trainingCenterName:locationInfo 已请求与您的 ACC 授权。',
    ],
    'training_center_authorized' => [
        'title' => '授权已批准',
        'message' => '您与 :accName 的授权请求已被批准。',
    ],
    'training_center_authorization_rejected' => [
        'title' => '授权被拒绝',
        'message' => '您与 :accName 的授权请求已被拒绝。原因：:reason',
    ],
    'training_center_authorization_returned' => [
        'title' => '授权请求已退回',
        'message' => '您与 :accName 的授权请求已退回以供修订。评论：:comment',
    ],

    // Code Purchase Notifications
    'code_purchased' => [
        'title' => '证书代码已购买',
        'message' => '您已成功购买 :quantity 个证书代码，价格为 $:amount。',
    ],
    'code_purchase_admin' => [
        'title' => '证书代码已购买',
        'message' => ':trainingCenterName 已购买 :quantity 个证书代码，价格为 $:amount。:commissionInfo',
    ],
    'code_purchase_acc' => [
        'title' => '证书代码已购买',
        'message' => ':trainingCenterName 已购买 :quantity 个证书代码。您的佣金：$:commission。',
    ],

    // Certificate Notifications
    'certificate_generated' => [
        'title' => '证书已生成',
        'message' => ':trainingCenterName 已生成新证书。证书编号：:certificateNumber，学员：:traineeName，课程：:courseName。',
    ],
    'certificate_generated_instructor' => [
        'title' => '为您的班级生成证书',
        'message' => ':trainingCenterName 已为课程 :courseName 中的学员 :traineeName 生成证书。证书编号：:certificateNumber。',
    ],
    'certificate_generated_admin' => [
        'title' => '证书已生成',
        'message' => '已生成新证书。证书编号：:certificateNumber，学员：:traineeName，课程：:courseName，培训中心：:trainingCenterName，ACC：:accName。',
    ],

    // Class Notifications
    'class_completed' => [
        'title' => '课程已完成',
        'message' => ':trainingCenterName 已将课程 \':courseName\' 的班级 \':className\' 标记为已完成。完成率：:completionRate%。',
    ],

    // Commission Notifications
    'commission_received' => [
        'title' => '收到佣金',
        'message' => '从 :typeLabel:payerInfo:payeeInfo 收到佣金 $:commissionAmount。交易总金额：$:totalAmount。',
    ],

    // Stripe Connect Notifications
    'stripe_onboarding_link_sent' => [
        'title' => '需要设置 Stripe Connect',
        'message' => '已向您的电子邮件发送 :accountTypeLabel 账户的 Stripe Connect 入门链接。请检查您的电子邮件并完成设置以开始接收付款。',
    ],

    // Manual Payment Notifications
    'manual_payment_request' => [
        'title' => '手动付款请求',
        'message' => ':trainingCenterName 已提交 :quantity 个证书代码的手动付款请求，总计 $:amount。请审查并验证付款收据。',
    ],
    'manual_payment_request_admin' => [
        'title' => '手动付款请求',
        'message' => ':trainingCenterName 已提交 :quantity 个证书代码的手动付款请求，总计 $:amount。请审查并验证付款收据。',
    ],
    'manual_payment_pending' => [
        'title' => '付款请求已提交',
        'message' => '您 :quantity 个证书代码的付款请求（总计 $:amount）已提交，正在等待批准。审查完成后将通知您。',
    ],
    'manual_payment_approved' => [
        'title' => '付款已批准',
        'message' => '您 :quantity 个证书代码的付款请求（总计 $:amount）已被批准。您的代码现已可用。',
    ],
    'manual_payment_rejected' => [
        'title' => '付款被拒绝',
        'message' => '您 :quantity 个证书代码的付款请求（总计 $:amount）已被拒绝。原因：:reason',
    ],

    // Status actions
    'status_actions' => [
        'suspended' => '已暂停',
        'active' => '已重新激活',
        'inactive' => '已停用',
        'expired' => '已过期',
        'rejected' => '已拒绝',
    ],
];

