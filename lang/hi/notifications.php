<?php

return [
    // ACC Notifications
    'acc_application' => [
        'title' => 'नया ACC आवेदन',
        'message' => 'एक नया ACC आवेदन जमा किया गया है: :accName',
    ],
    'acc_approved' => [
        'title' => 'ACC आवेदन स्वीकृत',
        'message' => '\':accName\' के लिए आपका ACC आवेदन स्वीकृत कर दिया गया है। अब आप अपने कार्यक्षेत्र तक पहुंच सकते हैं।',
    ],
    'acc_rejected' => [
        'title' => 'ACC आवेदन अस्वीकृत',
        'message' => '\':accName\' के लिए आपका ACC आवेदन अस्वीकृत कर दिया गया है। कारण: :reason',
    ],
    'acc_status_changed' => [
        'title' => 'ACC स्थिति बदली गई',
        'message' => 'आपका ACC \':accName\' :action हो गया है।:reason',
    ],

    // Training Center Notifications
    'training_center_application' => [
        'title' => 'नया प्रशिक्षण केंद्र आवेदन',
        'message' => 'एक नया प्रशिक्षण केंद्र आवेदन जमा किया गया है: :trainingCenterName',
    ],
    'training_center_approved' => [
        'title' => 'प्रशिक्षण केंद्र आवेदन स्वीकृत',
        'message' => '\':trainingCenterName\' के लिए आपका प्रशिक्षण केंद्र आवेदन स्वीकृत कर दिया गया है। अब आप अपने कार्यक्षेत्र तक पहुंच सकते हैं।',
    ],
    'training_center_rejected' => [
        'title' => 'प्रशिक्षण केंद्र आवेदन अस्वीकृत',
        'message' => '\':trainingCenterName\' के लिए आपका प्रशिक्षण केंद्र आवेदन अस्वीकृत कर दिया गया है। कारण: :reason',
    ],
    'training_center_status_changed' => [
        'title' => 'प्रशिक्षण केंद्र स्थिति बदली गई',
        'message' => 'आपका प्रशिक्षण केंद्र \':trainingCenterName\' :action हो गया है।:reason',
    ],

    // Subscription Notifications
    'subscription_paid' => [
        'title' => 'सदस्यता भुगतान सफल',
        'message' => '$:amount की आपकी सदस्यता भुगतान सफलतापूर्वक संसाधित की गई है।',
    ],
    'subscription_payment' => [
        'title' => 'ACC सदस्यता भुगतान प्राप्त',
        'message' => ':accName ने अपनी सदस्यता का भुगतान किया है। भुगतान राशि: $:amount।',
    ],
    'subscription_renewal' => [
        'title' => 'ACC सदस्यता नवीकृत',
        'message' => ':accName ने अपनी सदस्यता नवीकृत की है। भुगतान राशि: $:amount।',
    ],
    'subscription_expiring' => [
        'title' => 'सदस्यता जल्द ही समाप्त हो रही है',
        'message' => 'आपकी सदस्यता :expiryDate को समाप्त हो जाएगी। कृपया प्लेटफॉर्म का उपयोग जारी रखने के लिए नवीकरण करें।',
    ],

    // Instructor Authorization Notifications
    'instructor_authorization_requested' => [
        'title' => 'नया प्रशिक्षक अधिकार अनुरोध',
        'message' => ':trainingCenterName ने प्रशिक्षक: :instructorName:coursesInfo के लिए अधिकार का अनुरोध किया है।',
    ],
    'instructor_authorized' => [
        'title' => 'प्रशिक्षक अधिकार स्वीकृत',
        'message' => 'प्रशिक्षक \':instructorName\' को :accName द्वारा अधिकृत कर दिया गया है।:priceInfo:commissionInfo:coursesInfo अब आप भुगतान के साथ आगे बढ़ सकते हैं।',
    ],
    'instructor_authorization_payment_success' => [
        'title' => 'भुगतान सफल',
        'message' => 'प्रशिक्षक \':instructorName\' अधिकार के लिए $:amount का भुगतान सफलतापूर्वक संसाधित किया गया है। प्रशिक्षक अब आधिकारिक रूप से अधिकृत है।',
    ],
    'instructor_authorization_rejected' => [
        'title' => 'प्रशिक्षक अधिकार अस्वीकृत',
        'message' => 'प्रशिक्षक \':instructorName\' के लिए अधिकार अनुरोध अस्वीकृत कर दिया गया है। कारण: :reason',
    ],
    'instructor_authorization_returned' => [
        'title' => 'प्रशिक्षक अधिकार अनुरोध वापस',
        'message' => ':accName के साथ प्रशिक्षक \':instructorName\' के लिए आपका अधिकार अनुरोध संशोधन के लिए वापस कर दिया गया है। टिप्पणी: :comment',
    ],
    'instructor_commission_set' => [
        'title' => 'कमीशन सेट - भुगतान के लिए तैयार',
        'message' => ':accName के साथ प्रशिक्षक \':instructorName\' अधिकार के लिए कमीशन सेट किया गया है। अधिकार मूल्य: $:authorizationPrice, कमीशन: :commissionPercentage%, पाठ्यक्रम: :coursesCount। अब आप भुगतान के साथ आगे बढ़ सकते हैं।',
    ],
    'instructor_needs_commission' => [
        'title' => 'नया प्रशिक्षक अधिकार - कमीशन आवश्यक',
        'message' => 'प्रशिक्षक \':instructorName\' को :accName द्वारा स्वीकृत कर दिया गया है। कृपया कमीशन प्रतिशत सेट करें। अधिकार मूल्य: $:authorizationPrice।',
    ],
    'instructor_authorization_paid' => [
        'title' => 'प्रशिक्षक अधिकार भुगतान प्राप्त',
        'message' => 'प्रशिक्षक अधिकार के लिए $:amount का भुगतान प्राप्त हुआ: :instructorName:commissionInfo',
    ],
    'instructor_status_changed' => [
        'title' => 'प्रशिक्षक स्थिति बदली गई',
        'message' => ':trainingCenterName पर आपका प्रशिक्षक खाता \':instructorName\' :action हो गया है।:reason',
    ],

    // Training Center Authorization Notifications
    'training_center_authorization_requested' => [
        'title' => 'नया अधिकार अनुरोध',
        'message' => ':trainingCenterName:locationInfo ने आपके ACC के साथ अधिकार का अनुरोध किया है।',
    ],
    'training_center_authorized' => [
        'title' => 'अधिकार स्वीकृत',
        'message' => ':accName के साथ आपका अधिकार अनुरोध स्वीकृत कर दिया गया है।',
    ],
    'training_center_authorization_rejected' => [
        'title' => 'अधिकार अस्वीकृत',
        'message' => ':accName के साथ आपका अधिकार अनुरोध अस्वीकृत कर दिया गया है। कारण: :reason',
    ],
    'training_center_authorization_returned' => [
        'title' => 'अधिकार अनुरोध वापस',
        'message' => ':accName के साथ आपका अधिकार अनुरोध संशोधन के लिए वापस कर दिया गया है। टिप्पणी: :comment',
    ],

    // Code Purchase Notifications
    'code_purchased' => [
        'title' => 'प्रमाणपत्र कोड खरीदे गए',
        'message' => 'आपने सफलतापूर्वक $:amount के लिए :quantity प्रमाणपत्र कोड खरीदे हैं।',
    ],
    'code_purchase_admin' => [
        'title' => 'प्रमाणपत्र कोड खरीदे गए',
        'message' => ':trainingCenterName ने $:amount के लिए :quantity प्रमाणपत्र कोड खरीदे हैं।:commissionInfo',
    ],
    'code_purchase_acc' => [
        'title' => 'प्रमाणपत्र कोड खरीदे गए',
        'message' => ':trainingCenterName ने :quantity प्रमाणपत्र कोड खरीदे हैं। आपका कमीशन: $:commission।',
    ],

    // Certificate Notifications
    'certificate_generated' => [
        'title' => 'प्रमाणपत्र जनरेट किया गया',
        'message' => ':trainingCenterName द्वारा एक नया प्रमाणपत्र जनरेट किया गया है। प्रमाणपत्र संख्या: :certificateNumber, प्रशिक्षु: :traineeName, पाठ्यक्रम: :courseName।',
    ],
    'certificate_generated_instructor' => [
        'title' => 'आपकी कक्षा के लिए प्रमाणपत्र जनरेट किया गया',
        'message' => ':trainingCenterName द्वारा पाठ्यक्रम :courseName में प्रशिक्षु :traineeName के लिए एक प्रमाणपत्र जनरेट किया गया है। प्रमाणपत्र संख्या: :certificateNumber।',
    ],
    'certificate_generated_admin' => [
        'title' => 'प्रमाणपत्र जनरेट किया गया',
        'message' => 'एक नया प्रमाणपत्र जनरेट किया गया है। प्रमाणपत्र संख्या: :certificateNumber, प्रशिक्षु: :traineeName, पाठ्यक्रम: :courseName, प्रशिक्षण केंद्र: :trainingCenterName, ACC: :accName।',
    ],

    // Class Notifications
    'class_completed' => [
        'title' => 'कक्षा पूर्ण',
        'message' => ':trainingCenterName द्वारा पाठ्यक्रम \':courseName\' के लिए कक्षा \':className\' को पूर्ण के रूप में चिह्नित किया गया है। पूर्णता दर: :completionRate%।',
    ],

    // Commission Notifications
    'commission_received' => [
        'title' => 'कमीशन प्राप्त',
        'message' => ':typeLabel:payerInfo:payeeInfo से $:commissionAmount का कमीशन प्राप्त हुआ। कुल लेनदेन राशि: $:totalAmount।',
    ],

    // Stripe Connect Notifications
    'stripe_onboarding_link_sent' => [
        'title' => 'स्ट्राइप कनेक्ट सेटअप आवश्यक',
        'message' => 'आपके :accountTypeLabel खाते के लिए आपके ईमेल पर एक स्ट्राइप कनेक्ट ऑनबोर्डिंग लिंक भेजा गया है। कृपया अपना ईमेल जांचें और भुगतान प्राप्त करना शुरू करने के लिए सेटअप पूरा करें।',
    ],

    // Manual Payment Notifications
    'manual_payment_request' => [
        'title' => 'मैनुअल भुगतान अनुरोध',
        'message' => ':trainingCenterName ने $:amount की कुल राशि के लिए :quantity प्रमाणपत्र कोड के लिए एक मैनुअल भुगतान अनुरोध जमा किया है। कृपया भुगतान रसीद की समीक्षा और सत्यापन करें।',
    ],
    'manual_payment_request_admin' => [
        'title' => 'मैनुअल भुगतान अनुरोध',
        'message' => ':trainingCenterName ने $:amount की कुल राशि के लिए :quantity प्रमाणपत्र कोड के लिए एक मैनुअल भुगतान अनुरोध जमा किया है। कृपया भुगतान रसीद की समीक्षा और सत्यापन करें।',
    ],
    'manual_payment_pending' => [
        'title' => 'भुगतान अनुरोध जमा किया गया',
        'message' => '$:amount की कुल राशि के लिए :quantity प्रमाणपत्र कोड के लिए आपका भुगतान अनुरोध जमा कर दिया गया है और अनुमोदन के लिए लंबित है। समीक्षा होने के बाद आपको सूचित किया जाएगा।',
    ],
    'manual_payment_approved' => [
        'title' => 'भुगतान स्वीकृत',
        'message' => '$:amount की कुल राशि के लिए :quantity प्रमाणपत्र कोड के लिए आपका भुगतान अनुरोध स्वीकृत कर दिया गया है। आपके कोड अब उपलब्ध हैं।',
    ],
    'manual_payment_rejected' => [
        'title' => 'भुगतान अस्वीकृत',
        'message' => '$:amount की कुल राशि के लिए :quantity प्रमाणपत्र कोड के लिए आपका भुगतान अनुरोध अस्वीकृत कर दिया गया है। कारण: :reason',
    ],

    // Status actions
    'status_actions' => [
        'suspended' => 'निलंबित',
        'active' => 'पुनः सक्रिय',
        'inactive' => 'निष्क्रिय',
        'expired' => 'समाप्त',
        'rejected' => 'अस्वीकृत',
    ],
];

