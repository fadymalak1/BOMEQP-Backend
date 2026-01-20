# نظام التحويل التلقائي للأموال - Automatic Transfer System

## نظرة عامة
نظام متكامل للتحويل التلقائي للأموال عند نجاح أي دفعة. يقوم النظام بتقسيم المبلغ تلقائياً إلى عمولة ومبلغ صافي، ثم يحول المبلغ الصافي للمستخدم عبر Stripe Transfers.

## التاريخ
22 يناير 2026

---

## الميزات الرئيسية

### 1. التحويل التلقائي
- يتم تفعيل التحويل تلقائياً عند نجاح أي دفعة
- تقسيم المبلغ إلى عمولة ومبلغ صافي
- التحويل الفوري عبر Stripe Transfers

### 2. معالجة الأخطاء
- إعادة المحاولة التلقائية (حتى 3 مرات)
- جدولة إعادة المحاولة بفواصل زمنية متزايدة
- تسجيل جميع الأخطاء للتحليل

### 3. التقارير والإحصائيات
- تقارير يومية/أسبوعية/شهرية
- إحصائيات شاملة للتحويلات
- تتبع المبالغ المعلقة والفاشلة

---

## البنية التحتية

### قاعدة البيانات

#### جدول `transfers`
يحتوي على جميع سجلات التحويلات:

- `id`: معرف فريد
- `transaction_id`: معرف المعاملة المرتبطة
- `user_id`: معرف المستخدم
- `user_type`: نوع المستخدم (acc, training_center, instructor)
- `user_type_id`: معرف المستخدم حسب النوع
- `gross_amount`: المبلغ الإجمالي
- `commission_amount`: مبلغ العمولة
- `net_amount`: المبلغ الصافي المراد تحويله
- `stripe_transfer_id`: معرف التحويل في Stripe
- `stripe_account_id`: Stripe Connect account ID
- `status`: الحالة (pending, processing, completed, failed, retrying)
- `retry_count`: عدد محاولات إعادة التحويل
- `error_message`: رسالة الخطأ
- `processed_at`, `completed_at`, `failed_at`: التواريخ

---

## API Endpoints

### Admin Endpoints

#### 1. Get All Transfers
**GET** `/v1/api/admin/transfers`

**Query Parameters**:
- `status` (optional): فلترة حسب الحالة
- `user_type` (optional): فلترة حسب نوع المستخدم
- `date_from` (optional): تاريخ البداية
- `date_to` (optional): تاريخ النهاية
- `search` (optional): البحث في stripe_transfer_id أو stripe_account_id
- `per_page` (optional): عدد النتائج في الصفحة (default: 15)
- `page` (optional): رقم الصفحة

**Response**:
```json
{
  "data": [...],
  "statistics": {
    "total": 100,
    "pending": 5,
    "completed": 90,
    "failed": 5,
    "total_gross_amount": 50000.00,
    "total_commission_amount": 7500.00,
    "total_net_amount": 42500.00
  },
  "pagination": {...}
}
```

#### 2. Get Transfer Details
**GET** `/v1/api/admin/transfers/{id}`

**Response**:
```json
{
  "transfer": {
    "id": 1,
    "transaction_id": 123,
    "gross_amount": 500.00,
    "commission_amount": 75.00,
    "net_amount": 425.00,
    "status": "completed",
    "stripe_transfer_id": "tr_xxx",
    ...
  }
}
```

#### 3. Summary Report
**GET** `/v1/api/admin/transfers/reports/summary`

**Query Parameters**:
- `period` (optional): daily, weekly, monthly (default: monthly)
- `start_date` (optional): تاريخ البداية
- `end_date` (optional): تاريخ النهاية

**Response**:
```json
{
  "period": "monthly",
  "start_date": "2026-01-01",
  "end_date": "2026-01-31",
  "overall": {
    "total_transfers": 100,
    "completed_transfers": 95,
    "failed_transfers": 5,
    "total_gross_amount": 50000.00,
    "total_commission_amount": 7500.00,
    "total_net_amount": 42500.00
  },
  "breakdown": [...]
}
```

#### 4. Retry Failed Transfer
**POST** `/v1/api/admin/transfers/{id}/retry`

**Response**:
```json
{
  "success": true,
  "message": "Transfer retry succeeded",
  "transfer": {...}
}
```

---

## آلية العمل

### 1. عند نجاح الدفعة

عند استقبال webhook من Stripe بحدث `payment_intent.succeeded`:

1. يتم تحديث حالة المعاملة إلى `completed`
2. يتم استدعاء `handleAutomaticTransfer()` تلقائياً
3. يتم حساب التقسيم (العمولة والصافي)
4. يتم إنشاء سجل transfer في قاعدة البيانات
5. يتم تنفيذ التحويل عبر Stripe

### 2. حساب التقسيم

```
المبلغ الإجمالي = المبلغ المدفوع
العمولة = المبلغ الإجمالي × نسبة العمولة (15% افتراضياً)
المبلغ الصافي = المبلغ الإجمالي - العمولة
```

**مثال**:
- المبلغ الإجمالي: 500$
- نسبة العمولة: 15%
- العمولة: 75$
- المبلغ الصافي: 425$

### 3. التحويل عبر Stripe

- يتم استخدام `Stripe\Transfer::create()` لإنشاء التحويل
- يتم التحويل إلى Stripe Connect account للمستخدم
- يتم حفظ `stripe_transfer_id` في قاعدة البيانات

### 4. معالجة الفشل

إذا فشل التحويل:

1. يتم تحديث حالة transfer إلى `failed`
2. يتم زيادة `retry_count`
3. يتم جدولة إعادة المحاولة تلقائياً:
   - المحاولة 1: بعد 60 ثانية
   - المحاولة 2: بعد 120 ثانية
   - المحاولة 3: بعد 240 ثانية

### 5. حالة Stripe Account غير متوفرة

إذا لم يكن لدى المستخدم Stripe Connect account:

- يتم إنشاء transfer في حالة `pending`
- يتم إشعار الإدارة
- يمكن للمستخدم إضافة Stripe account لاحقاً
- يمكن للإدارة معالجة التحويلات المعلقة يدوياً

---

## الإعدادات والتكوين

### نسبة العمولة الافتراضية

النسبة الافتراضية هي **15%** ويمكن تغييرها من:
- إعدادات ACC (commission_percentage)
- إعدادات النظام العامة

### Stripe Configuration

يجب إعداد Stripe بشكل صحيح:

1. **Secret Key**: في `STRIPE_KEY` أو StripeSetting
2. **Webhook Secret**: في `STRIPE_WEBHOOK_SECRET` أو StripeSetting
3. **Stripe Connect**: تفعيل Stripe Connect للمستخدمين

---

## الأمان

### 1. Webhook Verification
- التحقق من توقيع webhook من Stripe
- Middleware: `VerifyStripeWebhook`
- التحقق من صحة البيانات

### 2. Idempotency
- استخدام idempotency keys في Stripe
- التحقق من عدم تكرار التحويلات
- فحص حالة transfer قبل التنفيذ

### 3. Audit Log
- تسجيل جميع العمليات
- حفظ رسائل الأخطاء
- تتبع جميع المحاولات

---

## Jobs والجدولة

### RetryFailedTransferJob

Job مخصص لإعادة محاولة التحويلات الفاشلة:

- يتم جدولته تلقائياً عند فشل التحويل
- يمكن جدولته يدوياً من Admin
- إعادة المحاولة حتى 3 مرات

**الاستخدام**:
```php
RetryFailedTransferJob::dispatch($transfer, 60)
    ->delay(now()->addMinutes(1));
```

---

## الإشعارات

### للمستخدمين
- إشعار عند نجاح التحويل
- إشعار عند فشل التحويل (إذا لم تنجح إعادة المحاولة)

### للإدارة
- إشعار عند وجود transfer معلق (لا يوجد Stripe account)
- إشعار عند فشل التحويل بعد 3 محاولات

---

## الخطوات التالية

### 1. Migration
```bash
php artisan migrate
```

### 2. إعداد Stripe
- إضافة Stripe keys في الإعدادات
- إعداد Stripe Connect للمستخدمين
- إعداد Webhook endpoint في Stripe Dashboard

### 3. اختبار النظام
- اختبار التحويل التلقائي
- اختبار إعادة المحاولة
- اختبار التقارير

---

## ملاحظات مهمة

1. **Stripe Connect Required**: يجب أن يكون لدى المستخدمين Stripe Connect account للحصول على التحويلات
2. **Commission Calculation**: يتم حساب العمولة من المبلغ الإجمالي
3. **Currency**: يجب أن تكون العملة متوافقة بين Stripe account والحساب الرئيسي
4. **Minimum Amount**: Stripe قد يفرض حد أدنى للمبلغ (عادة 0.50$)
5. **Processing Time**: التحويلات قد تستغرق 2-7 أيام حسب نوع الحساب

---

## الدعم

للأسئلة أو المشاكل المتعلقة بالنظام، يرجى الاتصال بفريق التطوير.

