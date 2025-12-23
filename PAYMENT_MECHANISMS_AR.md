# توثيق آليات الدفع في النظام

هذا المستند يشرح آليات الدفع المطبقة في نظام BOMEQP.

---

## جدول المحتويات

1. [اشتراك ACC](#اشتراك-acc)
2. [اعتماد المدرب (Instructor Authorization)](#اعتماد-المدرب)
3. [شراء أكواد الشهادات](#شراء-أكواد-الشهادات)
4. [المهام المجدولة](#المهام-المجدولة)

---

## اشتراك ACC

### نظرة عامة
يجب على حسابات ACC الحفاظ على اشتراك نشط للبقاء قيد التشغيل. عند انتهاء الاشتراك، يتم تعليق حساب ACC تلقائيًا ولا يمكن إعادة تفعيله إلا بعد تجديد الاشتراك.

### التدفق

1. **ACC يدفع رسوم الاشتراك** → إنشاء سجل اشتراك
2. **انتهاء الاشتراك** → تعليق تلقائي (عبر أمر مجدول)
3. **ACC يجدد الاشتراك** → إعادة تفعيل الحساب

---

### 1. دفع الاشتراك

**Endpoint:** `POST /api/acc/subscription/payment`  
**المصادقة:** مطلوبة (ACC Admin)  
**الوصف:** ACC يدفع رسوم الاشتراك للإدارة.

**Request Body:**
```json
{
  "amount": 10000.00,
  "payment_method": "credit_card",
  "payment_intent_id": "pi_1234567890"
}
```

**Response:** `200 OK`
```json
{
  "message": "Payment successful",
  "subscription": {
    "id": 1,
    "acc_id": 5,
    "subscription_start_date": "2025-12-19",
    "subscription_end_date": "2026-12-19",
    "amount": "10000.00",
    "payment_status": "paid"
  }
}
```

**ملاحظات:**
- إذا كان ACC معلقًا بسبب انتهاء الاشتراك، سيتم إعادة تفعيله تلقائيًا عند الدفع
- مدة الاشتراك الافتراضية: سنة واحدة
- يتم إنشاء سجل معاملة لتتبع الدفع

---

### 2. تجديد الاشتراك

**Endpoint:** `PUT /api/acc/subscription/renew`  
**المصادقة:** مطلوبة (ACC Admin)  
**الوصف:** تجديد اشتراك منتهي أو على وشك الانتهاء.

**Request Body:**
```json
{
  "amount": 10000.00,
  "payment_method": "credit_card",
  "payment_intent_id": "pi_1234567890",
  "auto_renew": false
}
```

**ملاحظات:**
- إذا لم ينته الاشتراك الحالي، يبدأ الاشتراك الجديد من تاريخ انتهاء الاشتراك الحالي
- إذا انتهى الاشتراك، يبدأ الاشتراك الجديد من الآن
- يعيد تفعيل حساب ACC تلقائيًا إذا كان معلقًا

---

## اعتماد المدرب

### نظرة عامة
عملية اعتماد المدرب تتضمن عدة خطوات مع دفع مطلوب قبل الاعتماد النهائي.

### التدفق

1. **Training Center** ينشئ المدرب ويرسل طلب اعتماد
2. **ACC Admin** يراجع ويوافق (يحدد سعر الاعتماد)
3. **Group Admin** يحدد نسبة العمولة
4. **Training Center** يدفع رسوم الاعتماد
5. **المدرب** يتم اعتماده رسميًا

---

### 1. ACC Admin يوافق على المدرب (تحديد السعر)

**Endpoint:** `PUT /api/acc/instructors/requests/{id}/approve`  
**المصادقة:** مطلوبة (ACC Admin)  
**الوصف:** ACC Admin يوافق على طلب اعتماد المدرب ويحدد سعر الاعتماد.

**Request Body:**
```json
{
  "authorization_price": 500.00
}
```

**ملاحظات:**
- بعد الموافقة، يتغير الحالة إلى `approved` و `group_admin_status` يصبح `pending`
- Training Center لا يمكنه الدفع حتى يحدد Group Admin نسبة العمولة

---

### 2. Group Admin يحدد نسبة العمولة

**Endpoint:** `PUT /api/admin/instructor-authorizations/{id}/set-commission`  
**المصادقة:** مطلوبة (Group Admin)  
**الوصف:** Group Admin يحدد نسبة العمولة لاعتماد المدرب.

**Request Body:**
```json
{
  "commission_percentage": 15.5
}
```

**ملاحظات:**
- نسبة العمولة تحدد كيفية تقسيم الدفع بين Group و ACC
- بعد تحديد العمولة، `group_admin_status` يصبح `commission_set`
- Training Center يتلقى إشعارًا لإتمام عملية الدفع

---

### 3. Group Admin عرض طلبات العمولة المعلقة

**Endpoint:** `GET /api/admin/instructor-authorizations/pending-commission`  
**المصادقة:** مطلوبة (Group Admin)  
**الوصف:** الحصول على جميع طلبات اعتماد المدرب التي تنتظر تحديد نسبة العمولة.

---

### 4. Training Center دفع الاعتماد

**Endpoint:** `POST /api/training-center/instructors/authorizations/{id}/pay`  
**المصادقة:** مطلوبة (Training Center Admin)  
**الوصف:** Training Center يدفع رسوم اعتماد المدرب بعد تحديد العمولة.

**Request Body:**
```json
{
  "payment_method": "wallet",
  "payment_intent_id": "pi_1234567890"
}
```

**ملاحظات:**
- الدفع ينشئ سجل معاملة
- يتم توزيع العمولة تلقائيًا وتسجيلها في CommissionLedger
- بعد الدفع، `group_admin_status` يصبح `completed` والمدرب معتمد رسميًا
- توزيع العمولة:
  - Group يتلقى: `authorization_price * commission_percentage / 100`
  - ACC يتلقى: `authorization_price * (100 - commission_percentage) / 100`

---

### 5. Training Center عرض طلبات الاعتماد

**Endpoint:** `GET /api/training-center/instructors/authorizations`  
**المصادقة:** مطلوبة (Training Center Admin)  
**الوصف:** الحصول على جميع طلبات اعتماد المدرب لمركز التدريب.

**Query Parameters:**
- `status`: تصفية حسب الحالة (اختياري)
- `payment_status`: تصفية حسب حالة الدفع (اختياري)

---

## شراء أكواد الشهادات

### نظرة عامة
Training Centers تشتري أكواد الشهادات من ACCs. يتم توزيع المدفوعات تلقائيًا بناءً على نسب العمولة المحددة من قبل Group Admin.

### التدفق

1. **Group Admin** يحدد نسبة العمولة لـ ACC
2. **Training Center** يشتري الأكواد من ACC
3. **الدفع** يتم توزيعه تلقائيًا:
   - Group يتلقى نسبة العمولة
   - ACC يتلقى المبلغ المتبقي

---

### شراء أكواد الشهادات

**Endpoint:** `POST /api/training-center/codes/purchase`  
**المصادقة:** مطلوبة (Training Center Admin)  
**الوصف:** شراء أكواد الشهادات مع توزيع العمولة التلقائي.

**Request Body:**
```json
{
  "acc_id": 3,
  "course_id": 5,
  "quantity": 10,
  "discount_code": "SAVE20",
  "payment_method": "wallet"
}
```

**توزيع العمولة:**
- إذا كانت نسبة عمولة ACC هي 15%:
  - Group يتلقى: `4000.00 * 15% = 600.00`
  - ACC يتلقى: `4000.00 * 85% = 3400.00`
- يتم تسجيل مبالغ العمولة في جدول `CommissionLedger`

**ملاحظات:**
- نسبة العمولة يتم استرجاعها من حقل `commission_percentage` في ACC (يحدده Group Admin)
- توزيع العمولة تلقائي ويتم تسجيله في CommissionLedger
- يتم إنشاء معاملة لتتبع الدفع
- يتم إنشاء سجل في CommissionLedger لتتبع التسوية

---

## المهام المجدولة

### فحص الاشتراكات المنتهية
**الأمر:** `php artisan subscriptions:check-expired`  
**الجدولة:** يوميًا  
**الغرض:** تعليق تلقائي لحسابات ACC التي انتهت اشتراكاتها

**الإعداد:**  
إضافة إلى cron في السيرفر:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## ملخص

### اشتراك ACC
- ✅ الدفع ينشئ اشتراك
- ✅ تعليق تلقائي عند انتهاء الاشتراك (أمر مجدول)
- ✅ إعادة تفعيل فقط بعد دفع التجديد
- ✅ المدة الافتراضية: سنة واحدة

### اعتماد المدرب
- ✅ ACC Admin يحدد سعر الاعتماد
- ✅ Group Admin يحدد نسبة العمولة
- ✅ Training Center يدفع رسوم الاعتماد
- ✅ توزيع العمولة التلقائي
- ✅ الاعتماد الرسمي بعد الدفع

### شراء أكواد الشهادات
- ✅ توزيع العمولة التلقائي
- ✅ العمولة بناءً على نسبة عمولة ACC
- ✅ إنشاء سجلات CommissionLedger
- ✅ جاهز لمعالجة التسوية

---

**آخر تحديث:** 19 ديسمبر 2025

