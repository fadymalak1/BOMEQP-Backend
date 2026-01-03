# ACC Profile API Documentation

## نظرة عامة

هذا التوثيق يشرح بالتفصيل جميع endpoints المتعلقة بإدارة ملف ACC الشخصي (Profile). هذه الـ APIs تسمح لـ ACC بإدارة معلوماته الشخصية، رفع الشعار، إدارة المستندات، وربط حساب Stripe.

---

## Base URL

```
https://your-domain.com/api
```

---

## Authentication

جميع الـ endpoints تتطلب مصادقة باستخدام Laravel Sanctum. يجب إرسال Bearer Token في header:

```
Authorization: Bearer {your_token_here}
```

---

## Endpoints

### 1. الحصول على ملف ACC الشخصي

**GET** `/acc/profile`

#### الوصف
يحصل على جميع معلومات ملف ACC الشخصي بما في ذلك البيانات الأساسية، العناوين، الشعار، المستندات، ومعلومات Stripe.

#### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### Response Success (200)

```json
{
  "profile": {
    "id": 1,
    "name": "ABC Accreditation Body",
    "legal_name": "ABC Accreditation Body LLC",
    "registration_number": "REG123456",
    "email": "info@example.com",
    "phone": "+1234567890",
    "country": "Egypt",
    "address": "123 Main St",
    "mailing_address": {
      "street": "123 Main Street",
      "city": "Cairo",
      "country": "Egypt",
      "postal_code": "12345"
    },
    "physical_address": {
      "street": "456 Business Avenue",
      "city": "Cairo",
      "country": "Egypt",
      "postal_code": "12345"
    },
    "website": "https://example.com",
    "logo_url": "https://your-domain.com/storage/accs/1/logo/1234567890_1_logo.png",
    "status": "active",
    "commission_percentage": 10.00,
    "stripe_account_id": "acct_xxxxxxxxxxxxx",
    "stripe_account_configured": true,
    "documents": [
      {
        "id": 1,
        "document_type": "license",
        "document_url": "https://your-domain.com/storage/accs/1/documents/abc123def456.pdf",
        "uploaded_at": "2024-01-15T10:30:00.000000Z",
        "verified": true,
        "verified_by": {
          "id": 5,
          "name": "Admin User",
          "email": "admin@example.com"
        },
        "verified_at": "2024-01-16T14:20:00.000000Z",
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-16T14:20:00.000000Z"
      }
    ],
    "user": {
      "id": 2,
      "name": "ABC Accreditation Body",
      "email": "info@example.com",
      "role": "acc_admin",
      "status": "active"
    },
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-20T15:45:00.000000Z"
  }
}
```

#### Response Fields Description

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | معرف ACC الفريد |
| `name` | string | اسم ACC |
| `legal_name` | string | الاسم القانوني لـ ACC |
| `registration_number` | string | رقم التسجيل الفريد |
| `email` | string | البريد الإلكتروني |
| `phone` | string | رقم الهاتف |
| `country` | string | الدولة |
| `address` | string | العنوان الأساسي |
| `mailing_address` | object | عنوان المراسلات (street, city, country, postal_code) |
| `physical_address` | object | العنوان الفعلي (street, city, country, postal_code) |
| `website` | string/nullable | موقع الويب |
| `logo_url` | string/nullable | رابط الشعار |
| `status` | string | حالة ACC (pending, active, suspended, expired) |
| `commission_percentage` | float | نسبة العمولة |
| `stripe_account_id` | string/nullable | معرف حساب Stripe |
| `stripe_account_configured` | boolean | هل تم إعداد حساب Stripe |
| `documents` | array | قائمة المستندات المرفوعة |
| `user` | object/nullable | معلومات حساب المستخدم المرتبط |

#### Document Object Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | معرف المستند |
| `document_type` | string | نوع المستند (license, registration, certificate, other) |
| `document_url` | string | رابط المستند |
| `uploaded_at` | datetime | تاريخ الرفع |
| `verified` | boolean | هل تم التحقق من المستند |
| `verified_by` | object/nullable | معلومات المستخدم الذي تحقق من المستند |
| `verified_at` | datetime/nullable | تاريخ التحقق |
| `created_at` | datetime | تاريخ الإنشاء |
| `updated_at` | datetime | تاريخ آخر تحديث |

