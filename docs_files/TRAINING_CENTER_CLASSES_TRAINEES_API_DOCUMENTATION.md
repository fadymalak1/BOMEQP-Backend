# Training Center Classes Trainees API Documentation

## Overview
تم تحديث API الخاص بـ Training Classes (Training Center) لإضافة خاصية ربط المتدربين (Trainees) بالصفوف التدريبية (Training Classes). يمكن الآن إضافة المتدربين عند إنشاء صف تدريبي جديد أو تحديث صف تدريبي موجود.

**ملاحظة مهمة**: هذا الـ API خاص بـ Training Center وليس Admin. يمكن لـ Training Center فقط إدارة المتدربين التابعين له.

---

## 1. Create Training Class API (POST)

### Endpoint
`POST /training-center/classes`

### Description
إنشاء صف تدريبي جديد مع إمكانية إضافة متدربين مباشرة عند الإنشاء. يجب أن ينتمي المتدربون لنفس Training Center.

### Authentication
يحتاج إلى Authentication Token (Bearer Token) مع role: `training_center_admin`

### Request Body

#### Required Fields
- **course_id** (integer, required): معرف الدورة التدريبية
- **class_id** (integer, required): معرف الصف (Class Model)
- **instructor_id** (integer, required): معرف المدرب
- **start_date** (string, date, required): تاريخ البدء (صيغة YYYY-MM-DD)
- **end_date** (string, date, required): تاريخ الانتهاء (يجب أن يكون بعد تاريخ البدء)
- **location** (string, required): نوع الموقع - يجب أن يكون `physical` أو `online`

#### Optional Fields
- **exam_date** (string, date, optional): تاريخ الامتحان (يجب أن يكون بعد أو يساوي تاريخ البدء)
- **exam_score** (number, float, optional): درجة الامتحان (0-100)
- **schedule_json** (array, optional): جدول الحصص بصيغة JSON
- **location_details** (string, optional): تفاصيل الموقع
- **trainee_ids** (array of integers, optional): مصفوفة تحتوي على معرفات المتدربين المراد إضافتهم للصف التدريبي

### Request Example
```json
{
  "course_id": 1,
  "class_id": 1,
  "instructor_id": 1,
  "start_date": "2024-01-15",
  "end_date": "2024-01-20",
  "location": "physical",
  "location_details": "Room 101",
  "trainee_ids": [1, 2, 3]
}
```

