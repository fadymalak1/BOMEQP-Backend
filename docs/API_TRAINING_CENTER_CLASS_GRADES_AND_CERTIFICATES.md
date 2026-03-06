## Training Center – Class Grades & Certificates API Changes

هذا الملف يشرح كل التعديلات التي تمت على APIs الخاصة بالـ **Training Center** في هذه الـ feature، عشان الـ Frontend يعرف يتعامل مع الدرجات، حالات النجاح/الرسوب، وتوليد الشهادات.

---

### 1. تغييرات على بيانات الـ Class

#### 1.1 حقل جديد: `success_grade` على مستوى الـ class

**الموديل**: `TrainingClass`

- تم إضافة الحقل:
  - `success_grade` (decimal) في جدول `training_classes`.
- في الـ model:
  - مضاف في `fillable`.
  - مضاف في `casts` كـ `decimal:2`.

#### 1.2 إنشاء class – `POST /api/training-center/classes`

تم إضافة حقل جديد في الـ body:

```json
{
  "course_id": 1,
  "name": "Class A - March 2026",
  "instructor_id": 5,
  "start_date": "2026-03-10",
  "end_date": "2026-03-15",
  "exam_date": "2026-03-16",
  "exam_score": 100,
  "success_grade": 60,
  "location": "online",
  "location_details": "Zoom link",
  "schedule_json": [],
  "trainee_ids": [10, 11, 12]
}
```

- **Validations**:
  - `exam_score`: `nullable|numeric|min:0|max:100`
  - `success_grade`: `nullable|numeric|min:0|max:100|lte:exam_score`

#### 1.3 تعديل class – `PUT /api/training-center/classes/{id}`

- يدعم نفس الحقول السابقة، بما فيها:
  - `exam_score` (اختياري)
  - `success_grade` (اختياري، بنفس الـ validation).
- في الـ update يتم تمرير:
  - `success_grade` ضمن `updateData`.

#### 1.4 قراءة قائمة الـ classes – `GET /api/training-center/classes`

- في كل عنصر `class` في الـ response:
  - `exam_score` يتم إرجاعه كـ **int** (تقريب من الـ decimal).
  - `success_grade` أيضًا يتم إرجاعه كـ **int** (تقريب).

#### 1.5 قراءة تفاصيل class – `GET /api/training-center/classes/{id}`

الآن الـ response فيه:

- على مستوى الـ class:
  - `exam_score`: رقم (int تقريبًا).
  - `success_grade`: رقم (int تقريبًا).
- داخل `class.trainees[]` لكل طالب:

```json
{
  "id": 10,
  "first_name": "Ahmed",
  "last_name": "Ali",
  "full_name": "Ahmed Ali",
  "email": "ahmed@example.com",
  "phone": "0123...",
  "id_number": "123456",
  "status": "completed",          // من pivot trainee_training_class
  "exam_score": 75,               // من pivot.exam_score
  "exam_status": "success",       // success | fail | null
  "enrolled_at": "...",
  "completed_at": "...",
  "certificate": {
    "id": 123,
    "certificate_number": "CERT-2026-XXXX",
    "verification_code": "VERIFY-XXXX",
    "certificate_pdf_url": "https://...",
    "card_pdf_url": "https://... أو null",
    "status": "valid",
    "issue_date": "2026-03-16",
    "expiry_date": null
  }
}
```

- **`exam_status`**:
  - `success` لو `exam_score >= success_grade`.
  - `fail` لو أقل.
  - `null` لو مفيش درجة.
- **`certificate`**:
  - لو في شهادة مرتبطة بنفس `training_class_id` وبـ `trainee_name` = `full_name` يتم إرجاع بياناتها.
  - لو مفيش شهادة → `certificate: null`.

---

### 2. درجات الطلبة على مستوى الـ pivot (trainee_training_class)

#### 2.1 حقل جديد: `exam_score` في `trainee_training_class`

- جدول `trainee_training_class` أصبح يحتوي على:
  - `status` (`enrolled`, `completed`, `dropped`, `failed`).
  - `exam_score` (decimal، nullable).
  - `enrolled_at`, `completed_at`.

#### 2.2 علاقات الـ Models

**TrainingClass::trainees()**

- العلاقة الآن:

