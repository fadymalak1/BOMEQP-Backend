# Class Trainees API Documentation

## Overview
تم تحديث API الخاص بالـ Classes لإضافة خاصية ربط المتدربين (Trainees) بالصفوف (Classes). يمكن الآن إضافة المتدربين عند إنشاء صف جديد أو تحديث صف موجود.

---

## 1. Create Class API (POST)

### Endpoint
`POST /admin/classes`

### Description
إنشاء صف جديد مع إمكانية إضافة متدربين مباشرة عند الإنشاء.

### Authentication
يحتاج إلى Authentication Token (Bearer Token)

### Request Body

#### Required Fields
- **course_id** (integer, required): معرف الدورة التدريبية
- **name** (string, required): اسم الصف (يجب أن يكون فريد)
- **status** (string, required): حالة الصف - يجب أن يكون `active` أو `inactive`

#### Optional Fields
- **trainee_ids** (array of integers, optional): مصفوفة تحتوي على معرفات المتدربين المراد إضافتهم للصف

### Request Example
```json
{
  "course_id": 1,
  "name": "Class A",
  "status": "active",
  "trainee_ids": [1, 2, 3]
}
```

### Response Success (201 Created)
```json
{
  "message": "Class created successfully",
  "class": {
    "id": 1,
    "course_id": 1,
    "name": "Class A",
    "status": "active",
    "created_by": 1,
    "course": { ... },
    "trainees": [
      {
        "id": 1,
        "first_name": "...",
        "last_name": "...",
        "pivot": {
          "class_id": 1,
          "trainee_id": 1,
          "status": "enrolled",
          "enrolled_at": "2026-01-11T12:00:00.000000Z"
        }
      },
      ...
    ]
  }
}
```

### Response Errors
- **401 Unauthorized**: في حالة عدم وجود token أو token غير صالح
- **422 Validation Error**: في حالة وجود أخطاء في البيانات المرسلة
  - `course_id` غير موجود في قاعدة البيانات
  - `name` مستخدم بالفعل
  - `status` قيمة غير صحيحة
  - `trainee_ids` يحتوي على معرفات غير موجودة في قاعدة البيانات

### Notes
- إذا تم إرسال `trainee_ids`، سيتم ربط جميع المتدربين بالصف مباشرة
- حالة التسجيل الافتراضية للمتدربين هي `enrolled`
- يتم تسجيل وقت التسجيل (`enrolled_at`) تلقائياً

---

## 2. Update Class API (PUT)

### Endpoint
`PUT /admin/classes/{id}`

### Description
تحديث معلومات الصف مع إمكانية تحديث قائمة المتدربين المسجلين.

### Authentication
يحتاج إلى Authentication Token (Bearer Token)

### Path Parameters
- **id** (integer, required): معرف الصف المراد تحديثه

### Request Body

#### Optional Fields (جميع الحقول اختيارية)
- **course_id** (integer, optional): معرف الدورة التدريبية
- **name** (string, optional): اسم الصف (يجب أن يكون فريد إذا تم تغييره)
- **status** (string, optional): حالة الصف - `active` أو `inactive`
- **trainee_ids** (array of integers, optional): مصفوفة تحتوي على معرفات المتدربين الجديدة

### Request Example
```json
{
  "name": "Class A Updated",
  "status": "active",
  "trainee_ids": [1, 2, 4]
}
```

### Response Success (200 OK)
```json
{
  "message": "Class updated successfully",
  "class": {
    "id": 1,
    "course_id": 1,
    "name": "Class A Updated",
    "status": "active",
    "course": { ... },
    "trainees": [
      {
        "id": 1,
        "first_name": "...",
        "last_name": "...",
        "pivot": {
          "class_id": 1,
          "trainee_id": 1,
          "status": "enrolled",
          "enrolled_at": "2026-01-11T12:00:00.000000Z"
        }
      },
      ...
    ]
  }
}
```

### Response Errors
- **401 Unauthorized**: في حالة عدم وجود token أو token غير صالح
- **404 Not Found**: في حالة عدم وجود الصف المطلوب
- **422 Validation Error**: في حالة وجود أخطاء في البيانات المرسلة

### Important Notes
- **سلوك `trainee_ids` في Update**: عند إرسال `trainee_ids` في طلب التحديث، يتم استبدال قائمة المتدربين الحالية بالقائمة الجديدة (Sync). أي متدرب موجود في القائمة القديمة وغير موجود في القائمة الجديدة سيتم إزالته من الصف.

- **مثال على السلوك**:
  - الصف الحالي يحتوي على المتدربين: [1, 2, 3]
  - تم إرسال طلب تحديث بـ `trainee_ids: [2, 4, 5]`
  - النتيجة: الصف سيحتوي على المتدربين: [2, 4, 5]
  - تمت إزالة المتدربين: [1, 3]
  - تمت إضافة المتدربين: [4, 5]

- إذا لم ترسل `trainee_ids` في طلب التحديث، لن يتم تغيير قائمة المتدربين الحالية.

- إذا أردت إزالة جميع المتدربين، أرسل `trainee_ids` كمصفوفة فارغة: `[]`

---

