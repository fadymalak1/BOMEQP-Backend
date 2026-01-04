# Instructor Profile API Documentation

## نظرة عامة / Overview

هذا التوثيق يشرح جميع الـ APIs المتعلقة ببيانات الـ Instructor Profile. يتيح هذا الـ API للـ Instructors عرض وتحديث بياناتهم الشخصية، بما في ذلك المعلومات الأساسية، السيرة الذاتية، الشهادات، والتخصصات.

This documentation explains all APIs related to Instructor Profile data. This API allows Instructors to view and update their personal information, including basic information, CV, certificates, and specializations.

---

## Endpoints

### 1. عرض بيانات الـ Profile / Get Profile

**Endpoint:** `GET /api/instructor/profile`

**الوصف / Description:**
يعيد جميع بيانات الـ Instructor Profile بما في ذلك المعلومات الشخصية، السيرة الذاتية، الشهادات، والتخصصات.

Returns all Instructor Profile data including personal information, CV, certificates, and specializations.

**المتطلبات / Requirements:**
- Authentication: مطلوب (Bearer Token)
- Role: `instructor`

**Response (200):**
```json
{
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "country": "Egypt",
    "city": "Cairo",
    "cv_url": "https://aeroenix.com/v1/api/storage/instructors/cv/cv_file.pdf",
    "certificates": [
      {
        "name": "Certificate Name",
        "issue_date": "2024-01-01",
        "url": "https://aeroenix.com/v1/api/storage/instructors/certificates/cert_file.pdf"
      }
    ],
    "specializations": ["Safety", "Training"],
    "status": "active",
    "training_center": {
      "id": 1,
      "name": "Training Center Name",
      "email": "tc@example.com"
    },
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "instructor",
      "status": "active"
    }
  }
}
```

**Error Responses:**
- `401 Unauthorized` - غير مسجل دخول أو Token غير صالح
- `404 Not Found` - الـ Instructor غير موجود

---

### 2. تحديث بيانات الـ Profile / Update Profile

**Endpoint:** `PUT /api/instructor/profile`

**الوصف / Description:**
يسمح للـ Instructor بتحديث بياناته الشخصية. يمكن تحديث الحقول التالية:
- الاسم الأول والأخير
- رقم الهاتف
- الدولة والمدينة
- السيرة الذاتية (CV)
- الشهادات (مع إمكانية رفع ملفات PDF)
- التخصصات

Allows the Instructor to update their personal information. The following fields can be updated:
- First and last name
- Phone number
- Country and city
- CV (Curriculum Vitae)
- Certificates (with PDF file upload capability)
- Specializations

**المتطلبات / Requirements:**
- Authentication: مطلوب (Bearer Token)
- Role: `instructor`
- Content-Type: `multipart/form-data` (لرفع الملفات)

**الحقول القابلة للتحديث / Updatable Fields:**

| الحقل / Field | النوع / Type | مطلوب / Required | الوصف / Description |
|---------------|--------------|------------------|---------------------|
| `first_name` | string | لا | الاسم الأول |
| `last_name` | string | لا | الاسم الأخير |
| `phone` | string | لا | رقم الهاتف |
| `country` | string | لا | الدولة |
| `city` | string | لا | المدينة |
| `cv` | file (PDF) | لا | ملف السيرة الذاتية (حد أقصى 10MB) |
| `certificates` | array | لا | مصفوفة من الشهادات |
| `certificates[].name` | string | نعم (مع certificates) | اسم الشهادة |
| `certificates[].issue_date` | date | نعم (مع certificates) | تاريخ الإصدار (YYYY-MM-DD) |
| `certificates[].certificate_file` | file (PDF) | لا | ملف الشهادة (PDF، حد أقصى 10MB) |
| `certificate_files[]` | array of files | لا | مصفوفة من ملفات الشهادات (بديل عن certificate_file) |
| `specializations` | array | لا | مصفوفة من التخصصات (نصوص) |

**الحقول التي لا يمكن تحديثها / Non-Updatable Fields:**
- `email` - البريد الإلكتروني (مرتبط بحساب المستخدم)
- `id_number` - رقم الهوية (معرّف فريد)
- `is_assessor` - يمكن فقط للـ Training Center تعديله

**ملاحظات مهمة / Important Notes:**