#### Error Responses

**401 Unauthorized**
```json
{
  "message": "Unauthenticated"
}
```

**404 Not Found**
```json
{
  "message": "ACC not found"
}
```

---

### 2. تحديث ملف ACC الشخصي

**PUT** `/acc/profile`

#### الوصف
يسمح بتحديث معلومات ملف ACC الشخصي. يمكن تحديث أي حقل بشكل منفصل أو عدة حقول معاً. يدعم رفع ملفات (الشعار والمستندات) باستخدام multipart/form-data أو إرسال البيانات كـ JSON.

#### Headers

**لرفع الملفات (multipart/form-data):**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**لإرسال JSON فقط:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### Request Body Parameters

جميع الحقول اختيارية. يمكن إرسال أي حقل أو مجموعة حقول للتحديث.

##### الحقول الأساسية

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `name` | string | No | اسم ACC | max:255 |
| `legal_name` | string | No | الاسم القانوني | max:255 |
| `phone` | string | No | رقم الهاتف | max:255 |
| `country` | string | No | الدولة | max:255 |
| `address` | string | No | العنوان الأساسي | - |

##### عناوين المراسلات (Mailing Address)

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `mailing_street` | string | No | الشارع | max:255 |
| `mailing_city` | string | No | المدينة | max:255 |
| `mailing_country` | string | No | الدولة | max:255 |
| `mailing_postal_code` | string | No | الرمز البريدي | max:20 |

##### العناوين الفعلية (Physical Address)

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `physical_street` | string | No | الشارع | max:255 |
| `physical_city` | string | No | المدينة | max:255 |
| `physical_country` | string | No | الدولة | max:255 |
| `physical_postal_code` | string | No | الرمز البريدي | max:20 |

##### معلومات إضافية

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `website` | string | No | موقع الويب | URL format, max:255 |
| `logo_url` | string | No | رابط الشعار (اختياري إذا تم رفع ملف) | URL format, max:255 |
| `logo` | file | No | ملف الشعار للرفع | image (jpeg, jpg, png), max:5MB |
| `stripe_account_id` | string | No | معرف حساب Stripe Connect | يجب أن يبدأ بـ "acct_" |

##### المستندات (Documents)

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `documents` | array | No | مصفوفة المستندات | - |
| `documents[].id` | integer | No | معرف المستند (للتحديث) | يجب أن يكون موجود في قاعدة البيانات |
| `documents[].document_type` | string | Yes* | نوع المستند | license, registration, certificate, other |
| `documents[].file` | file | No | ملف المستند للرفع | pdf, jpg, jpeg, png, max:10MB |

*مطلوب عند رفع مستند جديد أو تحديث نوع مستند موجود

#### ملاحظات مهمة

1. **رفع الشعار:**
   - يمكن رفع ملف شعار مباشرة باستخدام حقل `logo`
   - أو إرسال رابط الشعار في حقل `logo_url`
   - إذا تم رفع ملف شعار، سيتم تجاهل `logo_url` تلقائياً
   - الشعار القديم سيتم حذفه تلقائياً عند رفع شعار جديد
   - الصيغ المدعومة: JPEG, JPG, PNG
   - الحد الأقصى للحجم: 5MB

2. **إدارة المستندات:**
   - لرفع مستند جديد: أرسل `document_type` و `file` بدون `id`
   - لتحديث مستند موجود: أرسل `id` و `file` و `document_type`
   - لتحديث نوع مستند فقط: أرسل `id` و `document_type` بدون `file`
   - عند تحديث مستند، سيتم إعادة تعيين حالة التحقق إلى `false`
   - المستند القديم سيتم حذفه تلقائياً عند رفع مستند جديد

3. **Stripe Account ID:**
   - يجب أن يبدأ بـ "acct_"
   - يجب أن يكون معرف حساب Stripe Connect صحيح

4. **Transaction Safety:**
   - جميع العمليات تتم داخل transaction
   - في حالة حدوث خطأ، سيتم التراجع عن جميع التغييرات
   - الملفات المرفوعة سيتم حذفها تلقائياً في حالة الفشل

#### Request Examples

