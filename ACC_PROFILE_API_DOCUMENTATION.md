# ACC Profile API Documentation

## نظرة عامة / Overview

هذا التوثيق يشرح جميع الـ APIs المتعلقة ببيانات الـ ACC Profile. يتيح هذا الـ API للـ ACCs عرض وتحديث بياناتهم الشخصية، بما في ذلك المعلومات الأساسية، الشعار (Logo)، والمستندات (PDF Documents).

This documentation explains all APIs related to ACC Profile data. This API allows ACCs to view and update their profile information, including basic information, logo, and PDF documents.

---

## Endpoints

### 1. عرض بيانات الـ Profile / Get Profile

**Endpoint:** `GET /api/acc/profile`

**الوصف / Description:**
يعيد جميع بيانات الـ ACC Profile بما في ذلك المعلومات الأساسية، الشعار، والمستندات.

Returns all ACC Profile data including basic information, logo, and documents.

**المتطلبات / Requirements:**
- Authentication: مطلوب (Bearer Token)
- Role: `acc_admin`

**Response (200):**
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
    "logo_url": "https://example.com/storage/accs/1/logo/logo_file.png",
    "status": "active",
    "commission_percentage": 10.00,
    "stripe_account_id": "acct_xxxxxxxxxxxxx",
    "stripe_account_configured": true,
    "documents": [
      {
        "id": 1,
        "document_type": "license",
        "document_url": "https://example.com/storage/accs/1/documents/document_file.pdf",
        "uploaded_at": "2024-01-01T00:00:00.000000Z",
        "verified": false,
        "verified_by": null,
        "verified_at": null,
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
      }
    ],
    "user": {
      "id": 1,
      "name": "ABC Accreditation Body",
      "email": "info@example.com",
      "role": "acc_admin",
      "status": "active"
    },
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

**Error Responses:**
- `401 Unauthorized` - غير مسجل دخول أو Token غير صالح
- `404 Not Found` - الـ ACC غير موجود

---

### 2. تحديث بيانات الـ Profile / Update Profile

**Endpoint:** `PUT /api/acc/profile`

**الوصف / Description:**
يسمح للـ ACC بتحديث بياناته الشخصية. يمكن تحديث الحقول التالية:
- المعلومات الأساسية (الاسم، العنوان، الهاتف، إلخ)
- الشعار (Logo) - صورة
- المستندات (Documents) - ملفات PDF
- معلومات Stripe Account

Allows the ACC to update their profile information. The following fields can be updated:
- Basic information (name, address, phone, etc.)
- Logo (image file)
- Documents (PDF files)
- Stripe Account information

**المتطلبات / Requirements:**
- Authentication: مطلوب (Bearer Token)
- Role: `acc_admin`
- Content-Type: `multipart/form-data` (لرفع الملفات / for file uploads)

**الحقول القابلة للتحديث / Updatable Fields:**

| الحقل / Field | النوع / Type | مطلوب / Required | الوصف / Description |
|---------------|--------------|------------------|---------------------|
| `name` | string | لا / No | اسم الـ ACC |
| `legal_name` | string | لا / No | الاسم القانوني |
| `phone` | string | لا / No | رقم الهاتف |
| `country` | string | لا / No | الدولة |
| `address` | string | لا / No | العنوان الأساسي |
| `mailing_street` | string | لا / No | شارع العنوان البريدي |
| `mailing_city` | string | لا / No | مدينة العنوان البريدي |
| `mailing_country` | string | لا / No | دولة العنوان البريدي |
| `mailing_postal_code` | string | لا / No | الرمز البريدي |
| `physical_street` | string | لا / No | شارع العنوان الفعلي |
| `physical_city` | string | لا / No | مدينة العنوان الفعلي |
| `physical_country` | string | لا / No | دولة العنوان الفعلي |
| `physical_postal_code` | string | لا / No | الرمز البريدي للعنوان الفعلي |
| `website` | string (URL) | لا / No | الموقع الإلكتروني |
| `logo` | file (image) | لا / No | ملف الشعار (JPG, JPEG, PNG - حد أقصى 5MB) |
| `logo_url` | string (URL) | لا / No | رابط الشعار (إذا لم يتم رفع ملف) |
| `stripe_account_id` | string | لا / No | معرف حساب Stripe (يبدأ بـ acct_) |
| `documents` | array | لا / No | مصفوفة المستندات (انظر التفاصيل أدناه) |