1. **رفع ملفات الشهادات / Certificate File Upload:**
   - يمكن رفع ملف PDF لكل شهادة
   - يمكن استخدام `certificate_files[]` array أو `certificates[].certificate_file`
   - إذا تم رفع ملف، سيتم إنشاء URL تلقائياً
   - إذا لم يتم رفع ملف، يمكن توفير URL يدوياً (لكن هذه الميزة غير مدعومة حالياً - يجب رفع الملف)

2. **السيرة الذاتية / CV:**
   - عند رفع CV جديد، يتم حذف الملف القديم تلقائياً
   - الملف يجب أن يكون PDF
   - الحد الأقصى للحجم: 10MB

3. **الشهادات / Certificates:**
   - كل شهادة يجب أن تحتوي على `name` و `issue_date`
   - يمكن رفع ملف PDF لكل شهادة
   - الملفات تُحفظ في `storage/app/public/instructors/certificates/`
   - يتم إنشاء URL تلقائياً للملفات المرفوعة

4. **التخصصات / Specializations:**
   - مصفوفة من النصوص
   - مثال: `["Safety", "Training", "Management"]`

**أمثلة على الاستخدام / Usage Examples:**

**مثال 1: تحديث البيانات الأساسية / Update Basic Information**
```
PUT /api/instructor/profile
Content-Type: multipart/form-data

first_name: John
last_name: Doe
phone: +1234567890
country: Egypt
city: Cairo
```

**مثال 2: رفع CV جديد / Upload New CV**
```
PUT /api/instructor/profile
Content-Type: multipart/form-data

cv: [PDF File]
```

**مثال 3: إضافة شهادات مع رفع ملفات / Add Certificates with File Upload**
```
PUT /api/instructor/profile
Content-Type: multipart/form-data

certificates[0][name]: Safety Certificate
certificates[0][issue_date]: 2024-01-01
certificates[0][certificate_file]: [PDF File 1]

certificates[1][name]: Training Certificate
certificates[1][issue_date]: 2024-06-15
certificates[1][certificate_file]: [PDF File 2]
```

**مثال 4: استخدام certificate_files array / Using certificate_files Array**
```
PUT /api/instructor/profile
Content-Type: multipart/form-data

certificates[0][name]: Safety Certificate
certificates[0][issue_date]: 2024-01-01
certificate_files[0]: [PDF File 1]

certificates[1][name]: Training Certificate
certificates[1][issue_date]: 2024-06-15
certificate_files[1]: [PDF File 2]
```

**مثال 5: تحديث التخصصات / Update Specializations**
```
PUT /api/instructor/profile
Content-Type: application/json

{
  "specializations": ["Safety", "Training", "Management"]
}
```