```php
belongsToMany(Trainee::class, 'trainee_training_class', 'training_class_id', 'trainee_id')
    ->withPivot('status', 'exam_score', 'enrolled_at', 'completed_at')
    ->withTimestamps();
```

**Trainee::trainingClasses()**

- نفس الشيء: `withPivot('status', 'exam_score', 'enrolled_at', 'completed_at')`.

---

### 3. حفظ درجات الطلبة يدويًا (بدون ملف)

#### 3.1 حفظ/تعديل درجات الطلبة – `POST /api/training-center/classes/{id}/grades`

**الوصف**: حفظ أو تحديث درجات الطلبة في class واحد، وتحديث حالة كل طالب (success/fail) بناءً على `success_grade`.

- **Path params**:
  - `id`: رقم الـ class.
- **Body (JSON)**:

```json
{
  "grades": [
    { "trainee_id": 10, "score": 75 },
    { "trainee_id": 11, "score": 55 },
    { "trainee_id": 12, "score": 90 }
  ]
}
```

- **Rules / Behavior**:
  - لازم يكون الـ class عنده:
    - `exam_score` و `success_grade` مش null.
  - يتم تجاهل أي `trainee_id` مش مسجل في نفس الـ class.
  - لكل عنصر:
    - يخزن `exam_score` في pivot.
    - يحسب الحالة:
      - لو `score >= success_grade`:
        - `pivot.status = completed` (نجاح).
        - `pivot.completed_at = now()`.
      - غير ذلك:
        - `pivot.status = failed`.
        - `pivot.completed_at = null`.

- **Response مثال**:

```json
{
  "message": "Grades saved successfully",
  "class": {
    // نفس شكل GET /training-center/classes/{id} مع trainees محدثين
  }
}
```

---

### 4. استيراد/تصدير درجات الطلبة عن طريق ملف (Excel/CSV)

#### 4.1 تصدير Template الدرجات – `GET /api/training-center/classes/{id}/grades/export`

**الوصف**: تنزيل ملف CSV (متوافق مع Excel) فيه كل الطلبة في الـ class مع عمود `exam_score` لتعبئته وعمود `certificate_pdf_url` (يُملأ تلقائياً للطلبة الذين لديهم شهادة).

- **Path params**:
  - `id`: رقم الـ class.
- **Response**:
  - Content-Type: `text/csv`
  - Filename: `class_{id}_grades.csv`
  - الأعمدة في أول صف (header):

```text
trainee_id,first_name,last_name,email,id_number,exam_score
```

- كل صف بعد كده يمثل طالب واحد.
  - `exam_score`:
    - لو فيه قيمة → الدرجة الحالية.
    - لو فاضي → مفيش درجة، يمكن للمستخدم يحطها.

**Front-end usage**:
- زرار "Download Grades Template" يستدعي هذا الـ endpoint وينزّل الملف.

#### 4.2 استيراد الدرجات من ملف – `POST /api/training-center/classes/{id}/grades/import`

**الوصف**: رفع ملف CSV (مثاليًا يكون هو نفسه اللي نزلناه من الـ export) لتحديث درجات الطلبة دفعة واحدة.

- **Path params**:
  - `id`: رقم الـ class.
- **Request (multipart/form-data)**:
  - حقل واحد:

```text
file: (الـ CSV file)
```

- **Validation**:
  - `file`: `required|file|mimes:csv,txt`
  - لازم يكون في ملف header يحتوي على:
    - `trainee_id`
    - `exam_score`

- **سلوك القراءة**:
  - يقرأ الصفوف سطر بسطر.
  - يتجاهل الصف لو:
    - trainee_id فاضي.
    - trainee_id مش رقم.
    - trainee_id لا ينتمي لهذا الـ class.
    - exam_score فاضية.
    - exam_score ليست رقمًا.
    - exam_score خارج المدى 0–100.
  - الصفوف الصحيحة يتم تحويلها إلى نفس الشكل المستعمل في `saveGrades`:
    - `{ trainee_id, score }`.
  - ثم يتم تطبيق نفس منطق التحديث:
    - تحديث `pivot.exam_score`.
    - حساب `pivot.status` (`completed` / `failed`) حسب `success_grade`.

- **Response**:

```json
{
  "message": "Grades imported successfully",
  "updated_count": 10,  // عدد الصفوف اللي تم استخدامها فعليًا
  "skipped_count": 2    // عدد الصفوف اللي اتعمل لها skip لأي سبب
}
```