**ملاحظات مهمة / Important Notes:**
- يمكن تحديث أي حقل بشكل منفصل (Partial Updates مدعومة)
- عند رفع ملف شعار جديد، سيتم حذف الشعار القديم تلقائياً
- عند رفع مستند جديد، يمكن إنشاء مستند جديد أو تحديث مستند موجود
- يمكن تحديث نوع المستند فقط دون رفع ملف جديد

- Any field can be updated separately (Partial Updates are supported)
- When uploading a new logo file, the old logo will be automatically deleted
- When uploading a new document, you can create a new document or update an existing one
- You can update the document type only without uploading a new file

---

### 3. رفع الشعار (Logo) / Upload Logo

**الوصف / Description:**
يمكن رفع ملف الشعار كصورة. الشعار يتم حفظه في مجلد خاص بالـ ACC.

You can upload a logo file as an image. The logo is saved in a folder specific to the ACC.

**المتطلبات / Requirements:**
- نوع الملف: JPG, JPEG, PNG
- الحد الأقصى للحجم: 5MB
- Content-Type: `multipart/form-data`

**مثال على الطلب / Request Example:**

**Using cURL:**
```bash
curl -X PUT "https://example.com/api/acc/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: multipart/form-data" \
  -F "logo=@/path/to/logo.png"
```

**Using JavaScript (FormData):**
```javascript
const formData = new FormData();
formData.append('logo', logoFile); // logoFile is a File object

fetch('https://example.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: formData
});
```

**Response (200):**
```json
{
  "message": "Profile updated successfully",
  "profile": {
    "id": 1,
    "name": "ABC Accreditation Body",
    "logo_url": "https://example.com/storage/accs/1/logo/1234567890_1_logo.png",
    ...
  }
}
```

**Error Responses:**
- `422 Validation Error` - خطأ في التحقق من صحة الملف (الحجم، النوع، إلخ)
- `500 Server Error` - خطأ في الخادم أثناء رفع الملف

---

### 4. رفع المستندات (PDF Documents) / Upload PDF Documents

**الوصف / Description:**
يمكن رفع مستندات PDF كجزء من تحديث الـ Profile. يمكن إنشاء مستندات جديدة أو تحديث مستندات موجودة.

You can upload PDF documents as part of profile updates. You can create new documents or update existing ones.

**أنواع المستندات المدعومة / Supported Document Types:**
- `license` - رخصة
- `registration` - تسجيل
- `certificate` - شهادة
- `other` - أخرى

**المتطلبات / Requirements:**
- نوع الملف: PDF, JPG, JPEG, PNG
- الحد الأقصى للحجم: 10MB لكل ملف
- Content-Type: `multipart/form-data`

**هيكل المستندات / Documents Structure:**

المستندات يتم إرسالها كمصفوفة (array) في الطلب. كل عنصر في المصفوفة يحتوي على:

Documents are sent as an array in the request. Each element in the array contains:

- `id` (اختياري / optional): معرف المستند الموجود (للتحديث)
- `document_type` (مطلوب / required): نوع المستند
- `file` (اختياري / optional): ملف المستند (للمستندات الجديدة أو التحديث)

**مثال على الطلب / Request Examples:**

**إنشاء مستند جديد / Create New Document:**

**Using cURL:**
```bash
curl -X PUT "https://example.com/api/acc/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: multipart/form-data" \
  -F "documents[0][document_type]=license" \
  -F "documents[0][file]=@/path/to/license.pdf"
```

**Using JavaScript (FormData):**
```javascript
const formData = new FormData();
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', licenseFile); // licenseFile is a File object

fetch('https://example.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: formData
});
```

**تحديث مستند موجود / Update Existing Document:**

**Using cURL:**
```bash
curl -X PUT "https://example.com/api/acc/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: multipart/form-data" \
  -F "documents[0][id]=1" \
  -F "documents[0][document_type]=license" \
  -F "documents[0][file]=@/path/to/new_license.pdf"
```

**Using JavaScript (FormData):**
```javascript
const formData = new FormData();
formData.append('documents[0][id]', '1');
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', newLicenseFile);

fetch('https://example.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: formData
});
```

**تحديث نوع المستند فقط (بدون رفع ملف جديد) / Update Document Type Only:**

**Using cURL:**
```bash
curl -X PUT "https://example.com/api/acc/profile" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "documents": [
      {
        "id": 1,
        "document_type": "certificate"
      }
    ]
  }'
```

**رفع عدة مستندات في نفس الوقت / Upload Multiple Documents:**