## 3. Get Class Details API (GET)

### Endpoint
`GET /admin/classes/{id}`

### Description
الحصول على تفاصيل الصف بما في ذلك المتدربين المسجلين.

### Authentication
يحتاج إلى Authentication Token (Bearer Token)

### Path Parameters
- **id** (integer, required): معرف الصف

### Response Success (200 OK)
```json
{
  "class": {
    "id": 1,
    "course_id": 1,
    "name": "Class A",
    "status": "active",
    "course": { ... },
    "trainees": [
      {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "pivot": {
          "class_id": 1,
          "trainee_id": 1,
          "status": "enrolled",
          "enrolled_at": "2026-01-11T12:00:00.000000Z",
          "completed_at": null
        }
      },
      ...
    ]
  }
}
```

### Response Errors
- **401 Unauthorized**: في حالة عدم وجود token
- **404 Not Found**: في حالة عدم وجود الصف

### Changes
- تم تحديث الاستجابة لتشمل قائمة المتدربين (`trainees`) تلقائياً

---

## 4. List Classes API (GET)

### Endpoint
`GET /admin/classes`

### Description
الحصول على قائمة جميع الصفوف مع المتدربين المسجلين.

### Authentication
يحتاج إلى Authentication Token (Bearer Token)

### Query Parameters (Optional)
- **course_id** (integer, optional): تصفية الصفوف حسب معرف الدورة

### Response Success (200 OK)
```json
{
  "classes": [
    {
      "id": 1,
      "course_id": 1,
      "name": "Class A",
      "status": "active",
      "course": { ... },
      "trainees": [ ... ]
    },
    ...
  ]
}
```

### Changes
- تم تحديث الاستجابة لتشمل قائمة المتدربين (`trainees`) لكل صف تلقائياً

---

## Trainee Pivot Data Structure

عند استقبال بيانات المتدربين في الاستجابات، ستجد حقل `pivot` يحتوي على المعلومات التالية:

- **class_id**: معرف الصف
- **trainee_id**: معرف المتدرب
- **status**: حالة التسجيل - القيم المحتملة:
  - `enrolled`: مسجل
  - `completed`: مكتمل
  - `dropped`: منسحب
  - `failed`: فاشل
- **enrolled_at**: تاريخ ووقت التسجيل
- **completed_at**: تاريخ ووقت الإتمام (null إذا لم يكتمل)

---

## Validation Rules Summary

### Create Class
- `course_id`: مطلوب، يجب أن يكون موجود في جدول courses
- `name`: مطلوب، يجب أن يكون فريد في جدول classes
- `status`: مطلوب، يجب أن يكون `active` أو `inactive`
- `trainee_ids`: اختياري، يجب أن يكون مصفوفة من الأعداد الصحيحة
- `trainee_ids.*`: كل عنصر في المصفوفة يجب أن يكون موجود في جدول trainees

### Update Class
- `course_id`: اختياري، إذا أُرسل يجب أن يكون موجود في جدول courses
- `name`: اختياري، إذا أُرسل يجب أن يكون فريد (باستثناء الصف الحالي)
- `status`: اختياري، إذا أُرسل يجب أن يكون `active` أو `inactive`
- `trainee_ids`: اختياري، إذا أُرسل يجب أن يكون مصفوفة من الأعداد الصحيحة
- `trainee_ids.*`: كل عنصر في المصفوفة يجب أن يكون موجود في جدول trainees

---

## Common Use Cases

### Use Case 1: إنشاء صف بدون متدربين
```json
POST /admin/classes
{
  "course_id": 1,
  "name": "Class A",
  "status": "active"
}
```
(لا حاجة لإرسال `trainee_ids`)

### Use Case 2: إنشاء صف مع متدربين
```json
POST /admin/classes
{
  "course_id": 1,
  "name": "Class A",
  "status": "active",
  "trainee_ids": [1, 2, 3]
}
```

### Use Case 3: تحديث اسم الصف فقط (بدون تغيير المتدربين)
```json
PUT /admin/classes/1
{
  "name": "Class A Updated"
}
```
(لا ترسل `trainee_ids` إذا لم ترد تغيير قائمة المتدربين)

### Use Case 4: تحديث قائمة المتدربين فقط
```json
PUT /admin/classes/1
{
  "trainee_ids": [2, 4, 5]
}
```

### Use Case 5: إزالة جميع المتدربين من صف
```json
PUT /admin/classes/1
{
  "trainee_ids": []
}
```

---

## Testing Checklist

عند اختبار الـ API، تأكد من:

- [ ] إنشاء صف بدون متدربين
- [ ] إنشاء صف مع متدربين
- [ ] تحديث معلومات الصف بدون تغيير المتدربين
- [ ] تحديث قائمة المتدربين (sync)
- [ ] إزالة جميع المتدربين
- [ ] التحقق من صحة البيانات المرجعة (pivot data)
- [ ] التحقق من أخطاء التحقق (validation errors)
- [ ] التحقق من Authentication

---

## Questions or Issues

إذا كان لديك أي استفسارات أو مشاكل في التكامل مع الـ API، يرجى التواصل مع فريق Backend.

