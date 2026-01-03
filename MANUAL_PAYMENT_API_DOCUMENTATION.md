# توثيق API - نظام الدفع اليدوي لشراء أكواد الشهادات

## نظرة عامة

تم إضافة نظام الدفع اليدوي (Manual Payment) كخيار إضافي لشراء أكواد الشهادات من قبل Training Centers. يتيح هذا النظام للمراكز التدريبية رفع إيصال الدفع وإدخال المبلغ، ثم انتظار موافقة ACC أو Group Admin قبل إنشاء الأكواد.

---

## خيارات الدفع المتاحة

### 1. الدفع بالبطاقة الائتمانية (Credit Card)
- الدفع الفوري عبر Stripe
- إنشاء الأكواد مباشرة بعد التأكيد
- الحالة: `completed`

### 2. الدفع اليدوي (Manual Payment)
- رفع إيصال الدفع
- إدخال المبلغ المدفوع
- انتظار موافقة ACC أو Admin
- الحالة: `pending` → `approved` أو `rejected`

---

## Endpoints للمراكز التدريبية (Training Center)

### 1. إنشاء Payment Intent (مع معلومات الدفع اليدوي)

**Endpoint:** `POST /training-center/codes/create-payment-intent`

**الوصف:** إنشاء payment intent للدفع بالبطاقة الائتمانية، مع إرجاع معلومات عن خيار الدفع اليدوي المتاح.

**المعاملات المطلوبة:**
- `acc_id` (integer): معرف ACC
- `course_id` (integer): معرف الكورس
- `quantity` (integer): عدد الأكواد المطلوبة

**المعاملات الاختيارية:**
- `discount_code` (string): كود الخصم إن وجد

**Response (200):**
```json
{
  "success": true,
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx",
  "amount": 1000.00,
  "currency": "USD",
  "total_amount": "1000.00",
  "discount_amount": "100.00",
  "final_amount": "900.00",
  "unit_price": "100.00",
  "quantity": 10,
  "commission_amount": "90.00",
  "provider_amount": "810.00",
  "payment_type": "destination_charge",
  "payment_methods_available": ["credit_card", "manual_payment"],
  "manual_payment_info": {
    "available": true,
    "requires_receipt": true,
    "receipt_formats": ["pdf", "jpg", "jpeg", "png"],
    "max_receipt_size_mb": 10,
    "status_after_submission": "pending",
    "approval_required": true
  }
}
```

**الحقول الجديدة:**
- `payment_methods_available`: قائمة بخيارات الدفع المتاحة
- `manual_payment_info`: معلومات عن خيار الدفع اليدوي

---

### 2. شراء الأكواد (مع دعم الدفع اليدوي)

**Endpoint:** `POST /training-center/codes/purchase`

**الوصف:** شراء أكواد الشهادات باستخدام الدفع بالبطاقة الائتمانية أو الدفع اليدوي.

**المعاملات المطلوبة:**
- `acc_id` (integer): معرف ACC
- `course_id` (integer): معرف الكورس
- `quantity` (integer): عدد الأكواد المطلوبة
- `payment_method` (string): طريقة الدفع (`credit_card` أو `manual_payment`)

**المعاملات المشروطة:**
- `payment_intent_id` (string): مطلوب عند `payment_method = credit_card`
- `payment_receipt` (file): مطلوب عند `payment_method = manual_payment`
  - الصيغ المدعومة: PDF, JPG, JPEG, PNG
  - الحد الأقصى للحجم: 10 MB
- `payment_amount` (number): مطلوب عند `payment_method = manual_payment`
  - يجب أن يطابق المبلغ الإجمالي المحسوب

**المعاملات الاختيارية:**
- `discount_code` (string): كود الخصم

**Response عند الدفع بالبطاقة (200):**
```json
{
  "message": "Codes purchased successfully",
  "batch": {
    "id": 1,
    "training_center_id": 1,
    "acc_id": 1,
    "course_id": 1,
    "quantity": 10,
    "total_amount": "1000.00",
    "discount_amount": "100.00",
    "final_amount": "900.00",
    "payment_method": "credit_card",
    "payment_status": "completed",
    "created_at": "2025-01-20T10:30:00.000000Z"
  },
  "codes": [
    {
      "id": 1,
      "code": "ABC123XYZ456",
      "status": "available"
    }
  ]
}
```