**Using JavaScript (FormData):**
```javascript
const formData = new FormData();

// Document 1 - New document
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', licenseFile);

// Document 2 - Update existing document
formData.append('documents[1][id]', '2');
formData.append('documents[1][document_type]', 'registration');
formData.append('documents[1][file]', registrationFile);

// Document 3 - Update type only
formData.append('documents[2][id]', '3');
formData.append('documents[2][document_type]', 'certificate');

fetch('https://example.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: formData
});
```

**Response (200):**
```json
{
  "message": "Profile updated successfully",
  "profile": {
    "id": 1,
    "documents": [
      {
        "id": 1,
        "document_type": "license",
        "document_url": "https://example.com/storage/accs/1/documents/1234567890_1_license.pdf",
        "uploaded_at": "2024-01-01T00:00:00.000000Z",
        "verified": false,
        "verified_by": null,
        "verified_at": null
      }
    ],
    ...
  }
}
```

**Error Responses:**
- `422 Validation Error` - خطأ في التحقق من صحة الملف أو نوع المستند
  ```json
  {
    "message": "Validation failed",
    "errors": {
      "documents.0.document_type": ["The selected document type is invalid."],
      "documents.0.file": ["The file must be a file of type: pdf, jpg, jpeg, png."]
    }
  }
  ```
- `500 Server Error` - خطأ في الخادم أثناء رفع الملف

---

### 5. تحديث البيانات والمستندات معاً / Update Data and Documents Together

**الوصف / Description:**
يمكن تحديث البيانات الأساسية والشعار والمستندات في نفس الطلب.

You can update basic data, logo, and documents in the same request.

**مثال شامل / Complete Example:**

**Using JavaScript (FormData):**
```javascript
const formData = new FormData();

// Basic information
formData.append('name', 'Updated ACC Name');
formData.append('phone', '+1234567890');
formData.append('website', 'https://newwebsite.com');

// Logo
formData.append('logo', logoFile);

// Documents
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', licenseFile);

formData.append('documents[1][id]', '2');
formData.append('documents[1][document_type]', 'certificate');
formData.append('documents[1][file]', certificateFile);

fetch('https://example.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: formData
});
```

**Response (200):**
```json
{
  "message": "Profile updated successfully",
  "profile": {
    "id": 1,
    "name": "Updated ACC Name",
    "phone": "+1234567890",
    "website": "https://newwebsite.com",
    "logo_url": "https://example.com/storage/accs/1/logo/new_logo.png",
    "documents": [
      {
        "id": 1,
        "document_type": "license",
        "document_url": "https://example.com/storage/accs/1/documents/new_license.pdf",
        ...
      },
      {
        "id": 2,
        "document_type": "certificate",
        "document_url": "https://example.com/storage/accs/1/documents/new_certificate.pdf",
        ...
      }
    ],
    ...
  }
}
```

---

### 6. التحقق من Stripe Account / Verify Stripe Account

**Endpoint:** `POST /api/acc/profile/verify-stripe-account`

**الوصف / Description:**
التحقق من صحة معرف حساب Stripe Connect قبل إضافته إلى الـ Profile.

Verify the validity of a Stripe Connect account ID before adding it to the Profile.

**المتطلبات / Requirements:**
- Authentication: مطلوب (Bearer Token)
- Role: `acc_admin`
- Content-Type: `application/json`

**Request Body:**
```json
{
  "stripe_account_id": "acct_xxxxxxxxxxxxx"
}
```

**Response (200):**
```json
{
  "valid": true,
  "account": {
    "id": "acct_xxxxxxxxxxxxx",
    "type": "express",
    "country": "US"
  },
  "message": "Stripe account is valid and connected"
}
```

**Error Response (400):**
```json
{
  "valid": false,
  "error": "Invalid Stripe account",
  "message": "Stripe account verification failed. Please check that the account ID is correct and the account is properly connected to the platform."
}
```

---

## ملاحظات مهمة للمطورين / Important Notes for Developers

### 1. رفع الملفات / File Uploads

- **Content-Type**: يجب استخدام `multipart/form-data` عند رفع الملفات
- **File Size Limits**:
  - الشعار (Logo): حد أقصى 5MB
  - المستندات (Documents): حد أقصى 10MB لكل ملف
- **File Types**:
  - الشعار: JPG, JPEG, PNG
  - المستندات: PDF, JPG, JPEG, PNG

- **Content-Type**: Must use `multipart/form-data` when uploading files
- **File Size Limits**:
  - Logo: Maximum 5MB
  - Documents: Maximum 10MB per file