##### مثال 1: تحديث معلومات أساسية (JSON)

```json
{
  "name": "Updated ACC Name",
  "phone": "+9876543210",
  "website": "https://updated-website.com"
}
```

##### مثال 2: رفع شعار (multipart/form-data)

```
POST /api/acc/profile
Content-Type: multipart/form-data
Authorization: Bearer {token}

name: Updated ACC Name
logo: [binary file data]
```

##### مثال 3: رفع مستند جديد (multipart/form-data)

```
POST /api/acc/profile
Content-Type: multipart/form-data
Authorization: Bearer {token}

documents[0][document_type]: license
documents[0][file]: [binary file data]
```

##### مثال 4: تحديث مستند موجود (multipart/form-data)

```
POST /api/acc/profile
Content-Type: multipart/form-data
Authorization: Bearer {token}

documents[0][id]: 1
documents[0][document_type]: registration
documents[0][file]: [binary file data]
```

##### مثال 5: تحديث نوع مستند فقط (JSON)

```json
{
  "documents": [
    {
      "id": 1,
      "document_type": "certificate"
    }
  ]
}
```

##### مثال 6: تحديث شامل (multipart/form-data)

```
POST /api/acc/profile
Content-Type: multipart/form-data
Authorization: Bearer {token}

name: New ACC Name
legal_name: New ACC Legal Name
phone: +1234567890
country: Egypt
address: New Address
mailing_street: 123 Main St
mailing_city: Cairo
mailing_country: Egypt
mailing_postal_code: 12345
physical_street: 456 Business Ave
physical_city: Cairo
physical_country: Egypt
physical_postal_code: 12345
website: https://newwebsite.com
logo: [binary file data]
stripe_account_id: acct_xxxxxxxxxxxxx
documents[0][document_type]: license
documents[0][file]: [binary file data]
documents[1][id]: 2
documents[1][document_type]: registration
documents[1][file]: [binary file data]
```

#### Response Success (200)

```json
{
  "message": "Profile updated successfully",
  "profile": {
    "id": 1,
    "name": "Updated ACC Name",
    "legal_name": "Updated ACC Legal Name",
    "registration_number": "REG123456",
    "email": "info@example.com",
    "phone": "+9876543210",
    "country": "Egypt",
    "address": "New Address",
    "mailing_address": {
      "street": "123 Main St",
      "city": "Cairo",
      "country": "Egypt",
      "postal_code": "12345"
    },
    "physical_address": {
      "street": "456 Business Ave",
      "city": "Cairo",
      "country": "Egypt",
      "postal_code": "12345"
    },
    "website": "https://newwebsite.com",
    "logo_url": "https://your-domain.com/storage/accs/1/logo/1234567890_1_logo.png",
    "status": "active",
    "commission_percentage": 10.00,
    "stripe_account_id": "acct_xxxxxxxxxxxxx",
    "stripe_account_configured": true,
    "documents": [
      {
        "id": 1,
        "document_type": "license",
        "document_url": "https://your-domain.com/storage/accs/1/documents/abc123def456.pdf",
        "uploaded_at": "2024-01-20T15:45:00.000000Z",
        "verified": false,
        "verified_by": null,
        "verified_at": null,
        "created_at": "2024-01-15T10:30:00.000000Z",
        "updated_at": "2024-01-20T15:45:00.000000Z"
      }
    ],
    "user": {
      "id": 2,
      "name": "Updated ACC Name",
      "email": "info@example.com",
      "role": "acc_admin",
      "status": "active"
    },
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-20T15:45:00.000000Z"
  }
}
```

#### Response: No Changes (200)

إذا لم يتم إرسال أي تغييرات:

```json
{
  "message": "No changes provided. Profile remains unchanged.",
  "profile": {
    // ... profile data unchanged
  }
}
```

#### Error Responses

**401 Unauthorized**
```json
{
  "message": "Unauthenticated"
}
```

**404 Not Found**
```json
{
  "message": "ACC not found"
}
```