**Response عند الدفع اليدوي (200):**
```json
{
  "message": "Payment request submitted successfully. Waiting for approval.",
  "batch": {
    "id": 1,
    "training_center_id": 1,
    "acc_id": 1,
    "course_id": 1,
    "quantity": 10,
    "total_amount": "1000.00",
    "discount_amount": "100.00",
    "final_amount": "900.00",
    "payment_method": "manual_payment",
    "payment_status": "pending",
    "created_at": "2025-01-20T10:30:00.000000Z"
  }
}
```

**ملاحظات مهمة:**
- عند الدفع اليدوي، لا يتم إنشاء الأكواد فوراً
- الحالة تبقى `pending` حتى موافقة ACC أو Admin
- يتم إرسال إشعارات للـ ACC والـ Admin عند تقديم الطلب
- يتم إرسال إشعار للـ Training Center عند الموافقة أو الرفض

---

## Endpoints للـ ACC

### 1. عرض طلبات الدفع المعلقة

**Endpoint:** `GET /acc/code-batches/pending-payments`

**الوصف:** الحصول على جميع طلبات الدفع اليدوي المعلقة للـ ACC الحالي.

**Response (200):**
```json
{
  "batches": [
    {
      "id": 1,
      "training_center": {
        "id": 1,
        "name": "Training Center Name",
        "email": "tc@example.com"
      },
      "quantity": 10,
      "total_amount": "1000.00",
      "payment_amount": "1000.00",
      "payment_receipt_url": "https://example.com/storage/...",
      "payment_status": "pending",
      "created_at": "2025-01-20T10:30:00.000000Z",
      "updated_at": "2025-01-20T10:30:00.000000Z"
    }
  ]
}
```

---

### 2. الموافقة على طلب الدفع اليدوي

**Endpoint:** `PUT /acc/code-batches/{id}/approve-payment`

**الوصف:** الموافقة على طلب الدفع اليدوي وإنشاء الأكواد.

**المعاملات المطلوبة:**
- `payment_amount` (number): المبلغ للتحقق (يجب أن يطابق المبلغ الإجمالي)

**Response (200):**
```json
{
  "message": "Payment approved and codes generated successfully",
  "batch": {
    "id": 1,
    "payment_status": "approved",
    "codes_count": 10
  },
  "codes": [
    {
      "id": 1,
      "code": "ABC123XYZ456",
      "status": "available"
    }
  ]
}
```

**ما يحدث عند الموافقة:**
1. التحقق من تطابق المبلغ
2. إنشاء الأكواد المطلوبة
3. تحديث حالة الدفعة إلى `approved`
4. تحديث حالة المعاملة إلى `completed`
5. إنشاء سجل العمولة (Commission Ledger)
6. إرسال إشعار للـ Training Center بالموافقة
7. إرسال إشعار للـ ACC بالشراء

**أخطاء محتملة:**
- `400`: المبلغ المدخل لا يطابق المبلغ الإجمالي
- `404`: الدفعة غير موجودة أو ليست معلقة

---

### 3. رفض طلب الدفع اليدوي

**Endpoint:** `PUT /acc/code-batches/{id}/reject-payment`

**الوصف:** رفض طلب الدفع اليدوي مع إدخال سبب الرفض.

**المعاملات المطلوبة:**
- `rejection_reason` (string): سبب الرفض (حد أقصى 1000 حرف)

**Response (200):**
```json
{
  "message": "Payment request rejected",
  "batch": {
    "id": 1,
    "payment_status": "rejected",
    "rejection_reason": "Payment receipt is unclear or amount mismatch"
  }
}
```

**ما يحدث عند الرفض:**
1. تحديث حالة الدفعة إلى `rejected`
2. حفظ سبب الرفض
3. تحديث حالة المعاملة إلى `failed`
4. إرسال إشعار للـ Training Center بالرفض مع السبب

---

## Endpoints للـ Admin

### 1. عرض جميع طلبات الدفع المعلقة

**Endpoint:** `GET /admin/code-batches/pending-payments`

**الوصف:** الحصول على جميع طلبات الدفع اليدوي المعلقة من جميع ACCs.