- **File Types**:
  - Logo: JPG, JPEG, PNG
  - Documents: PDF, JPG, JPEG, PNG

### 2. هيكل المستندات / Documents Structure

عند إرسال المستندات، يجب اتباع الهيكل التالي:

When sending documents, you must follow this structure:

- **للمستندات الجديدة / For New Documents:**
  ```
  documents[0][document_type] = "license"
  documents[0][file] = <file>
  ```

- **لتحديث مستند موجود / For Updating Existing Documents:**
  ```
  documents[0][id] = 1
  documents[0][document_type] = "license"
  documents[0][file] = <file>
  ```

- **لتحديث نوع المستند فقط / For Updating Document Type Only:**
  ```
  documents[0][id] = 1
  documents[0][document_type] = "certificate"
  ```

### 3. Partial Updates

- يمكن تحديث أي حقل بشكل منفصل
- لا حاجة لإرسال جميع الحقول في كل طلب
- الحقول غير المرسلة ستبقى كما هي

- Any field can be updated separately
- No need to send all fields in every request
- Fields not sent will remain unchanged

### 4. حذف الملفات القديمة / Old File Deletion

- عند رفع شعار جديد، يتم حذف الشعار القديم تلقائياً
- عند تحديث مستند موجود، يتم حذف الملف القديم تلقائياً
- الحذف يتم بعد التأكد من نجاح رفع الملف الجديد

- When uploading a new logo, the old logo is automatically deleted
- When updating an existing document, the old file is automatically deleted
- Deletion occurs after confirming successful upload of the new file

### 5. معالجة الأخطاء / Error Handling

**أخطاء التحقق / Validation Errors (422):**
```json
{
  "message": "Validation failed",
  "errors": {
    "logo": ["The logo must be an image."],
    "documents.0.file": ["The file must not be greater than 10240 kilobytes."]
  }
}
```

**أخطاء الخادم / Server Errors (500):**
```json
{
  "message": "Profile update failed"
}
```

في وضع التطوير (debug mode)، قد يتم إرجاع تفاصيل أكثر عن الخطأ.

In development mode (debug mode), more error details may be returned.

### 6. URLs للملفات / File URLs

- جميع روابط الملفات يتم إرجاعها كـ URLs كاملة
- الملفات مخزنة في `storage/app/public/accs/{acc_id}/`
- الشعار: `storage/app/public/accs/{acc_id}/logo/`
- المستندات: `storage/app/public/accs/{acc_id}/documents/`

- All file links are returned as full URLs
- Files are stored in `storage/app/public/accs/{acc_id}/`
- Logo: `storage/app/public/accs/{acc_id}/logo/`
- Documents: `storage/app/public/accs/{acc_id}/documents/`

---

## أمثلة كاملة / Complete Examples

### مثال 1: تحديث البيانات الأساسية فقط / Example 1: Update Basic Data Only

```javascript
fetch('https://example.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    name: 'Updated Name',
    phone: '+1234567890',
    website: 'https://example.com'
  })
});
```

### مثال 2: رفع شعار فقط / Example 2: Upload Logo Only

```javascript
const formData = new FormData();
formData.append('logo', logoFile);

fetch('https://example.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: formData
});
```

### مثال 3: رفع مستند جديد فقط / Example 3: Upload New Document Only

```javascript
const formData = new FormData();
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', documentFile);

fetch('https://example.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: formData
});
```

### مثال 4: تحديث شامل / Example 4: Complete Update

```javascript
const formData = new FormData();

// Basic data
formData.append('name', 'New Name');
formData.append('phone', '+1234567890');

// Logo
formData.append('logo', logoFile);

// Multiple documents
formData.append('documents[0][document_type]', 'license');
formData.append('documents[0][file]', licenseFile);

formData.append('documents[1][id]', '2');
formData.append('documents[1][document_type]', 'certificate');
formData.append('documents[1][file]', certificateFile);

fetch('https://example.com/api/acc/profile', {
  method: 'PUT',
  headers: {
    'Authorization': 'Bearer YOUR_TOKEN'
  },
  body: formData
});
```

---

## Error Codes

| Code | Description | الحالة |
|------|-------------|--------|
| 200 | Success | نجاح |
| 401 | Unauthorized | غير مصرح |
| 404 | Not Found | غير موجود |
| 422 | Validation Error | خطأ في التحقق |
| 500 | Server Error | خطأ في الخادم |

---

## Support

للمساعدة والدعم الفني، يرجى التواصل مع فريق التطوير.

For help and technical support, please contact the development team.