**422 Validation Error**
```json
{
  "message": "Validation failed",
  "errors": {
    "name": [
      "The name field must not exceed 255 characters."
    ],
    "logo": [
      "The logo must be an image.",
      "The logo must not be greater than 5120 kilobytes."
    ],
    "stripe_account_id": [
      "The Stripe account ID must start with \"acct_\" and be a valid Stripe account ID."
    ],
    "documents.0.document_type": [
      "The documents.0.document_type field is required when documents.0.file is present."
    ]
  }
}
```

**500 Server Error**
```json
{
  "message": "Profile update failed"
}
```

أو في حالة debug mode:
```json
{
  "message": "Profile update failed: [detailed error message]"
}
```

---

### 3. التحقق من حساب Stripe

**POST** `/acc/profile/verify-stripe-account`

#### الوصف
يتحقق من صحة معرف حساب Stripe Connect ويرجع معلومات الحساب إذا كان صحيحاً ومتصلاً بالمنصة.

#### Headers
```
Authorization: Bearer {token}
Content-Type: application/json
```

#### Request Body

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `stripe_account_id` | string | Yes | معرف حساب Stripe Connect للتحقق منه |

#### Request Example

```json
{
  "stripe_account_id": "acct_xxxxxxxxxxxxx"
}
```

#### Response Success (200)

```json
{
  "valid": true,
  "account": {
    "id": "acct_xxxxxxxxxxxxx",
    "type": "express",
    "country": "US",
    "email": "acc@example.com",
    "charges_enabled": true,
    "payouts_enabled": true
  },
  "message": "Stripe account is valid and connected"
}
```

#### Response Fields Description

| Field | Type | Description |
|-------|------|-------------|
| `valid` | boolean | هل الحساب صحيح ومتصلاً |
| `account` | object/nullable | معلومات حساب Stripe (إذا كان صحيحاً) |
| `error` | string/nullable | رسالة الخطأ (إذا كان الحساب غير صحيح) |
| `message` | string | رسالة توضيحية |

#### Error Responses

**400 Bad Request - Stripe Not Configured**
```json
{
  "valid": false,
  "error": "Stripe is not configured"
}
```

**400 Bad Request - Invalid Account**
```json
{
  "valid": false,
  "error": "Invalid Stripe account",
  "message": "Stripe account verification failed. Please check that the account ID is correct and the account is properly connected to the platform."
}
```

**401 Unauthorized**
```json
{
  "message": "Unauthenticated"
}
```

**422 Validation Error**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "stripe_account_id": [
      "The stripe account id field is required."
    ]
  }
}
```

---

## أمثلة استخدام متقدمة

### مثال 1: تحديث الشعار فقط

**cURL:**
```bash
curl -X PUT "https://your-domain.com/api/acc/profile" \
  -H "Authorization: Bearer {token}" \
  -F "logo=@/path/to/logo.png"
```

**JavaScript (Fetch):**
```javascript
const formData = new FormData();
formData.append('logo', fileInput.files[0]);

fetch('https://your-domain.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

### مثال 2: رفع عدة مستندات معاً

**cURL:**
```bash
curl -X PUT "https://your-domain.com/api/acc/profile" \
  -H "Authorization: Bearer {token}" \
  -F "documents[0][document_type]=license" \
  -F "documents[0][file]=@/path/to/license.pdf" \
  -F "documents[1][document_type]=registration" \
  -F "documents[1][file]=@/path/to/registration.pdf"
```

**JavaScript (Fetch):**
```javascript
const formData = new FormData();
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', licenseFile);
formData.append('documents[1][document_type]', 'registration');
formData.append('documents[1][file]', registrationFile);

fetch('https://your-domain.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`
  },
  body: formData
})
.then(response => response.json())
.then(data => console.log(data));
```

### مثال 3: تحديث معلومات مع رفع شعار ومستند

**cURL:**
```bash
curl -X PUT "https://your-domain.com/api/acc/profile" \
  -H "Authorization: Bearer {token}" \
  -F "name=New ACC Name" \
  -F "phone=+1234567890" \
  -F "logo=@/path/to/logo.png" \
  -F "documents[0][document_type]=license" \
  -F "documents[0][file]=@/path/to/license.pdf"
```

### مثال 4: استخدام JSON فقط (بدون ملفات)

**cURL:**
```bash
curl -X PUT "https://your-domain.com/api/acc/profile" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Name",
    "phone": "+1234567890",
    "website": "https://example.com",
    "logo_url": "https://external-site.com/logo.png"
  }'