**Response (200):**
```json
{
  "batches": [
    {
      "id": 1,
      "training_center": {
        "id": 1,
        "name": "Training Center Name",
        "email": "tc@example.com"
      },
      "acc": {
        "id": 1,
        "name": "ACC Name",
        "email": "acc@example.com"
      },
      "course": {
        "id": 1,
        "name": "Course Name"
      },
      "quantity": 10,
      "total_amount": "1000.00",
      "payment_amount": "1000.00",
      "payment_receipt_url": "https://example.com/storage/...",
      "payment_status": "pending",
      "created_at": "2025-01-20T10:30:00.000000Z",
      "updated_at": "2025-01-20T10:30:00.000000Z"
    }
  ]
}
```

---

### 2. الموافقة على طلب الدفع اليدوي (Admin)

**Endpoint:** `PUT /admin/code-batches/{id}/approve-payment`

**الوصف:** الموافقة على طلب الدفع اليدوي وإنشاء الأكواد (Admin).

**المعاملات المطلوبة:**
- `payment_amount` (number): المبلغ للتحقق

**Response (200):**
```json
{
  "message": "Payment approved and codes generated successfully",
  "batch": {
    "id": 1,
    "payment_status": "approved",
    "codes_count": 10
  },
  "codes": [
    {
      "id": 1,
      "code": "ABC123XYZ456",
      "status": "available"
    }
  ]
}
```

**ما يحدث عند الموافقة:**
1. نفس الخطوات كما في ACC
2. إرسال إشعار إضافي للـ ACC بالشراء

---

### 3. رفض طلب الدفع اليدوي (Admin)

**Endpoint:** `PUT /admin/code-batches/{id}/reject-payment`

**الوصف:** رفض طلب الدفع اليدوي مع إدخال سبب الرفض (Admin).

**المعاملات المطلوبة:**
- `rejection_reason` (string): سبب الرفض

**Response (200):**
```json
{
  "message": "Payment request rejected",
  "batch": {
    "id": 1,
    "payment_status": "rejected",
    "rejection_reason": "Payment receipt is unclear or amount mismatch"
  }
}
```

---

## حالات الدفع (Payment Status)

### الحالات المتاحة:
- `pending`: الطلب معلق في انتظار المراجعة
- `approved`: تمت الموافقة وإنشاء الأكواد
- `rejected`: تم رفض الطلب
- `completed`: الدفع مكتمل (للدفع بالبطاقة الائتمانية)

### تدفق الحالات:

**للدفع بالبطاقة الائتمانية:**
```
completed (فوراً)
```

**للدفع اليدوي:**
```
pending → approved (بعد الموافقة)
pending → rejected (بعد الرفض)
```

---

## نظام الإشعارات

### عند تقديم طلب الدفع اليدوي:

**1. إشعار للـ ACC:**
- النوع: `manual_payment_request`
- العنوان: "Manual Payment Request"
- الرسالة: "Training Center Name has submitted a manual payment request for X certificate code(s) totaling $X.XX. Please review and verify the payment receipt."

**2. إشعار للـ Admin:**
- النوع: `manual_payment_request_admin`
- العنوان: "Manual Payment Request"
- الرسالة: "Training Center Name has submitted a manual payment request for X certificate code(s) totaling $X.XX. Please review and verify the payment receipt."

**3. إشعار للـ Training Center:**
- النوع: `manual_payment_pending`
- العنوان: "Payment Request Submitted"
- الرسالة: "Your payment request for X certificate code(s) totaling $X.XX has been submitted and is pending approval. You will be notified once it's reviewed."

---

### عند الموافقة على الطلب:

**1. إشعار للـ Training Center:**
- النوع: `manual_payment_approved`
- العنوان: "Payment Approved"
- الرسالة: "Your payment request for X certificate code(s) totaling $X.XX has been approved. Your codes are now available."

**2. إشعار للـ ACC (اختياري):**
- النوع: `code_purchase_acc`
- العنوان: "Certificate Codes Purchased"
- الرسالة: "Training Center Name purchased X certificate code(s). Your commission: $X.XX."

---

### عند رفض الطلب:

**إشعار للـ Training Center:**
- النوع: `manual_payment_rejected`
- العنوان: "Payment Rejected"
- الرسالة: "Your payment request for X certificate code(s) totaling $X.XX has been rejected. Reason: [سبب الرفض]"

---

## متطلبات إيصال الدفع

### الصيغ المدعومة:
- PDF
- JPG / JPEG
- PNG

### الحد الأقصى للحجم:
- 10 MB

