<?php

return [
    // ACC Notifications
    'acc_application' => [
        'title' => 'Nueva Solicitud ACC',
        'message' => 'Se ha enviado una nueva solicitud ACC: :accName',
    ],
    'acc_approved' => [
        'title' => 'Solicitud ACC Aprobada',
        'message' => 'Su solicitud ACC para \':accName\' ha sido aprobada. Ahora puede acceder a su espacio de trabajo.',
    ],
    'acc_rejected' => [
        'title' => 'Solicitud ACC Rechazada',
        'message' => 'Su solicitud ACC para \':accName\' ha sido rechazada. Razón: :reason',
    ],
    'acc_status_changed' => [
        'title' => 'Estado ACC Cambiado',
        'message' => 'Su ACC \':accName\' ha sido :action.:reason',
    ],

    // Training Center Notifications
    'training_center_application' => [
        'title' => 'Nueva Solicitud de Centro de Entrenamiento',
        'message' => 'Se ha enviado una nueva solicitud de centro de entrenamiento: :trainingCenterName',
    ],
    'training_center_approved' => [
        'title' => 'Solicitud de Centro de Entrenamiento Aprobada',
        'message' => 'Su solicitud de centro de entrenamiento para \':trainingCenterName\' ha sido aprobada. Ahora puede acceder a su espacio de trabajo.',
    ],
    'training_center_rejected' => [
        'title' => 'Solicitud de Centro de Entrenamiento Rechazada',
        'message' => 'Su solicitud de centro de entrenamiento para \':trainingCenterName\' ha sido rechazada. Razón: :reason',
    ],
    'training_center_status_changed' => [
        'title' => 'Estado del Centro de Entrenamiento Cambiado',
        'message' => 'Su centro de entrenamiento \':trainingCenterName\' ha sido :action.:reason',
    ],

    // Subscription Notifications
    'subscription_paid' => [
        'title' => 'Pago de Suscripción Exitoso',
        'message' => 'Su pago de suscripción de $:amount ha sido procesado exitosamente.',
    ],
    'subscription_payment' => [
        'title' => 'Pago de Suscripción ACC Recibido',
        'message' => ':accName ha pagado su suscripción. Monto del pago: $:amount.',
    ],
    'subscription_renewal' => [
        'title' => 'Suscripción ACC Renovada',
        'message' => ':accName ha renovado su suscripción. Monto del pago: $:amount.',
    ],
    'subscription_expiring' => [
        'title' => 'Suscripción por Vencer',
        'message' => 'Su suscripción vencerá el :expiryDate. Por favor renueve para continuar usando la plataforma.',
    ],

    // Instructor Authorization Notifications
    'instructor_authorization_requested' => [
        'title' => 'Nueva Solicitud de Autorización de Instructor',
        'message' => ':trainingCenterName ha solicitado autorización para el instructor: :instructorName:coursesInfo.',
    ],
    'instructor_authorized' => [
        'title' => 'Autorización de Instructor Aprobada',
        'message' => 'El instructor \':instructorName\' ha sido autorizado por :accName.:priceInfo:commissionInfo:coursesInfo Ahora puede proceder con el pago.',
    ],
    'instructor_authorization_payment_success' => [
        'title' => 'Pago Exitoso',
        'message' => 'El pago de $:amount por la autorización del instructor \':instructorName\' ha sido procesado exitosamente. El instructor ahora está oficialmente autorizado.',
    ],
    'instructor_authorization_rejected' => [
        'title' => 'Autorización de Instructor Rechazada',
        'message' => 'La solicitud de autorización para el instructor \':instructorName\' ha sido rechazada. Razón: :reason',
    ],
    'instructor_authorization_returned' => [
        'title' => 'Solicitud de Autorización de Instructor Devuelta',
        'message' => 'Su solicitud de autorización para el instructor \':instructorName\' con :accName ha sido devuelta para revisión. Comentario: :comment',
    ],
    'instructor_commission_set' => [
        'title' => 'Comisión Establecida - Listo para Pago',
        'message' => 'Se ha establecido la comisión para la autorización del instructor \':instructorName\' con :accName. Precio de autorización: $:authorizationPrice, Comisión: :commissionPercentage%, Cursos: :coursesCount. Ahora puede proceder con el pago.',
    ],
    'instructor_needs_commission' => [
        'title' => 'Nueva Autorización de Instructor - Comisión Requerida',
        'message' => 'El instructor \':instructorName\' ha sido aprobado por :accName. Por favor establezca el porcentaje de comisión. Precio de autorización: $:authorizationPrice.',
    ],
    'instructor_authorization_paid' => [
        'title' => 'Pago de Autorización de Instructor Recibido',
        'message' => 'Se recibió un pago de $:amount por la autorización del instructor: :instructorName:commissionInfo',
    ],
    'instructor_status_changed' => [
        'title' => 'Estado del Instructor Cambiado',
        'message' => 'Su cuenta de instructor \':instructorName\' en :trainingCenterName ha sido :action.:reason',
    ],

    // Training Center Authorization Notifications
    'training_center_authorization_requested' => [
        'title' => 'Nueva Solicitud de Autorización',
        'message' => ':trainingCenterName:locationInfo ha solicitado autorización con su ACC.',
    ],
    'training_center_authorized' => [
        'title' => 'Autorización Aprobada',
        'message' => 'Su solicitud de autorización con :accName ha sido aprobada.',
    ],
    'training_center_authorization_rejected' => [
        'title' => 'Autorización Rechazada',
        'message' => 'Su solicitud de autorización con :accName ha sido rechazada. Razón: :reason',
    ],
    'training_center_authorization_returned' => [
        'title' => 'Solicitud de Autorización Devuelta',
        'message' => 'Su solicitud de autorización con :accName ha sido devuelta para revisión. Comentario: :comment',
    ],

    // Code Purchase Notifications
    'code_purchased' => [
        'title' => 'Códigos de Certificado Comprados',
        'message' => 'Ha comprado exitosamente :quantity código(s) de certificado por $:amount.',
    ],
    'code_purchase_admin' => [
        'title' => 'Códigos de Certificado Comprados',
        'message' => ':trainingCenterName compró :quantity código(s) de certificado por $:amount.:commissionInfo',
    ],
    'code_purchase_acc' => [
        'title' => 'Códigos de Certificado Comprados',
        'message' => ':trainingCenterName compró :quantity código(s) de certificado. Su comisión: $:commission.',
    ],

    // Certificate Notifications
    'certificate_generated' => [
        'title' => 'Certificado Generado',
        'message' => 'Se ha generado un nuevo certificado por :trainingCenterName. Número de Certificado: :certificateNumber, Estudiante: :traineeName, Curso: :courseName.',
    ],
    'certificate_generated_instructor' => [
        'title' => 'Certificado Generado para Su Clase',
        'message' => 'Se ha generado un certificado para el estudiante :traineeName en el curso :courseName por :trainingCenterName. Número de Certificado: :certificateNumber.',
    ],
    'certificate_generated_admin' => [
        'title' => 'Certificado Generado',
        'message' => 'Se ha generado un nuevo certificado. Número de Certificado: :certificateNumber, Estudiante: :traineeName, Curso: :courseName, Centro de Entrenamiento: :trainingCenterName, ACC: :accName.',
    ],

    // Class Notifications
    'class_completed' => [
        'title' => 'Clase Completada',
        'message' => 'La clase \':className\' para el curso \':courseName\' ha sido marcada como completada por :trainingCenterName. Tasa de finalización: :completionRate%.',
    ],

    // Commission Notifications
    'commission_received' => [
        'title' => 'Comisión Recibida',
        'message' => 'Comisión de $:commissionAmount recibida de :typeLabel:payerInfo:payeeInfo. Monto total de la transacción: $:totalAmount.',
    ],

    // Stripe Connect Notifications
    'stripe_onboarding_link_sent' => [
        'title' => 'Configuración de Stripe Connect Requerida',
        'message' => 'Se ha enviado un enlace de configuración de Stripe Connect a su correo electrónico para su cuenta :accountTypeLabel. Por favor revise su correo y complete la configuración para comenzar a recibir pagos.',
    ],

    // Manual Payment Notifications
    'manual_payment_request' => [
        'title' => 'Solicitud de Pago Manual',
        'message' => ':trainingCenterName ha enviado una solicitud de pago manual para :quantity código(s) de certificado por un total de $:amount. Por favor revise y verifique el recibo de pago.',
    ],
    'manual_payment_request_admin' => [
        'title' => 'Solicitud de Pago Manual',
        'message' => ':trainingCenterName ha enviado una solicitud de pago manual para :quantity código(s) de certificado por un total de $:amount. Por favor revise y verifique el recibo de pago.',
    ],
    'manual_payment_pending' => [
        'title' => 'Solicitud de Pago Enviada',
        'message' => 'Su solicitud de pago para :quantity código(s) de certificado por un total de $:amount ha sido enviada y está pendiente de aprobación. Se le notificará una vez que sea revisada.',
    ],
    'manual_payment_approved' => [
        'title' => 'Pago Aprobado',
        'message' => 'Su solicitud de pago para :quantity código(s) de certificado por un total de $:amount ha sido aprobada. Sus códigos ahora están disponibles.',
    ],
    'manual_payment_rejected' => [
        'title' => 'Pago Rechazado',
        'message' => 'Su solicitud de pago para :quantity código(s) de certificado por un total de $:amount ha sido rechazada. Razón: :reason',
    ],

    // Status actions
    'status_actions' => [
        'suspended' => 'suspendido',
        'active' => 'reactivado',
        'inactive' => 'desactivado',
        'expired' => 'vencido',
        'rejected' => 'rechazado',
    ],
];