```

**JavaScript (Fetch):**
```javascript
fetch('https://your-domain.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    name: 'Updated Name',
    phone: '+1234567890',
    website: 'https://example.com',
    logo_url: 'https://external-site.com/logo.png'
  })
})
.then(response => response.json())
.then(data => console.log(data));
```

---

## ملاحظات مهمة للمطورين

### 1. معالجة الأخطاء

- جميع الأخطاء يتم إرجاعها مع رسائل واضحة
- في حالة فشل رفع ملف، سيتم حذف الملفات المرفوعة تلقائياً
- في حالة فشل تحديث قاعدة البيانات، سيتم التراجع عن جميع التغييرات (Rollback)

### 2. الأمان

- جميع الـ endpoints تتطلب مصادقة
- يمكن للـ ACC تحديث ملفه الشخصي فقط
- المستندات يتم التحقق منها من قبل Group Admin
- عند تحديث مستند، يتم إعادة تعيين حالة التحقق

### 3. الأداء

- الملفات يتم حفظها في `storage/app/public`
- الشعارات في: `accs/{acc_id}/logo/`
- المستندات في: `accs/{acc_id}/documents/`
- الملفات القديمة يتم حذفها تلقائياً عند رفع ملفات جديدة

### 4. الحدود والقيود

- حجم الشعار: 5MB كحد أقصى
- حجم المستند: 10MB كحد أقصى
- صيغ الشعار المدعومة: JPEG, JPG, PNG
- صيغ المستندات المدعومة: PDF, JPEG, JPG, PNG

### 5. التوافق

- يدعم `application/json` و `multipart/form-data`
- يمكن إرسال أي حقل أو مجموعة حقول
- جميع الحقول اختيارية

---

## حالات الاستخدام الشائعة

### السيناريو 1: تسجيل ACC جديد وتحديث الملف الشخصي

1. تسجيل حساب جديد عبر `/auth/register`
2. تسجيل الدخول والحصول على token
3. تحديث الملف الشخصي برفع الشعار والمستندات عبر `/acc/profile`

### السيناريو 2: تحديث معلومات الاتصال

1. الحصول على الملف الحالي عبر `/acc/profile`
2. تحديث معلومات الاتصال فقط عبر `/acc/profile`

### السيناريو 3: ربط حساب Stripe

1. إنشاء حساب Stripe Connect
2. التحقق من الحساب عبر `/acc/profile/verify-stripe-account`
3. إضافة معرف الحساب في الملف الشخصي عبر `/acc/profile`

### السيناريو 4: تحديث المستندات

1. رفع مستندات جديدة
2. تحديث مستندات موجودة
3. تحديث نوع مستند موجود

---

## استكشاف الأخطاء

### المشكلة: خطأ 401 Unauthorized

**السبب:** Token غير صحيح أو منتهي الصلاحية

**الحل:** 
- تأكد من إرسال Token في header
- تأكد من صحة Token
- قم بتسجيل الدخول مرة أخرى للحصول على token جديد

### المشكلة: خطأ 422 Validation Error

**السبب:** البيانات المرسلة غير صحيحة

**الحل:**
- راجع قواعد التحقق (Validation Rules)
- تأكد من صحة تنسيق البيانات
- تأكد من حجم الملفات (5MB للشعار، 10MB للمستندات)

### المشكلة: خطأ 500 Server Error

**السبب:** خطأ في الخادم

**الحل:**
- راجع سجلات الخادم (Logs)
- تأكد من صلاحيات المجلدات
- تأكد من إعدادات التخزين (Storage)

### المشكلة: الملفات لا ترفع

**السبب:** مشاكل في التخزين أو الصلاحيات

**الحل:**
- تأكد من وجود مجلد `storage/app/public`
- تأكد من صلاحيات الكتابة على المجلد
- تأكد من ربط `storage` بـ `public` عبر `php artisan storage:link`

---

## الدعم والمساعدة

للمزيد من المساعدة أو الإبلاغ عن مشاكل، يرجى التواصل مع فريق الدعم الفني.

---

**آخر تحديث:** 2024-01-20