### الموقع:
- يتم حفظ الإيصالات في: `storage/app/public/training-centers/{training_center_id}/payment-receipts/`

---

## التحقق من المبلغ

### عند تقديم الطلب:
- يجب أن يطابق `payment_amount` المبلغ الإجمالي المحسوب (`final_amount`)
- يُسمح بفرق صغير (0.01) للتقريب

### عند الموافقة:
- يجب أن يطابق `payment_amount` المدخل المبلغ الإجمالي للدفعة
- يُسمح بفرق صغير (0.01) للتقريب

---

## سيناريوهات الاستخدام

### السيناريو 1: دفع ناجح بالبطاقة الائتمانية
1. Training Center يستدعي `create-payment-intent`
2. Training Center يستدعي `purchase` مع `payment_method: "credit_card"`
3. يتم إنشاء الأكواد فوراً
4. الحالة: `completed`

### السيناريو 2: دفع يدوي ناجح
1. Training Center يستدعي `purchase` مع `payment_method: "manual_payment"`
2. يرفع إيصال الدفع ويدخل المبلغ
3. الحالة: `pending`
4. ACC أو Admin يراجع الطلب
5. ACC أو Admin يوافق على الطلب
6. يتم إنشاء الأكواد
7. الحالة: `approved`
8. Training Center يستقبل إشعار بالموافقة

### السيناريو 3: رفض طلب الدفع اليدوي
1. Training Center يقدم طلب دفع يدوي
2. الحالة: `pending`
3. ACC أو Admin يراجع الطلب
4. ACC أو Admin يرفض الطلب مع إدخال السبب
5. الحالة: `rejected`
6. Training Center يستقبل إشعار بالرفض مع السبب

---

## أخطاء شائعة ومعالجتها

### خطأ: المبلغ لا يطابق المبلغ الإجمالي
**الكود:** `400`
**الرسالة:** "Payment amount does not match the calculated total amount"
**الحل:** تأكد من إدخال المبلغ الصحيح المطابق للمبلغ الإجمالي المحسوب

### خطأ: إيصال الدفع مطلوب
**الكود:** `422`
**الرسالة:** "Payment receipt is required for manual payment"
**الحل:** تأكد من رفع ملف إيصال الدفع بصيغة مدعومة

### خطأ: الدفعة غير موجودة أو ليست معلقة
**الكود:** `404`
**الرسالة:** "Batch not found or not pending"
**الحل:** تأكد من معرف الدفعة الصحيح وأن الحالة `pending`

### خطأ: حجم ملف الإيصال كبير جداً
**الكود:** `422`
**الرسالة:** Validation error
**الحل:** تأكد من أن حجم الملف أقل من 10 MB

---

## ملاحظات مهمة

1. **الأكواد لا تُنشأ فوراً للدفع اليدوي:** يتم إنشاؤها فقط بعد الموافقة
2. **العمولة:** يتم حساب العمولة وإنشاء سجل العمولة عند الموافقة على الدفع اليدوي
3. **التخزين:** يتم حفظ إيصالات الدفع في مجلد منفصل لكل مركز تدريبي
4. **الأمان:** فقط ACC المالك أو Admin يمكنه الموافقة/الرفض
5. **التتبع:** يتم حفظ من قام بالموافقة/الرفض وتوقيت العملية

---

## التكامل مع النظام الحالي

### المعاملات (Transactions):
- يتم إنشاء معاملة بحالة `pending` للدفع اليدوي
- يتم تحديث الحالة إلى `completed` عند الموافقة
- يتم تحديث الحالة إلى `failed` عند الرفض

### سجل العمولة (Commission Ledger):
- يتم إنشاء سجل العمولة فقط عند الموافقة على الدفع اليدوي
- يتم حساب العمولة بناءً على نسبة العمولة المحددة للـ ACC

### أكواد الخصم (Discount Codes):
- يتم تطبيق أكواد الخصم على الدفع اليدوي أيضاً
- يتم تحديث استخدام كود الخصم عند الموافقة على الدفع

---

## الخلاصة

يوفر نظام الدفع اليدوي مرونة أكبر للمراكز التدريبية في دفع تكاليف شراء أكواد الشهادات، مع الحفاظ على الأمان والتحقق من خلال عملية مراجعة من قبل ACC أو Admin. النظام متكامل بالكامل مع نظام الإشعارات والعمولات الحالي.