### Response Success (201 Created)
```json
{
  "class": {
    "id": 26,
    "training_center_id": 1,
    "course_id": 1,
    "class_id": 1,
    "instructor_id": 1,
    "start_date": "2024-01-15",
    "end_date": "2024-01-20",
    "status": "scheduled",
    "location": "physical",
    "enrolled_count": 3,
    "course": { ... },
    "instructor": { ... },
    "trainees": [
      {
        "id": 1,
        "first_name": "...",
        "last_name": "...",
        "email": "...",
        "pivot": {
          "training_class_id": 26,
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
- **403 Forbidden**: في حالة عدم وجود صلاحية ACC للدورة التدريبية
- **404 Not Found**: في حالة عدم وجود Training Center
- **422 Validation Error**: في حالة وجود أخطاء في البيانات المرسلة
  - `course_id` غير موجود
  - `class_id` غير موجود
  - `instructor_id` غير موجود
  - `trainee_ids` يحتوي على معرفات غير موجودة
  - `trainee_ids` يحتوي على متدربين لا ينتمون لنفس Training Center
  - تواريخ غير صحيحة

### Notes
- إذا تم إرسال `trainee_ids`، سيتم ربط جميع المتدربين بالصف التدريبي مباشرة
- **مهم**: جميع المتدربين يجب أن ينتموا لنفس Training Center الخاص بالمستخدم المُصادق عليه
- حالة التسجيل الافتراضية للمتدربين هي `enrolled`
- يتم تسجيل وقت التسجيل (`enrolled_at`) تلقائياً
- يتم تحديث `enrolled_count` تلقائياً

---

## 2. Update Training Class API (PUT)

### Endpoint
`PUT /training-center/classes/{id}`

### Description
تحديث معلومات الصف التدريبي مع إمكانية تحديث قائمة المتدربين المسجلين. يجب أن ينتمي المتدربون لنفس Training Center.

### Authentication
يحتاج إلى Authentication Token (Bearer Token) مع role: `training_center_admin`

### Path Parameters
- **id** (integer, required): معرف الصف التدريبي المراد تحديثه

### Request Body

#### Optional Fields (جميع الحقول اختيارية)
- **course_id** (integer, optional): معرف الدورة التدريبية
- **class_id** (integer, optional): معرف الصف (Class Model)
- **instructor_id** (integer, optional): معرف المدرب
- **start_date** (string, date, optional): تاريخ البدء
- **end_date** (string, date, optional): تاريخ الانتهاء (يجب أن يكون بعد تاريخ البدء)
- **exam_date** (string, date, optional): تاريخ الامتحان
- **exam_score** (number, float, optional): درجة الامتحان (0-100)
- **schedule_json** (array, optional): جدول الحصص
- **location** (string, optional): نوع الموقع - `physical` أو `online`
- **location_details** (string, optional): تفاصيل الموقع
- **status** (string, optional): حالة الصف - `scheduled`, `in_progress`, `completed`, `cancelled`
- **trainee_ids** (array of integers, optional): مصفوفة تحتوي على معرفات المتدربين الجديدة

### Request Example
```json
{
  "start_date": "2024-01-16",
  "location_details": "Room 102",
  "trainee_ids": [1, 2, 4]
}
```

### Response Success (200 OK)
```json
{
  "message": "Class updated successfully",
  "class": {
    "id": 26,
    "training_center_id": 1,
    "course_id": 1,
    "class_id": 1,
    "instructor_id": 1,
    "start_date": "2024-01-16",
    "end_date": "2024-01-20",
    "status": "scheduled",
    "location": "physical",
    "location_details": "Room 102",
    "enrolled_count": 3,
    "course": { ... },
    "instructor": { ... },
    "trainees": [
      {
        "id": 1,
        "first_name": "...",
        "last_name": "...",
        "pivot": {
          "training_class_id": 26,
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
- **404 Not Found**: في حالة عدم وجود الصف التدريبي أو Training Center
- **422 Validation Error**: في حالة وجود أخطاء في البيانات المرسلة
  - `trainee_ids` يحتوي على متدربين لا ينتمون لنفس Training Center

### Important Notes
- **سلوك `trainee_ids` في Update**: عند إرسال `trainee_ids` في طلب التحديث، يتم استبدال قائمة المتدربين الحالية بالقائمة الجديدة (Sync). أي متدرب موجود في القائمة القديمة وغير موجود في القائمة الجديدة سيتم إزالته من الصف التدريبي.

- **مثال على السلوك**:
  - الصف التدريبي الحالي يحتوي على المتدربين: [1, 2, 3]
  - تم إرسال طلب تحديث بـ `trainee_ids: [2, 4, 5]`
  - النتيجة: الصف التدريبي سيحتوي على المتدربين: [2, 4, 5]
  - تمت إزالة المتدربين: [1, 3]
  - تمت إضافة المتدربين: [4, 5]

- إذا لم ترسل `trainee_ids` في طلب التحديث، لن يتم تغيير قائمة المتدربين الحالية.

- إذا أردت إزالة جميع المتدربين، أرسل `trainee_ids` كمصفوفة فارغة: `[]`

- **التحقق من Ownership**: جميع المتدربين في `trainee_ids` يجب أن ينتموا لنفس Training Center. إذا أرسلت متدرباً لا ينتمي لـ Training Center الخاص بك، سيتم إرجاع خطأ 422.

- يتم تحديث `enrolled_count` تلقائياً بناءً على عدد المتدربين الجديد

---

## 3. Get Training Class Details API (GET)

### Endpoint
`GET /training-center/classes/{id}`

### Description
الحصول على تفاصيل الصف التدريبي بما في ذلك المتدربين المسجلين.

### Authentication
يحتاج إلى Authentication Token (Bearer Token) مع role: `training_center_admin`

### Path Parameters
- **id** (integer, required): معرف الصف التدريبي

### Response Success (200 OK)
```json
{
  "class": {
    "id": 26,
    "training_center_id": 1,
    "course_id": 1,
    "class_id": 1,
    "instructor_id": 1,
    "start_date": "2024-01-15",
    "end_date": "2024-01-20",
    "status": "scheduled",
    "location": "physical",
    "enrolled_count": 3,
    "course": { ... },
    "instructor": { ... },
    "trainingCenter": { ... },
    "classModel": { ... },
    "completion": null,
    "trainees": [
      {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "phone": "...",
        "id_number": "...",
        "pivot": {
          "training_class_id": 26,
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
- **404 Not Found**: في حالة عدم وجود الصف التدريبي أو Training Center

### Changes
- تم تحديث الاستجابة لتشمل قائمة المتدربين (`trainees`) تلقائياً

---

## 4. List Training Classes API (GET)

### Endpoint
`GET /training-center/classes`

### Description
الحصول على قائمة جميع الصفوف التدريبية الخاصة بـ Training Center مع المتدربين المسجلين.

### Authentication
يحتاج إلى Authentication Token (Bearer Token) مع role: `training_center_admin`

### Response Success (200 OK)
```json
{
  "classes": [
    {
      "id": 26,
      "training_center_id": 1,
      "course_id": 1,
      "class_id": 1,
      "instructor_id": 1,
      "start_date": "2024-01-15",
      "end_date": "2024-01-20",
      "status": "scheduled",
      "enrolled_count": 3,
      "trainees_count": 3,
      "course": { ... },
      "instructor": { ... },
      "classModel": { ... },
      "trainees": [
        {
          "id": 1,
          "first_name": "John",
          "last_name": "Doe",
          "email": "john@example.com",
          "phone": "...",
          "id_number": "...",
          "status": "enrolled",
          "enrolled_at": "2026-01-11T12:00:00.000000Z",
          "completed_at": null
        },
        ...
      ]
    },
    ...
  ]
}
```

### Notes
- الاستجابة تتضمن `trainees_count` للتوافق مع الإصدارات القديمة
- كل صف تدريبي يحتوي على قائمة المتدربين (`trainees`) مع معلومات التسجيل

---

## Trainee Pivot Data Structure

عند استقبال بيانات المتدربين في الاستجابات، ستجد حقل `pivot` يحتوي على المعلومات التالية:

- **training_class_id**: معرف الصف التدريبي
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

### Create Training Class
- `course_id`: مطلوب، يجب أن يكون موجود في جدول courses
- `class_id`: مطلوب، يجب أن يكون موجود في جدول classes
- `instructor_id`: مطلوب، يجب أن يكون موجود في جدول instructors
- `start_date`: مطلوب، يجب أن يكون تاريخ صحيح
- `end_date`: مطلوب، يجب أن يكون تاريخ صحيح ويأتي بعد `start_date`
- `location`: مطلوب، يجب أن يكون `physical` أو `online`
- `exam_date`: اختياري، إذا أُرسل يجب أن يكون بعد أو يساوي `start_date`
- `exam_score`: اختياري، إذا أُرسل يجب أن يكون بين 0 و 100
- `trainee_ids`: اختياري، يجب أن يكون مصفوفة من الأعداد الصحيحة
- `trainee_ids.*`: كل عنصر في المصفوفة يجب أن:
  - يكون موجود في جدول trainees
  - ينتمي لنفس Training Center الخاص بالمستخدم

### Update Training Class
- جميع الحقول اختيارية (optional)
- نفس قواعد التحقق السابقة تنطبق على الحقول المرسلة
- `trainee_ids`: إذا أُرسل، يجب أن يكون جميع المتدربين ينتمون لنفس Training Center

---

## Security Notes

- **Ownership Verification**: API يتحقق تلقائياً من أن المتدربين المراد إضافتهم ينتمون لنفس Training Center الخاص بالمستخدم المُصادق عليه
- **Access Control**: يمكن لـ Training Center فقط الوصول إلى الصفوف التدريبية الخاصة به
- إذا حاولت إضافة متدرب لا ينتمي لـ Training Center الخاص بك، سيتم إرجاع خطأ 422 مع رسالة: "Some trainee IDs do not belong to your training center"

---

## Common Use Cases

### Use Case 1: إنشاء صف تدريبي بدون متدربين
```json
POST /training-center/classes
{
  "course_id": 1,
  "class_id": 1,
  "instructor_id": 1,
  "start_date": "2024-01-15",
  "end_date": "2024-01-20",
  "location": "physical"
}
```
(لا حاجة لإرسال `trainee_ids`)

### Use Case 2: إنشاء صف تدريبي مع متدربين
```json
POST /training-center/classes
{
  "course_id": 1,
  "class_id": 1,
  "instructor_id": 1,
  "start_date": "2024-01-15",
  "end_date": "2024-01-20",
  "location": "physical",
  "trainee_ids": [1, 2, 3]
}
```

### Use Case 3: تحديث معلومات الصف التدريبي فقط (بدون تغيير المتدربين)
```json
PUT /training-center/classes/26
{
  "location_details": "Room 102",
  "status": "in_progress"
}
```
(لا ترسل `trainee_ids` إذا لم ترد تغيير قائمة المتدربين)

### Use Case 4: تحديث قائمة المتدربين فقط
```json
PUT /training-center/classes/26
{
  "trainee_ids": [2, 4, 5]
}
```

### Use Case 5: إزالة جميع المتدربين من صف تدريبي
```json
PUT /training-center/classes/26
{
  "trainee_ids": []
}
```

---

## Differences from Admin Classes API

### Training Center Classes API (`/training-center/classes`)
- يتعامل مع `TrainingClass` (الصفوف التدريبية الفعلية)
- يمكن إدارة المتدربين التابعين لنفس Training Center فقط
- يحتوي على معلومات إضافية مثل: `start_date`, `end_date`, `instructor_id`, `location`, `schedule_json`
- يستخدم جدول `trainee_training_class` للربط بين المتدربين والصفوف التدريبية

### Admin Classes API (`/admin/classes`)
- يتعامل مع `ClassModel` (نموذج الصف)
- يمكن إدارة جميع المتدربين في النظام
- يحتوي على معلومات أساسية: `course_id`, `name`, `status`
- يستخدم جدول `class_trainee` للربط بين المتدربين ونموذج الصف

---

## Testing Checklist

عند اختبار الـ API، تأكد من:

- [ ] إنشاء صف تدريبي بدون متدربين
- [ ] إنشاء صف تدريبي مع متدربين ينتمون لنفس Training Center
- [ ] محاولة إضافة متدرب لا ينتمي لـ Training Center (يجب أن يفشل)
- [ ] تحديث معلومات الصف التدريبي بدون تغيير المتدربين
- [ ] تحديث قائمة المتدربين (sync)
- [ ] إزالة جميع المتدربين
- [ ] التحقق من صحة البيانات المرجعة (pivot data)
- [ ] التحقق من تحديث `enrolled_count` تلقائياً
- [ ] التحقق من أخطاء التحقق (validation errors)
- [ ] التحقق من Authentication والصلاحيات

---

## Questions or Issues

إذا كان لديك أي استفسارات أو مشاكل في التكامل مع الـ API، يرجى التواصل مع فريق Backend.

