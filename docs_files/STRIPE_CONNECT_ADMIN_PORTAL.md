# نظام إدارة Stripe Connect - Admin Portal

## نظرة عامة
نظام متكامل لإدارة Stripe Connect حيث Admin واحد فقط (Portal Admin) هو المسؤول الكامل عن تفعيل وإضافة Stripe Connect لكل الحسابات في المنصة (ACCs، Training Centers، Instructors).

## التاريخ
22 يناير 2026

---

## الميزات الرئيسية

### 1. إدارة مركزية
- Admin واحد فقط لديه صلاحيات كاملة
- تفعيل/إلغاء/تحديث Stripe Connect لأي حساب
- عرض حالة جميع الحسابات في مكان واحد

### 2. معالجة تلقائية
- معالجة Webhooks من Stripe تلقائياً
- تحديث الحالة والمتطلبات تلقائياً
- فحص دوري لحالة الحسابات

### 3. السجلات والتدقيق
- سجل كامل لجميع عمليات التفعيل
- سجل نشاط Admin
- تتبع جميع التغييرات والأخطاء

---

## قاعدة البيانات

### جداول جديدة

#### 1. `stripe_connect_logs`
سجل لجميع عمليات Stripe Connect:
- `account_type`: نوع الحساب (acc, training_center, instructor)
- `account_id`: معرف الحساب
- `action`: نوع العملية (initiated, completed, failed, etc.)
- `status`: الحالة (success, failed, pending)
- `stripe_connected_account_id`: معرف Stripe
- `error_message`: رسالة الخطأ
- `performed_by_admin`: Admin الذي نفذ العملية

#### 2. `admin_activity_logs`
سجل نشاط Admin:
- `admin_id`: Admin الذي نفذ العملية
- `action`: نوع العملية (view, initiate, update, etc.)
- `target_account_type` و `target_account_id`: الحساب المستهدف
- `ip_address` و `user_agent`: معلومات الطلب
- `status`: نجح/فشل

### حقول جديدة للحسابات

تم إضافة الحقول التالية لـ ACC, TrainingCenter, و Instructor:

- `stripe_connect_status`: حالة Stripe Connect
- `stripe_onboarding_url`: رابط التسجيل
- `stripe_onboarding_completed`: هل اكتمل التسجيل
- `stripe_requirements`: المتطلبات من Stripe
- `stripe_connected_by_admin`: Admin الذي فعل الحساب
- `stripe_connected_at`: تاريخ التفعيل
- `stripe_last_status_check_at`: آخر فحص للحالة
- `stripe_last_error_message`: آخر رسالة خطأ

---

## API Endpoints

### 1. Get All Accounts
**GET** `/v1/api/admin/stripe-connect/accounts`

**Query Parameters**:
- `search` (optional): البحث بالاسم أو البريد
- `status` (optional): فلترة حسب الحالة
- `type` (optional): فلترة حسب النوع (acc, training_center, instructor)
- `per_page` (optional): عدد النتائج (default: 15)
- `page` (optional): رقم الصفحة

**Response**:
```json
{
  "success": true,
  "data": {
    "accounts": [
      {
        "id": 1,
        "name": "Account Name",
        "email": "account@email.com",
        "phone": "+1234567890",
        "type": "acc",
        "stripe_account_id": "acct_xxx",
        "stripe_connect_status": "connected",
        "stripe_connected_at": "2026-01-22T00:00:00Z"
      }
    ],
    "total": 100,
    "page": 1,
    "per_page": 15,
    "last_page": 7
  }
}
```

---

### 2. Get Account Details
**GET** `/v1/api/admin/stripe-connect/accounts/{accountType}/{accountId}`

**Parameters**:
- `accountType`: acc, training_center, or instructor
- `accountId`: Account ID

**Response**:
```json
{
  "success": true,
  "data": {
    "account": {...},
    "stripe_status": {
      "status": "connected",
      "stripe_account_id": "acct_xxx",
      "connected_at": "2026-01-22T00:00:00Z",
      "requirements": {
        "currently_due": [],
        "eventually_due": []
      },
      "bank_info": {
        "bank_name": "Bank Name",
        "account_number": "****1234"
      },
      "onboarding_url": "https://connect.stripe.com/...",
      "onboarding_completed": true
    },
    "logs": [...]
  }
}
```

---

### 3. Initiate Stripe Connect
**POST** `/v1/api/admin/stripe-connect/initiate`

**Request Body**:
```json
{
  "account_type": "acc",
  "account_id": 1,
  "country": "EG"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Stripe Connect initiated successfully",
  "data": {
    "stripe_connected_account_id": "acct_xxx",
    "onboarding_url": "https://connect.stripe.com/...",
    "status": "pending",
    "created_at": "2026-01-22T00:00:00Z"
  }
}
```

---

### 4. Get Status
**GET** `/v1/api/admin/stripe-connect/status/{accountType}/{accountId}`

**Response**: نفس response من Get Account Details

---

### 5. Retry Failed Connection
**POST** `/v1/api/admin/stripe-connect/retry/{accountType}/{accountId}`

**Response**:
```json
{
  "success": true,
  "message": "Retry initiated successfully",
  "data": {
    "onboarding_url": "https://connect.stripe.com/...",
    "status": "pending"
  }
}
```