**Response (200):**
```json
{
  "message": "Profile updated successfully",
  "profile": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "id_number": "ID123456",
    "country": "Egypt",
    "city": "Cairo",
    "cv_url": "https://aeroenix.com/v1/api/storage/instructors/cv/cv_file.pdf",
    "certificates": [
      {
        "name": "Safety Certificate",
        "issue_date": "2024-01-01",
        "url": "https://aeroenix.com/v1/api/storage/instructors/certificates/cert_file.pdf"
      }
    ],
    "specializations": ["Safety", "Training"],
    "status": "active",
    "is_assessor": false,
    "training_center_id": 1,
    "training_center": {
      "id": 1,
      "name": "Training Center Name"
    },
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

**Error Responses:**
- `401 Unauthorized` - غير مسجل دخول أو Token غير صالح
- `404 Not Found` - الـ Instructor غير موجود
- `422 Validation Error` - خطأ في التحقق من البيانات
- `500 Internal Server Error` - خطأ في رفع الملفات

**أخطاء التحقق الشائعة / Common Validation Errors:**
- `certificates.*.name is required` - اسم الشهادة مطلوب
- `certificates.*.issue_date is required` - تاريخ الإصدار مطلوب
- `certificates.*.issue_date must be a valid date` - تاريخ غير صالح
- `cv must be a file` - CV يجب أن يكون ملف
- `cv must be a file of type: pdf` - CV يجب أن يكون PDF
- `cv may not be greater than 10240 kilobytes` - حجم CV أكبر من 10MB

---

## الوصول إلى الملفات / File Access

### 1. الوصول إلى ملف CV / Access CV File

**Endpoint:** `GET /api/storage/instructors/cv/{filename}`

**الوصف / Description:**
يعيد ملف السيرة الذاتية (CV) للـ Instructor.

Returns the Instructor's CV file.

**المتطلبات / Requirements:**
- Public endpoint (لا يتطلب authentication)

**Parameters:**
- `filename` - اسم الملف (path parameter)

**Response (200):**
- Content-Type: `application/pdf`
- يعيد ملف PDF مباشرة

**Error Responses:**
- `404 Not Found` - الملف غير موجود

---

### 2. الوصول إلى ملفات الشهادات / Access Certificate Files

**Endpoint:** `GET /api/storage/instructors/certificates/{filename}`

**الوصف / Description:**
يعيد ملف شهادة للـ Instructor.

Returns an Instructor's certificate file.

**المتطلبات / Requirements:**
- Public endpoint (لا يتطلب authentication)

**Parameters:**
- `filename` - اسم الملف (path parameter)

**Response (200):**
- Content-Type: `application/pdf`
- يعيد ملف PDF مباشرة

**Error Responses:**
- `404 Not Found` - الملف غير موجود

---

## هيكل البيانات / Data Structure

### Profile Object

```json
{
  "id": 1,
  "first_name": "John",
  "last_name": "Doe",
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "id_number": "ID123456",
  "country": "Egypt",
  "city": "Cairo",
  "cv_url": "https://aeroenix.com/v1/api/storage/instructors/cv/cv_file.pdf",
  "certificates": [
    {
      "name": "Certificate Name",
      "issue_date": "2024-01-01",
      "url": "https://aeroenix.com/v1/api/storage/instructors/certificates/cert_file.pdf"
    }
  ],
  "specializations": ["Safety", "Training"],
  "status": "active",
  "is_assessor": false,
  "training_center_id": 1,
  "training_center": {
    "id": 1,
    "name": "Training Center Name",
    "email": "tc@example.com",
    "phone": "+1234567890",
    "country": "Egypt",
    "city": "Cairo"
  },
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "instructor",
    "status": "active"
  },
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-15T10:30:00.000000Z"
}
```

### Certificate Object

```json
{
  "name": "Certificate Name",
  "issue_date": "2024-01-01",
  "url": "https://aeroenix.com/v1/api/storage/instructors/certificates/cert_file.pdf"
}
```

**الحقول / Fields:**
- `name` (string, required) - اسم الشهادة
- `issue_date` (date, required) - تاريخ الإصدار (YYYY-MM-DD)
- `url` (string, nullable) - رابط ملف الشهادة (يتم إنشاؤه تلقائياً عند رفع الملف)

---

## سيناريوهات الاستخدام / Use Cases

### 1. تحديث البيانات الأساسية / Update Basic Information

**السيناريو / Scenario:**
يريد الـ Instructor تحديث رقم هاتفه ومدينته.

**الخطوات / Steps:**
1. إرسال طلب PUT إلى `/api/instructor/profile`
2. إرسال `phone` و `city` في الـ request body
3. استلام الـ response مع البيانات المحدثة

---

### 2. رفع CV جديد / Upload New CV

**السيناريو / Scenario:**
يريد الـ Instructor رفع CV جديد ليحل محل القديم.

**الخطوات / Steps:**
1. إرسال طلب PUT إلى `/api/instructor/profile` مع Content-Type: `multipart/form-data`
2. إرفاق ملف PDF في حقل `cv`
3. يتم حذف الملف القديم تلقائياً
4. استلام الـ response مع `cv_url` الجديد

---

### 3. إضافة شهادات جديدة / Add New Certificates

**السيناريو / Scenario:**
يريد الـ Instructor إضافة شهادات جديدة مع رفع ملفات PDF.

**الخطوات / Steps:**
1. إرسال طلب PUT إلى `/api/instructor/profile` مع Content-Type: `multipart/form-data`
2. إرسال بيانات الشهادات في `certificates[]` array
3. إرفاق ملفات PDF في `certificates[].certificate_file` أو `certificate_files[]`
4. استلام الـ response مع URLs للملفات المرفوعة

---

### 4. تحديث التخصصات / Update Specializations

**السيناريو / Scenario:**
يريد الـ Instructor تحديث قائمة تخصصاته.

**الخطوات / Steps:**
1. إرسال طلب PUT إلى `/api/instructor/profile`
2. إرسال `specializations` array في الـ request body
3. استلام الـ response مع التخصصات المحدثة

---

## القيود والقيود / Limitations and Constraints

### 1. الحقول غير القابلة للتحديث / Non-Updatable Fields
- `email` - لا يمكن تغييره (مرتبط بحساب المستخدم)
- `id_number` - لا يمكن تغييره (معرّف فريد)
- `is_assessor` - يمكن فقط للـ Training Center تعديله
- `status` - يتم إدارته من قبل النظام
- `training_center_id` - لا يمكن تغييره

### 2. قيود الملفات / File Constraints
- **CV:**
  - نوع الملف: PDF فقط
  - الحد الأقصى للحجم: 10MB
  - عند رفع ملف جديد، يتم حذف القديم تلقائياً

- **Certificates:**
  - نوع الملف: PDF فقط
  - الحد الأقصى للحجم: 10MB لكل ملف
  - يمكن رفع ملفات متعددة في نفس الطلب

### 3. قيود البيانات / Data Constraints
- `first_name` و `last_name`: حد أقصى 255 حرف
- `phone`: حد أقصى 255 حرف
- `country` و `city`: حد أقصى 255 حرف
- `certificates[].name`: حد أقصى 255 حرف
- `certificates[].issue_date`: يجب أن يكون تاريخ صالح (YYYY-MM-DD)

---

## الأخطاء الشائعة وحلولها / Common Errors and Solutions

### 1. خطأ 401 Unauthorized
**السبب / Cause:** Token غير صالح أو منتهي الصلاحية

**الحل / Solution:**
- التحقق من صحة Token
- إعادة تسجيل الدخول للحصول على Token جديد

---

### 2. خطأ 404 Not Found
**السبب / Cause:** الـ Instructor غير موجود

**الحل / Solution:**
- التحقق من أن المستخدم مسجل كـ Instructor
- التحقق من أن الـ email في Token يطابق email الـ Instructor

---

### 3. خطأ 422 Validation Error
**السبب / Cause:** بيانات غير صالحة

**الحل / Solution:**
- التحقق من أن جميع الحقول المطلوبة موجودة
- التحقق من صحة تنسيق البيانات (التواريخ، الملفات، إلخ)
- التحقق من قيود الحجم والنوع للملفات

---

### 4. خطأ 500 Internal Server Error عند رفع الملفات
**السبب / Cause:** مشكلة في رفع الملفات

**الحل / Solution:**
- التحقق من أن الملف PDF صالح
- التحقق من أن حجم الملف أقل من 10MB
- التحقق من صلاحيات الكتابة في مجلد storage
- إعادة المحاولة

---

## ملاحظات إضافية / Additional Notes

### 1. تحديث اسم المستخدم / User Name Update
عند تحديث `first_name` أو `last_name`، يتم تحديث اسم المستخدم في جدول `users` تلقائياً.

When `first_name` or `last_name` is updated, the user's name in the `users` table is automatically updated.

### 2. حذف الملفات القديمة / Old File Deletion
عند رفع CV جديد أو شهادة جديدة، يتم حذف الملف القديم تلقائياً لتوفير المساحة.

When a new CV or certificate is uploaded, the old file is automatically deleted to save space.

### 3. URLs للملفات / File URLs
جميع URLs للملفات المرفوعة تكون في الصيغة:
```
https://aeroenix.com/v1/api/storage/instructors/{type}/{filename}
```

حيث `{type}` يمكن أن يكون:
- `cv` - للسيرة الذاتية
- `certificates` - للشهادات

All uploaded file URLs follow the format:
```
https://aeroenix.com/v1/api/storage/instructors/{type}/{filename}
```

Where `{type}` can be:
- `cv` - for CV
- `certificates` - for certificates

### 4. التحديث الجزئي / Partial Updates
يمكن تحديث أي حقل بشكل منفصل. لا حاجة لإرسال جميع الحقول في كل طلب.

Any field can be updated independently. There's no need to send all fields in every request.

---

## الدعم / Support

إذا واجهت أي مشاكل أو لديك أسئلة، يرجى التواصل مع فريق التطوير.

If you encounter any issues or have questions, please contact the development team.

---

**آخر تحديث / Last Updated:** 2026-01-03  
**الإصدار / Version:** 1.0.0