**Front-end usage**:

- زرار "Upload Grades File":
  - يفتح File Picker.
  - يرسل الملف في حقل `file` إلى:
    - `POST /api/training-center/classes/{id}/grades/import`.

---

### 5. توليد الشهادات لكل الطلاب الناجحين في كلاس واحد

#### 5.1 توليد شهادات Bulk لكلاس – `POST /api/training-center/classes/{id}/certificates/generate`

**المكان**: `TrainingCenter\CertificateController@generateForClass`

**الوصف**: توليد شهادات لكل الطلبة في class معيّن، بشرط:

- حالة الـ class = `completed`.
- عنده `exam_score` و `success_grade`.
- الطالب:
  - له `exam_score` في pivot.
  - و `exam_score >= success_grade`.
- يوجد purchase code متاح للـ ACC/course.
- لا يوجد شهادة سابقة لنفس الـ trainee و الـ course (يتم عمل skip في هذه الحالة).

**Path params**:

- `id`: رقم الـ class.

**Body (اختياري)**:

```json
{
  "trainee_ids": [10, 12],      // اختياري، لو مش مبعوت → يستخدم كل الناجحين
  "issue_date": "2026-03-16",   // اختياري، الافتراضي = اليوم
  "expiry_date": null           // اختياري
}
```

**Behavior**:

- يجيب الـ class مع:
  - `course.acc`
  - `trainees`.
- يفلتر الطلبة:
  - لو `trainee_ids` مبعوتة → يشتغل على subset منهم فقط.
  - بعدها يفلتر على:
    - `pivot.exam_score >= success_grade`.
- لكل طالب passing:
  1. يتأكد مفيش شهادة موجودة لنفس:
     - `course_id`
     - `training_center_id`
     - `trainee_name` (full name).
  2. يسحب purchase code:
     - أولاً للكورس نفسه (course-specific).
     - لو مش موجود → code عام لنفس الـ ACC.
  3. يولد `certificate_number` و `verification_code`.
  4. يبني `certificateData` (نفس هيكل API الواحد).
  5. ينادي `CertificateGenerationService->generate(...)`:
     - لو فشل → rollback للكود + زيادة `skipped_count`.
     - لو نجح:
       - يحفظ الـ certificate مع:
         - `training_class_id = class.id`.
         - روابط الـ PDF / card (لو موجودة).

**Response**:

```json
{
  "message": "Certificates generation completed",
  "generated_count": 10,
  "skipped_count": 2,
  "details": [
    {
      "trainee_id": 10,
      "name": "Ahmed Ali",
      "certificate_id": 123
    },
    {
      "trainee_id": 11,
      "name": "Mohamed Hassan",
      "reason": "existing_certificate",
      "certificate_id": 120
    },
    {
      "trainee_id": 12,
      "name": "Sara Ibrahim",
      "reason": "no_available_purchase_code"
    }
  ]
}
```

**Front-end usage**:

- زرار "Generate Certificates" في شاشة تفاصيل الـ class:
  - ينادي:
    - `POST /api/training-center/classes/{id}/certificates/generate`
  - ممكن تبعت `trainee_ids` لو عايز توليد لبعض الطلبة بس (اختياري).
  - بعد النجاح:
    - إعادة استدعاء `GET /training-center/classes/{id}` عشان تظهر أعمدة `certificate` والـ download buttons بجوار كل طالب ناجح.

---

### 6. ملاحظات مهمة للـ Frontend

- كل الـ APIs دي محمية بـ `auth:sanctum` + دور `training_center_admin`.
- يفضل التعامل مع الدرجات بهذه الخطوات:
  1. عند فتح شاشة class:
     - استدعي `GET /training-center/classes/{id}`.
  2. لعرض/تعديل الدرجات يدويًا:
     - استخدم `POST /training-center/classes/{id}/grades`.
  3. لو مركز التدريب عايز يشتغل عن طريق Excel:
     - `GET /training-center/classes/{id}/grades/export` لتنزيل الملف.
     - يعدل الأعمدة `exam_score`.
     - يرفع الملف لـ `POST /training-center/classes/{id}/grades/import`.
  4. بعد تأكيد الدرجات:
     - زر "Generate Certificates" → `POST /training-center/classes/{id}/certificates/generate`.
     - ثم refresh للـ class details.

