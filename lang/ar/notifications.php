<?php

return [
    // ACC Notifications
    'acc_application' => [
        'title' => 'طلب ACC جديد',
        'message' => 'تم تقديم طلب ACC جديد: :accName',
    ],
    'acc_approved' => [
        'title' => 'تم الموافقة على طلب ACC',
        'message' => 'تمت الموافقة على طلب ACC الخاص بك لـ \':accName\'. يمكنك الآن الوصول إلى مساحة العمل الخاصة بك.',
    ],
    'acc_rejected' => [
        'title' => 'تم رفض طلب ACC',
        'message' => 'تم رفض طلب ACC الخاص بك لـ \':accName\'. السبب: :reason',
    ],
    'acc_status_changed' => [
        'title' => 'تم تغيير حالة ACC',
        'message' => 'تم :action لـ ACC الخاص بك \':accName\'.:reason',
    ],

    // Training Center Notifications
    'training_center_application' => [
        'title' => 'طلب مركز تدريب جديد',
        'message' => 'تم تقديم طلب مركز تدريب جديد: :trainingCenterName',
    ],
    'training_center_approved' => [
        'title' => 'تم الموافقة على طلب مركز التدريب',
        'message' => 'تمت الموافقة على طلب مركز التدريب الخاص بك لـ \':trainingCenterName\'. يمكنك الآن الوصول إلى مساحة العمل الخاصة بك.',
    ],
    'training_center_rejected' => [
        'title' => 'تم رفض طلب مركز التدريب',
        'message' => 'تم رفض طلب مركز التدريب الخاص بك لـ \':trainingCenterName\'. السبب: :reason',
    ],
    'training_center_status_changed' => [
        'title' => 'تم تغيير حالة مركز التدريب',
        'message' => 'تم :action لمركز التدريب الخاص بك \':trainingCenterName\'.:reason',
    ],

    // Subscription Notifications
    'subscription_paid' => [
        'title' => 'نجحت عملية دفع الاشتراك',
        'message' => 'تمت معالجة دفعة الاشتراك الخاصة بك بقيمة $:amount بنجاح.',
    ],
    'subscription_payment' => [
        'title' => 'تم استلام دفعة اشتراك ACC',
        'message' => 'دفع :accName اشتراكه. مبلغ الدفعة: $:amount.',
    ],
    'subscription_renewal' => [
        'title' => 'تم تجديد اشتراك ACC',
        'message' => 'جدد :accName اشتراكه. مبلغ الدفعة: $:amount.',
    ],
    'subscription_expiring' => [
        'title' => 'الاشتراك على وشك الانتهاء',
        'message' => 'سينتهي اشتراكك في :expiryDate. يرجى التجديد للمتابعة في استخدام المنصة.',
    ],

    // Instructor Authorization Notifications
    'instructor_authorization_requested' => [
        'title' => 'طلب تفويض مدرب جديد',
        'message' => 'طلب :trainingCenterName تفويض المدرب: :instructorName:coursesInfo.',
    ],
    'instructor_authorized' => [
        'title' => 'تم الموافقة على تفويض المدرب',
        'message' => 'تم تفويض المدرب \':instructorName\' من قبل :accName.:priceInfo:commissionInfo:coursesInfo يمكنك الآن المتابعة مع الدفع.',
    ],
    'instructor_authorization_payment_success' => [
        'title' => 'نجحت عملية الدفع',
        'message' => 'تمت معالجة دفعة بقيمة $:amount لتفويض المدرب \':instructorName\' بنجاح. المدرب مفوض الآن رسمياً.',
    ],
    'instructor_authorization_rejected' => [
        'title' => 'تم رفض تفويض المدرب',
        'message' => 'تم رفض طلب التفويض للمدرب \':instructorName\'. السبب: :reason',
    ],
    'instructor_authorization_returned' => [
        'title' => 'تم إرجاع طلب تفويض المدرب',
        'message' => 'تم إرجاع طلب التفويض الخاص بك للمدرب \':instructorName\' مع :accName للمراجعة. التعليق: :comment',
    ],
    'instructor_commission_set' => [
        'title' => 'تم تعيين العمولة - جاهز للدفع',
        'message' => 'تم تعيين العمولة لتفويض المدرب \':instructorName\' مع :accName. سعر التفويض: $:authorizationPrice، العمولة: :commissionPercentage%، الدورات: :coursesCount. يمكنك الآن المتابعة مع الدفع.',
    ],
    'instructor_needs_commission' => [
        'title' => 'تفويض مدرب جديد - العمولة مطلوبة',
        'message' => 'تمت الموافقة على المدرب \':instructorName\' من قبل :accName. يرجى تعيين نسبة العمولة. سعر التفويض: $:authorizationPrice.',
    ],
    'instructor_authorization_paid' => [
        'title' => 'تم استلام دفعة تفويض المدرب',
        'message' => 'تم استلام دفعة بقيمة $:amount لتفويض المدرب: :instructorName:commissionInfo',
    ],
    'instructor_status_changed' => [
        'title' => 'تم تغيير حالة المدرب',
        'message' => 'تم :action لحساب المدرب الخاص بك \':instructorName\' في :trainingCenterName.:reason',
    ],

    // Training Center Authorization Notifications
    'training_center_authorization_requested' => [
        'title' => 'طلب تفويض جديد',
        'message' => 'طلب :trainingCenterName:locationInfo التفويض مع ACC الخاص بك.',
    ],
    'training_center_authorized' => [
        'title' => 'تمت الموافقة على التفويض',
        'message' => 'تمت الموافقة على طلب التفويض الخاص بك مع :accName.',
    ],
    'training_center_authorization_rejected' => [
        'title' => 'تم رفض التفويض',
        'message' => 'تم رفض طلب التفويض الخاص بك مع :accName. السبب: :reason',
    ],
    'training_center_authorization_returned' => [
        'title' => 'تم إرجاع طلب التفويض',
        'message' => 'تم إرجاع طلب التفويض الخاص بك مع :accName للمراجعة. التعليق: :comment',
    ],

    // Code Purchase Notifications
    'code_purchased' => [
        'title' => 'تم شراء رموز الشهادات',
        'message' => 'لقد قمت بشراء :quantity رمز(رموز) شهادة بنجاح مقابل $:amount.',
    ],
    'code_purchase_admin' => [
        'title' => 'تم شراء رموز الشهادات',
        'message' => 'اشترى :trainingCenterName :quantity رمز(رموز) شهادة مقابل $:amount.:commissionInfo',
    ],
    'code_purchase_acc' => [
        'title' => 'تم شراء رموز الشهادات',
        'message' => 'اشترى :trainingCenterName :quantity رمز(رموز) شهادة. عمولتك: $:commission.',
    ],

    // Certificate Notifications
    'certificate_generated' => [
        'title' => 'تم إنشاء الشهادة',
        'message' => 'تم إنشاء شهادة جديدة بواسطة :trainingCenterName. رقم الشهادة: :certificateNumber، المتدرب: :traineeName، الدورة: :courseName.',
    ],
    'certificate_generated_instructor' => [
        'title' => 'تم إنشاء شهادة لفصلك',
        'message' => 'تم إنشاء شهادة للمتدرب :traineeName في الدورة :courseName بواسطة :trainingCenterName. رقم الشهادة: :certificateNumber.',
    ],
    'certificate_generated_admin' => [
        'title' => 'تم إنشاء الشهادة',
        'message' => 'تم إنشاء شهادة جديدة. رقم الشهادة: :certificateNumber، المتدرب: :traineeName، الدورة: :courseName، مركز التدريب: :trainingCenterName، ACC: :accName.',
    ],

    // Class Notifications
    'class_completed' => [
        'title' => 'اكتمل الفصل',
        'message' => 'تم تحديد الفصل \':className\' للدورة \':courseName\' كمكتمل بواسطة :trainingCenterName. معدل الإكمال: :completionRate%.',
    ],

    // Commission Notifications
    'commission_received' => [
        'title' => 'تم استلام العمولة',
        'message' => 'تم استلام عمولة بقيمة $:commissionAmount من :typeLabel:payerInfo:payeeInfo. إجمالي مبلغ المعاملة: $:totalAmount.',
    ],

    // Stripe Connect Notifications
    'stripe_onboarding_link_sent' => [
        'title' => 'إعداد Stripe Connect مطلوب',
        'message' => 'تم إرسال رابط إعداد Stripe Connect إلى بريدك الإلكتروني لحساب :accountTypeLabel الخاص بك. يرجى التحقق من بريدك الإلكتروني وإكمال الإعداد لبدء استلام المدفوعات.',
    ],

    // Manual Payment Notifications
    'manual_payment_request' => [
        'title' => 'طلب دفع يدوي',
        'message' => 'قدم :trainingCenterName طلب دفع يدوي لـ :quantity رمز(رموز) شهادة بإجمالي $:amount. يرجى مراجعة والتحقق من إيصال الدفع.',
    ],
    'manual_payment_request_admin' => [
        'title' => 'طلب دفع يدوي',
        'message' => 'قدم :trainingCenterName طلب دفع يدوي لـ :quantity رمز(رموز) شهادة بإجمالي $:amount. يرجى مراجعة والتحقق من إيصال الدفع.',
    ],
    'manual_payment_pending' => [
        'title' => 'تم تقديم طلب الدفع',
        'message' => 'تم تقديم طلب الدفع الخاص بك لـ :quantity رمز(رموز) شهادة بإجمالي $:amount وهو في انتظار الموافقة. سيتم إشعارك بمجرد مراجعته.',
    ],
    'manual_payment_approved' => [
        'title' => 'تمت الموافقة على الدفع',
        'message' => 'تمت الموافقة على طلب الدفع الخاص بك لـ :quantity رمز(رموز) شهادة بإجمالي $:amount. رموزك متاحة الآن.',
    ],
    'manual_payment_rejected' => [
        'title' => 'تم رفض الدفع',
        'message' => 'تم رفض طلب الدفع الخاص بك لـ :quantity رمز(رموز) شهادة بإجمالي $:amount. السبب: :reason',
    ],

    // Status actions
    'status_actions' => [
        'suspended' => 'تم تعليقه',
        'active' => 'تم إعادة تفعيله',
        'inactive' => 'تم إلغاء تفعيله',
        'expired' => 'انتهى',
        'rejected' => 'تم رفضه',
    ],
];