---

### 6. Disconnect
**DELETE** `/v1/api/admin/stripe-connect/disconnect/{accountType}/{accountId}`

**Response**:
```json
{
  "success": true,
  "message": "Stripe Connect disconnected successfully",
  "data": {
    "status": "inactive",
    "disconnected_at": "2026-01-22T00:00:00Z"
  }
}
```

---

### 7. Resend Onboarding Link
**POST** `/v1/api/admin/stripe-connect/resend-link`

**Request Body**:
```json
{
  "account_type": "acc",
  "account_id": 1
}
```

**Response**:
```json
{
  "success": true,
  "message": "Onboarding link sent successfully",
  "data": {
    "onboarding_url": "https://connect.stripe.com/...",
    "email_sent": true,
    "sent_to": "account@email.com"
  }
}
```

---

### 8. Get Logs
**GET** `/v1/api/admin/stripe-connect/logs`

**Query Parameters**:
- `account_type` (optional)
- `account_id` (optional)
- `action` (optional)
- `status` (optional)
- `date_from` (optional)
- `date_to` (optional)
- `per_page` (optional)
- `page` (optional)

**Response**:
```json
{
  "success": true,
  "data": {
    "logs": [...],
    "total": 50,
    "page": 1,
    "per_page": 15,
    "last_page": 4
  }
}
```

---

### 9. Get Statistics
**GET** `/v1/api/admin/stripe-connect/stats`

**Response**:
```json
{
  "success": true,
  "data": {
    "total": 100,
    "connected": 85,
    "pending": 10,
    "failed": 5,
    "inactive": 0,
    "updating": 0,
    "success_rate": 85.0,
    "updated_at": "2026-01-22T00:00:00Z"
  }
}
```

---

### 10. Get Admin Activity Logs
**GET** `/v1/api/admin/activity-logs`

**Query Parameters**:
- `action` (optional)
- `limit` (optional, default: 50)
- `offset` (optional, default: 0)

**Response**:
```json
{
  "success": true,
  "data": {
    "logs": [...],
    "total": 200,
    "limit": 50,
    "offset": 0
  }
}
```

---

## حالات Stripe Connect

- **pending**: قيد الانتظار - تم إنشاء الحساب ولكن لم يكتمل التسجيل
- **connected**: متصل - اكتمل التسجيل ويمكن استقبال المدفوعات
- **failed**: فشل - فشل التفعيل
- **inactive**: غير نشط - تم قطع الاتصال
- **updating**: قيد التحديث - يتم تحديث المعلومات

---

## آلية العمل

### 1. تفعيل Stripe Connect

1. Admin يختار الحساب
2. Admin ينقر على "تفعيل Stripe Connect"
3. النظام ينشئ Stripe Connected Account
4. النظام ينشئ رابط Onboarding
5. يتم حفظ البيانات في قاعدة البيانات
6. يتم تسجيل العملية في السجلات

### 2. معالجة Webhooks

عند استقبال webhook من Stripe:

- `account.updated`: تحديث حالة الحساب والمتطلبات
- `account.external_account.created`: تحديث معلومات البنك
- `account.application.deauthorized`: تعطيل الحساب

### 3. الفحص الدوري

Job يتم تشغيله دورياً (كل 6 ساعات) للتحقق من:
- حالة الحسابات
- المتطلبات الجديدة
- تحديث المعلومات

---

## الأمان

### 1. صلاحيات Admin
- فقط المستخدمون ذوو role `admin` أو `group_admin` يمكنهم الوصول
- التحقق من الصلاحيات في كل endpoint

### 2. Webhook Verification
- التحقق من توقيع webhook من Stripe
- استخدام middleware للتحقق

### 3. Audit Log
- تسجيل جميع أفعال Admin
- حفظ IP و User Agent
- تتبع جميع التغييرات

---

## Jobs والجدولة

### CheckStripeConnectStatusJob
Job للفحص الدوري لحالة Stripe Connect:

```php
// في app/Console/Kernel.php
$schedule->job(new CheckStripeConnectStatusJob())->everySixHours();
```

---

## الخطوات التالية

### 1. Migration
```bash
php artisan migrate
```

### 2. إعداد Stripe
- تأكد من إعداد Stripe keys
- إعداد Webhook endpoint في Stripe Dashboard
- إضافة Webhook events:
  - `account.updated`
  - `account.external_account.created`
  - `account.external_account.updated`
  - `account.application.deauthorized`

### 3. جدولة Jobs
إضافة في `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new CheckStripeConnectStatusJob())->everySixHours();
}
```

---

## ملاحظات مهمة

1. **Admin فقط**: فقط Admin يمكنه تفعيل وإدارة Stripe Connect
2. **Onboarding URL**: الرابط صالح لمدة 24 ساعة، يمكن إنشاء رابط جديد
3. **Requirements**: Stripe قد يطلب معلومات إضافية (KYC، البنك، إلخ)
4. **Bank Info**: معلومات البنك معروضة بشكل مخفي جزئياً للأمان
5. **Webhooks**: يجب إعداد webhook endpoint في Stripe Dashboard

---

## الدعم

للأسئلة أو المشاكل، يرجى الاتصال بفريق التطوير.

