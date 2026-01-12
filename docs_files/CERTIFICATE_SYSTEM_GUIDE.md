# Certificate System - Complete Guide

## Overview
نظام الشهادات في المنصة يسمح لـ Training Centers بإنشاء شهادات للمتدربين بعد إتمامهم للدورات التدريبية. النظام يعتمد على شراء Certificate Codes من ACCs أولاً، ثم استخدام هذه الكودات لإنشاء الشهادات.

---

## What is a Certificate?

الشهادة هي وثيقة رقمية تُصدر للمتدرب بعد إتمامه دورة تدريبية بنجاح. كل شهادة تحتوي على:
- رقم الشهادة الفريد
- اسم المتدرب
- اسم الدورة التدريبية
- تاريخ الإصدار
- تاريخ الانتهاء (اختياري)
- كود التحقق الفريد
- رابط PDF للشهادة
- حالة الشهادة (valid, expired, revoked)

---

## Certificate Flow - Complete Process

### Step 1: Purchase Certificate Codes
قبل إنشاء الشهادات، يجب على Training Center شراء Certificate Codes من ACC.

**Workflow:**
1. Training Center يختار ACC و Course
2. يحدد عدد الكودات المطلوبة
3. يقوم بالدفع (Credit Card, Bank Transfer, أو Wallet)
4. بعد الموافقة على الدفع، يتم إنشاء الكودات
5. الكودات تصبح متاحة في Inventory

**API Endpoint:**
- `POST /training-center/codes/purchase` - شراء كودات شهادات
- `GET /training-center/codes/inventory` - عرض الكودات المتاحة

**Important Notes:**
- يجب أن يكون Training Center لديه authorization من ACC
- يمكن استخدام Discount Codes للحصول على خصم
- الكودات مرتبطة بـ ACC و Course محددين
- كل كود له status: `available`, `used`, `expired`, `revoked`

---

### Step 2: Complete Training Class
قبل إنشاء الشهادة، يجب أن يكون الصف التدريبي (Training Class) مكتملاً.

**Requirements:**
- الصف التدريبي يجب أن يكون في حالة `completed`
- يجب أن يكون هناك Class Completion record للصف

**API Endpoint:**
- `PUT /training-center/classes/{id}/complete` - تحديد الصف كمكتمل

**Important Notes:**
- لا يمكن إنشاء شهادة لصف لم يكتمل بعد
- بعد إتمام الصف، يتم إنشاء Class Completion record تلقائياً

---

### Step 3: Generate Certificate
بعد إتمام الصف ووجود كود متاح، يمكن إنشاء الشهادة.

**API Endpoint:**
`POST /training-center/certificates/generate`

**Required Data:**
- `training_class_id`: معرف الصف التدريبي المكتمل
- `code_id`: معرف كود الشهادة من Inventory
- `trainee_name`: اسم المتدرب

**Optional Data:**
- `trainee_id_number`: رقم هوية المتدرب
- `issue_date`: تاريخ الإصدار (افتراضي: اليوم)
- `expiry_date`: تاريخ الانتهاء (اختياري)

**What Happens:**
1. النظام يتحقق من أن الصف مكتمل
2. يتحقق من أن الكود متاح وغير مستخدم
3. يبحث عن Certificate Template المناسب (بناءً على ACC و Category)
4. ينشئ الشهادة مع:
   - رقم شهادة فريد
   - كود تحقق فريد
   - رابط PDF (سيتم إنشاؤه لاحقاً)
5. يحدث حالة الكود إلى `used`
6. يحدث Class Completion count
7. يرسل إشعارات إلى:
   - ACC Admin
   - Instructor
   - Group Admin

**Response:**
- رسالة نجاح
- بيانات الشهادة الكاملة

---

### Step 4: View Certificate
بعد إنشاء الشهادة، يمكن عرضها.

**API Endpoints:**
- `GET /training-center/certificates` - قائمة جميع الشهادات (مع pagination و filtering)
- `GET /training-center/certificates/{id}` - تفاصيل شهادة محددة

**Filtering Options:**
- `status`: تصفية حسب الحالة (valid, expired, revoked)
- `course_id`: تصفية حسب الدورة
- `per_page`: عدد النتائج في الصفحة
- `page`: رقم الصفحة

**Response Includes:**
- معلومات الشهادة الكاملة
- معلومات الدورة
- معلومات المدرب
- معلومات Template
- معلومات Training Center

---

### Step 5: Verify Certificate (Public)
يمكن لأي شخص التحقق من صحة الشهادة باستخدام كود التحقق.

**API Endpoint:**
`GET /certificates/verify/{code}`

**Important:**
- هذا endpoint عام (لا يحتاج authentication)
- يمكن استخدامه من أي مكان (موقع عام، تطبيق، إلخ)

**What It Returns:**
- معلومات الشهادة الأساسية
- حالة الشهادة (valid, expired, revoked)
- رسالة خطأ إذا كانت الشهادة ملغاة أو منتهية

**Use Cases:**
- التحقق من صحة الشهادة عند التوظيف
- التحقق من صحة الشهادة عند التقديم على وظيفة
- عرض الشهادة على موقع عام

---

## Certificate Templates - Complete Guide

### What is a Certificate Template?

Certificate Template هو التصميم المستخدم لإنشاء الشهادات. كل ACC يمكنه إنشاء Templates خاصة به لكل Category. Template يحتوي على:
- HTML content مع متغيرات قابلة للاستبدال
- صورة خلفية (اختياري)
- مواقع الشعارات والتوقيعات
- قائمة المتغيرات المستخدمة

### Template Variables

في Template HTML، يمكنك استخدام متغيرات يتم استبدالها ببيانات حقيقية عند إنشاء الشهادة. المتغيرات المتاحة:

**Basic Variables:**
- `{{trainee_name}}` - اسم المتدرب
- `{{trainee_id_number}}` - رقم هوية المتدرب
- `{{course_name}}` - اسم الدورة التدريبية
- `{{course_code}}` - كود الدورة
- `{{certificate_number}}` - رقم الشهادة
- `{{verification_code}}` - كود التحقق
- `{{issue_date}}` - تاريخ الإصدار
- `{{expiry_date}}` - تاريخ الانتهاء

**Additional Variables:**
- `{{training_center_name}}` - اسم مركز التدريب
- `{{instructor_name}}` - اسم المدرب
- `{{class_name}}` - اسم الصف
- `{{acc_name}}` - اسم ACC

**Date Formatting:**
- `{{issue_date_formatted}}` - تاريخ الإصدار بصيغة منسقة
- `{{expiry_date_formatted}}` - تاريخ الانتهاء بصيغة منسقة

### How to Create a Certificate Template

#### For ACC Admin

**API Endpoint:**
`POST /acc/certificate-templates`

**Required Fields:**
- `category_id`: معرف الفئة (Category) التي ينتمي إليها Template
- `name`: اسم Template
- `template_html`: محتوى HTML للـ Template مع المتغيرات
- `status`: الحالة (active أو inactive)

**Optional Fields:**
- `template_variables`: مصفوفة بأسماء المتغيرات المستخدمة (للمساعدة في التوثيق)
- `background_image_url`: رابط صورة الخلفية
- `logo_positions`: مواقع الشعارات (JSON object)
- `signature_positions`: مواقع التوقيعات (JSON object)

**Example Request:**
```json
{
  "category_id": 1,
  "name": "Fire Safety Certificate Template",
  "template_html": "<div style='text-align: center; padding: 50px;'><h1>CERTIFICATE OF COMPLETION</h1><p>This certifies that</p><h2>{{trainee_name}}</h2><p>has successfully completed</p><h3>{{course_name}}</h3><p>Issued on: {{issue_date}}</p><p>Certificate Number: {{certificate_number}}</p></div>",
  "template_variables": ["trainee_name", "course_name", "issue_date", "certificate_number"],
  "background_image_url": "/storage/templates/fire-safety-bg.jpg",
  "status": "active"
}
```

**Important Notes:**
- Template يجب أن يكون `active` ليتم استخدامه
- Template يتم اختياره تلقائياً بناءً على:
  - ACC الخاص بالكود المستخدم
  - Category الخاصة بالدورة
- يمكن إنشاء عدة Templates لنفس Category (لكن واحد فقط active في كل مرة)

---

#### For Frontend Developer

**Page: Certificate Templates Management**

##### 1. List Templates Page
**Page: Certificate Templates List**
- عرض قائمة جميع Templates الخاصة بـ ACC
- كل Template يعرض:
  - الاسم
  - الفئة (Category)
  - الحالة (active/inactive)
  - تاريخ الإنشاء
- إمكانية:
  - إنشاء Template جديد
  - تعديل Template موجود
  - حذف Template
  - Preview Template

##### 2. Create Template Page
**Page: Create Certificate Template**

**Form Fields:**
1. **Basic Information:**
   - Category (dropdown - اختيار من Categories المتاحة)
   - Template Name (text input)
   - Status (radio buttons: Active / Inactive)

2. **Template Design:**
   - HTML Editor (WYSIWYG أو Code Editor)
   - Background Image Upload (file upload)
   - Preview Area (live preview)

3. **Template Variables:**
   - قائمة المتغيرات المتاحة (dropdown أو buttons)
   - إمكانية إدراج المتغيرات في HTML بضغطة واحدة
   - عرض مثال على كيفية استخدام المتغيرات

4. **Advanced Settings (Optional):**
   - Logo Positions (JSON editor أو visual editor)
   - Signature Positions (JSON editor أو visual editor)
   - Template Variables List (array input)

**Design Recommendations:**
- استخدم HTML Editor متقدم (مثل TinyMCE, CKEditor, أو Monaco Editor)
- أضف Preview Area لعرض Template مع بيانات تجريبية
- أضف قائمة المتغيرات المتاحة مع إمكانية الإدراج السريع
- أضف validation للـ HTML للتأكد من صحة الكود
- أضف إمكانية رفع صورة خلفية مع preview

**Template HTML Example:**
```html
<!DOCTYPE html>
<html>
<head>
    <style>
        .certificate {
            width: 800px;
            height: 600px;
            border: 10px solid #gold;
            padding: 40px;
            text-align: center;
            background-image: url('{{background_image_url}}');
            background-size: cover;
        }
        .title {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .trainee-name {
            font-size: 36px;
            margin: 30px 0;
        }
        .course-name {
            font-size: 24px;
            margin: 20px 0;
        }
        .details {
            margin-top: 40px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="title">CERTIFICATE OF COMPLETION</div>
        <p>This is to certify that</p>
        <div class="trainee-name">{{trainee_name}}</div>
        <p>has successfully completed the course</p>
        <div class="course-name">{{course_name}}</div>
        <div class="details">
            <p>Issued on: {{issue_date}}</p>
            <p>Certificate Number: {{certificate_number}}</p>
            <p>Verification Code: {{verification_code}}</p>
        </div>
    </div>
</body>
</html>
```

##### 3. Edit Template Page
**Page: Edit Certificate Template**
- نفس Form في Create Page
- يتم تحميل بيانات Template الحالية
- إمكانية Preview مع بيانات تجريبية
- حفظ التعديلات

##### 4. Preview Template
**API Endpoint:**
`POST /acc/certificate-templates/{id}/preview`

**Purpose:**
معاينة Template مع بيانات تجريبية قبل استخدامه

**Request:**
```json
{
  "sample_data": {
    "trainee_name": "John Doe",
    "course_name": "Fire Safety Training",
    "issue_date": "2024-01-15",
    "certificate_number": "CERT-2024-001",
    "verification_code": "VERIFY-ABC123"
  }
}
```

**Response:**
- `preview_url`: رابط PDF Preview (سيتم إنشاؤه لاحقاً)

**Frontend Implementation:**
- زر "Preview" في صفحة Create/Edit Template
- عند الضغط، يرسل request مع بيانات تجريبية
- يعرض Preview في modal أو صفحة منفصلة

---

### Template Selection Logic

عند إنشاء شهادة، يتم اختيار Template تلقائياً بناءً على:

1. **ACC**: Template يجب أن ينتمي لنفس ACC الخاص بالكود المستخدم
2. **Category**: Template يجب أن ينتمي لنفس Category الخاصة بالدورة
3. **Status**: Template يجب أن يكون `active`

**Priority:**
- إذا كان هناك أكثر من Template active لنفس ACC و Category، يتم اختيار الأحدث (أو الأول الموجود)

**Important:**
- يجب أن يكون ACC لديه Template نشط لكل Category يريد إصدار شهادات لها
- إذا لم يوجد Template مناسب، سيتم إرجاع خطأ عند محاولة إنشاء الشهادة

---

### Template Best Practices

#### HTML Design Tips
1. **Use Standard Sizes**: استخدم أحجام قياسية للشهادات (مثل A4: 210mm x 297mm)
2. **Responsive Design**: تأكد من أن Template يعمل على أحجام مختلفة
3. **Print-Friendly**: استخدم ألوان وأحجام مناسبة للطباعة
4. **Professional Look**: استخدم تصميم احترافي ومناسب للشهادات

#### Variable Usage
1. **Always Use Variables**: استخدم المتغيرات بدلاً من النصوص الثابتة
2. **Test Variables**: اختبر Template مع بيانات مختلفة
3. **Handle Missing Data**: تأكد من أن Template يعمل حتى لو كانت بعض المتغيرات فارغة

#### Performance
1. **Optimize Images**: استخدم صور محسّنة للخلفية
2. **Minimize HTML**: استخدم HTML نظيف ومحسّن
3. **Cache Templates**: قم بتخزين Templates مؤقتاً لتحسين الأداء

---

## Certificate Components

### 1. Certificate Code
كود الشهادة هو رمز فريد يتم شراؤه من ACC لاستخدامه في إنشاء شهادة واحدة.

**Properties:**
- `code`: الكود الفريد
- `status`: الحالة (available, used, expired, revoked)
- `acc_id`: ACC الذي يبيع الكود
- `course_id`: الدورة المرتبطة
- `training_center_id`: Training Center الذي اشترى الكود
- `purchased_price`: السعر الذي تم شراؤه به
- `used_at`: تاريخ الاستخدام (إذا تم استخدامه)
- `used_for_certificate_id`: معرف الشهادة التي استخدم فيها

**Lifecycle:**
1. يتم شراؤه من ACC
2. يصبح `available` في Inventory
3. عند استخدامه لإنشاء شهادة، يصبح `used`
4. لا يمكن استخدامه مرة أخرى

---

### 2. Certificate Template
Template الشهادة هو التصميم المستخدم لإنشاء الشهادة.

**Properties:**
- `name`: اسم Template
- `template_html`: HTML للتصميم
- `template_variables`: المتغيرات المستخدمة في Template
- `background_image_url`: صورة الخلفية
- `logo_positions`: مواقع الشعارات
- `signature_positions`: مواقع التوقيعات
- `acc_id`: ACC المالك للـ Template
- `category_id`: الفئة المرتبطة

**How It Works:**
- كل ACC يمكنه إنشاء Templates خاصة به
- Template يتم اختياره تلقائياً بناءً على:
  - ACC الخاص بالكود
  - Category الخاصة بالدورة
- Template يجب أن يكون `active` ليتم استخدامه

**Template Variables:**
في Template HTML، يمكنك استخدام متغيرات يتم استبدالها ببيانات حقيقية عند إنشاء الشهادة:

**Available Variables:**
- `{{trainee_name}}` - اسم المتدرب
- `{{trainee_id_number}}` - رقم هوية المتدرب
- `{{course_name}}` - اسم الدورة التدريبية
- `{{course_code}}` - كود الدورة
- `{{certificate_number}}` - رقم الشهادة
- `{{verification_code}}` - كود التحقق
- `{{issue_date}}` - تاريخ الإصدار (YYYY-MM-DD)
- `{{expiry_date}}` - تاريخ الانتهاء (YYYY-MM-DD)
- `{{training_center_name}}` - اسم مركز التدريب
- `{{instructor_name}}` - اسم المدرب
- `{{class_name}}` - اسم الصف
- `{{acc_name}}` - اسم ACC

**Example Usage in HTML:**
```html
<div>
    <h1>Certificate of Completion</h1>
    <p>This certifies that <strong>{{trainee_name}}</strong></p>
    <p>has successfully completed</p>
    <h2>{{course_name}}</h2>
    <p>Issued on: {{issue_date}}</p>
    <p>Certificate Number: {{certificate_number}}</p>
</div>
```

---

### 3. Certificate
الشهادة نفسها هي السجل النهائي الذي يحتوي على جميع المعلومات.

**Properties:**
- `certificate_number`: رقم الشهادة الفريد
- `verification_code`: كود التحقق الفريد
- `trainee_name`: اسم المتدرب
- `trainee_id_number`: رقم هوية المتدرب
- `issue_date`: تاريخ الإصدار
- `expiry_date`: تاريخ الانتهاء
- `certificate_pdf_url`: رابط PDF الشهادة
- `status`: الحالة (valid, expired, revoked)
- `code_used_id`: معرف الكود المستخدم

**Relations:**
- `course`: الدورة التدريبية
- `class`: الصف التدريبي
- `training_center`: مركز التدريب
- `instructor`: المدرب
- `template`: Template المستخدم
- `codeUsed`: الكود المستخدم

---

## What You Need to Do

### For Training Center Admin

#### 1. Purchase Certificate Codes
- اذهب إلى صفحة Certificate Codes
- اختر ACC و Course
- حدد عدد الكودات المطلوبة
- قم بالدفع
- انتظر الموافقة على الدفع (إذا كان Manual Payment)

#### 2. Complete Training Classes
- بعد انتهاء الصف التدريبي
- اذهب إلى صفحة Classes
- حدد الصف المكتمل
- اضغط على "Mark as Complete"

#### 3. Generate Certificates
- اذهب إلى صفحة Certificates
- اضغط على "Generate Certificate"
- اختر:
  - الصف التدريبي المكتمل
  - كود الشهادة من Inventory
  - اسم المتدرب
  - معلومات إضافية (اختياري)
- اضغط على "Generate"

#### 4. View Certificates
- اذهب إلى صفحة Certificates
- يمكنك تصفية الشهادات حسب:
  - الحالة
  - الدورة
- اضغط على شهادة لعرض التفاصيل

#### 5. Share Certificate
- بعد إنشاء الشهادة، يمكنك مشاركة:
  - رابط PDF (عندما يكون جاهزاً)
  - كود التحقق للتحقق من صحة الشهادة

---

### For Frontend Developer

#### 1. Certificate Codes Purchase Flow
**Page: Certificate Codes Purchase**
- عرض قائمة ACCs المتاحة
- عند اختيار ACC، عرض Courses المتاحة
- عند اختيار Course، عرض:
  - السعر لكل كود
  - إمكانية إدخال Discount Code
  - عدد الكودات المطلوبة
- زر "Purchase" يؤدي إلى:
  - إنشاء Payment Intent (إذا كان Credit Card)
  - أو عرض تفاصيل Manual Payment
- بعد الدفع الناجح، عرض رسالة نجاح

**Page: Certificate Codes Inventory**
- عرض قائمة الكودات المتاحة
- كل كود يعرض:
  - الكود نفسه
  - ACC
  - Course
  - السعر
  - الحالة (available, used, expired, revoked)
- تصفية حسب:
  - ACC
  - Course
  - الحالة
- إمكانية البحث

---

#### 2. Certificate Generation Flow
**Page: Generate Certificate**
- قائمة الصفوف المكتملة فقط
- عند اختيار صف:
  - عرض معلومات الصف
  - عرض قائمة الكودات المتاحة للـ ACC و Course الخاصين بالصف
- نموذج إدخال:
  - اسم المتدرب (مطلوب)
  - رقم هوية المتدرب (اختياري)
  - تاريخ الإصدار (افتراضي: اليوم)
  - تاريخ الانتهاء (اختياري)
- زر "Generate Certificate"
- بعد النجاح:
  - عرض رسالة نجاح
  - عرض معلومات الشهادة
  - إمكانية عرض أو تحميل الشهادة

---

#### 3. Certificates List Page
**Page: Certificates**
- قائمة جميع الشهادات مع pagination
- كل شهادة تعرض:
  - رقم الشهادة
  - اسم المتدرب
  - اسم الدورة
  - تاريخ الإصدار
  - الحالة
  - كود التحقق
- تصفية حسب:
  - الحالة (valid, expired, revoked)
  - الدورة
- بحث
- إمكانية عرض تفاصيل الشهادة

---

#### 4. Certificate Details Page
**Page: Certificate Details**
- عرض جميع معلومات الشهادة:
  - رقم الشهادة
  - اسم المتدرب
  - رقم هوية المتدرب
  - الدورة
  - الصف التدريبي
  - المدرب
  - تاريخ الإصدار
  - تاريخ الانتهاء
  - الحالة
  - كود التحقق
- زر "View PDF" (عندما يكون جاهزاً)
- زر "Copy Verification Code"
- زر "Share Certificate" (رابط التحقق)

---

#### 5. Certificate Verification Page (Public)
**Page: Verify Certificate (Public)**
- حقل إدخال كود التحقق
- زر "Verify"
- عند التحقق الناجح:
  - عرض معلومات الشهادة
  - عرض حالة الشهادة
  - رسالة تأكيد الصحة
- عند الفشل:
  - رسالة خطأ واضحة
  - سبب الفشل (غير موجود، ملغاة، منتهية)

---

#### 6. Certificate Templates Management (For ACC Admin)
**Page: Certificate Templates List**
- عرض قائمة جميع Templates الخاصة بـ ACC
- كل Template يعرض:
  - الاسم
  - الفئة (Category)
  - الحالة (active/inactive)
  - تاريخ الإنشاء
  - عدد الشهادات المُنشأة باستخدامه
- إمكانية:
  - إنشاء Template جديد
  - تعديل Template موجود
  - حذف Template
  - Preview Template
  - تفعيل/تعطيل Template

**Page: Create/Edit Certificate Template**
- **Basic Information Section:**
  - Category (dropdown - اختيار من Categories المتاحة للـ ACC)
  - Template Name (text input - مطلوب)
  - Status (radio buttons: Active / Inactive - مطلوب)

- **Template Design Section:**
  - HTML Editor (WYSIWYG أو Code Editor - مطلوب)
    - يجب دعم HTML كامل
    - إمكانية إدراج CSS
    - إمكانية إدراج المتغيرات
  - Background Image Upload (file upload - اختياري)
    - دعم الصور: JPEG, PNG
    - عرض Preview للصورة
  - Live Preview Area (يعرض Template مع بيانات تجريبية)

- **Template Variables Section:**
  - قائمة المتغيرات المتاحة:
    - `{{trainee_name}}` - اسم المتدرب
    - `{{trainee_id_number}}` - رقم هوية المتدرب
    - `{{course_name}}` - اسم الدورة
    - `{{course_code}}` - كود الدورة
    - `{{certificate_number}}` - رقم الشهادة
    - `{{verification_code}}` - كود التحقق
    - `{{issue_date}}` - تاريخ الإصدار
    - `{{expiry_date}}` - تاريخ الانتهاء
    - `{{training_center_name}}` - اسم مركز التدريب
    - `{{instructor_name}}` - اسم المدرب
    - `{{class_name}}` - اسم الصف
    - `{{acc_name}}` - اسم ACC
  - إمكانية إدراج المتغيرات في HTML بضغطة واحدة
  - Template Variables List (array input - اختياري)
    - لإدراج قائمة المتغيرات المستخدمة (للمساعدة في التوثيق)

- **Advanced Settings Section (Optional):**
  - Logo Positions (JSON editor أو visual editor)
    - مثال: `{"x": 50, "y": 100, "width": 200, "height": 100}`
  - Signature Positions (JSON editor أو visual editor)
    - مثال: `{"x": 500, "y": 500, "width": 150, "height": 50}`

- **Actions:**
  - زر "Save" - حفظ Template
  - زر "Preview" - معاينة Template مع بيانات تجريبية
  - زر "Cancel" - إلغاء

**Page: Preview Template**
- عرض Template مع بيانات تجريبية
- إمكانية تعديل البيانات التجريبية
- عرض Preview في modal أو صفحة منفصلة
- إمكانية تحميل Preview كـ PDF (عندما يكون جاهزاً)

**API Endpoints:**
- `GET /acc/certificate-templates` - قائمة Templates
- `POST /acc/certificate-templates` - إنشاء Template جديد
- `GET /acc/certificate-templates/{id}` - تفاصيل Template
- `PUT /acc/certificate-templates/{id}` - تحديث Template
- `DELETE /acc/certificate-templates/{id}` - حذف Template
- `POST /acc/certificate-templates/{id}/preview` - معاينة Template

**Template HTML Best Practices:**
1. استخدم HTML5 و CSS3
2. استخدم أحجام قياسية (A4: 210mm x 297mm أو 8.5" x 11")
3. استخدم ألوان مناسبة للطباعة
4. تأكد من أن التصميم responsive
5. استخدم fonts واضحة ومناسبة
6. أضف borders أو decorative elements
7. تأكد من أن المتغيرات واضحة ومقروءة

**Example Template HTML:**
```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', serif;
        }
        .certificate {
            width: 297mm;
            height: 210mm;
            border: 15px solid #D4AF37;
            padding: 40px;
            text-align: center;
            background: linear-gradient(to bottom, #f5f5f5, #ffffff);
            position: relative;
        }
        .title {
            font-size: 48px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .subtitle {
            font-size: 18px;
            color: #7f8c8d;
            margin-bottom: 40px;
        }
        .trainee-name {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin: 30px 0;
            text-decoration: underline;
        }
        .course-name {
            font-size: 24px;
            color: #34495e;
            margin: 20px 0;
        }
        .details {
            margin-top: 50px;
            font-size: 14px;
            color: #7f8c8d;
        }
        .verification {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 10px;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="title">Certificate of Completion</div>
        <div class="subtitle">This is to certify that</div>
        <div class="trainee-name">{{trainee_name}}</div>
        <div class="subtitle">has successfully completed the course</div>
        <div class="course-name">{{course_name}}</div>
        <div class="details">
            <p>Issued on: {{issue_date}}</p>
            <p>Certificate Number: {{certificate_number}}</p>
            <p>Training Center: {{training_center_name}}</p>
        </div>
        <div class="verification">
            Verification Code: {{verification_code}}
        </div>
    </div>
</body>
</html>
```

---

## Important Notes

### Certificate Code Status
- **available**: الكود متاح للاستخدام
- **used**: الكود تم استخدامه (لا يمكن استخدامه مرة أخرى)
- **expired**: الكود منتهي الصلاحية
- **revoked**: الكود ملغى

### Certificate Status
- **valid**: الشهادة صالحة
- **expired**: الشهادة منتهية الصلاحية
- **revoked**: الشهادة ملغاة

### Prerequisites
قبل إنشاء شهادة، يجب:
1. ✅ شراء Certificate Code من ACC
2. ✅ إتمام الصف التدريبي (Mark as Complete)
3. ✅ وجود Certificate Template نشط للـ ACC و Category

### Notifications
عند إنشاء شهادة، يتم إرسال إشعارات إلى:
- ACC Admin (صاحب الدورة)
- Instructor (مدرب الصف)
- Group Admin (للإحصائيات)

### PDF Generation
حالياً، PDF الشهادة لم يتم إنشاؤه بعد (TODO في الكود). يجب:
- إنشاء PDF من Template
- حفظه في Storage
- تحديث `certificate_pdf_url`

### Verification Code
كل شهادة لها كود تحقق فريد يمكن استخدامه للتحقق من صحة الشهادة من أي مكان بدون الحاجة لتسجيل الدخول.

---

## API Endpoints Summary

### Training Center Endpoints
- `POST /training-center/codes/purchase` - شراء كودات شهادات
- `GET /training-center/codes/inventory` - عرض الكودات المتاحة
- `POST /training-center/certificates/generate` - إنشاء شهادة
- `GET /training-center/certificates` - قائمة الشهادات
- `GET /training-center/certificates/{id}` - تفاصيل شهادة

### ACC Admin Endpoints (Certificate Templates)
- `GET /acc/certificate-templates` - قائمة Templates الخاصة بـ ACC
- `POST /acc/certificate-templates` - إنشاء Template جديد
- `GET /acc/certificate-templates/{id}` - تفاصيل Template
- `PUT /acc/certificate-templates/{id}` - تحديث Template
- `DELETE /acc/certificate-templates/{id}` - حذف Template
- `POST /acc/certificate-templates/{id}/preview` - معاينة Template مع بيانات تجريبية

### Public Endpoints
- `GET /certificates/verify/{code}` - التحقق من صحة الشهادة (لا يحتاج authentication)

---

## Common Scenarios

### Scenario 1: First Time Certificate Generation
1. Training Center يشتري 10 كودات من ACC
2. بعد إتمام صف تدريبي
3. يقوم بإنشاء شهادة للمتدرب الأول
4. يستخدم كود واحد
5. يتبقى 9 كودات في Inventory

### Scenario 2: Multiple Certificates for Same Class
1. صف تدريبي مكتمل به 5 متدربين
2. Training Center لديه 5 كودات متاحة
3. يقوم بإنشاء 5 شهادات (واحدة لكل متدرب)
4. يستخدم 5 كودات
5. لا تبقى كودات متاحة

### Scenario 3: Certificate Verification
1. متدرب يحصل على شهادة
2. يحصل على كود التحقق
3. يشارك الكود مع صاحب عمل
4. صاحب العمل يذهب إلى صفحة التحقق العامة
5. يدخل الكود
6. يرى معلومات الشهادة ويؤكد صحتها

---

## Troubleshooting

### Cannot Generate Certificate
**Problem**: لا يمكن إنشاء شهادة

**Possible Causes:**
1. الصف التدريبي لم يكتمل بعد
   - **Solution**: قم بإنهاء الصف أولاً (Mark as Complete)

2. لا توجد كودات متاحة
   - **Solution**: قم بشراء كودات من ACC

3. الكود المستخدم غير متاح
   - **Solution**: تأكد من أن الكود في حالة `available`

4. لا يوجد Template للـ ACC و Category
   - **Solution**: تأكد من أن ACC لديه Template نشط للفئة

### Certificate Code Not Available
**Problem**: الكود غير متاح في Inventory

**Possible Causes:**
1. الكود تم استخدامه بالفعل
   - **Solution**: استخدم كود آخر

2. الكود منتهي الصلاحية
   - **Solution**: قم بشراء كودات جديدة

3. الكود ملغى
   - **Solution**: قم بشراء كودات جديدة

### Certificate Verification Failed
**Problem**: التحقق من الشهادة فشل

**Possible Causes:**
1. كود التحقق غير صحيح
   - **Solution**: تأكد من إدخال الكود بشكل صحيح

2. الشهادة ملغاة
   - **Solution**: الشهادة لم تعد صالحة

3. الشهادة منتهية الصلاحية
   - **Solution**: الشهادة انتهت صلاحيتها

---

## Best Practices

### For Training Centers
1. **Plan Ahead**: قم بشراء كودات كافية قبل بدء الصفوف
2. **Complete Classes Promptly**: قم بإنهاء الصفوف فور انتهائها
3. **Generate Certificates Immediately**: أنشئ الشهادات فور إتمام الصف
4. **Keep Records**: احتفظ بسجلات لجميع الشهادات المُنشأة
5. **Share Verification Codes**: شارك أكواد التحقق مع المتدربين

### For Frontend Developers
1. **Validate Before Submit**: تحقق من جميع البيانات قبل الإرسال
2. **Show Clear Errors**: اعرض رسائل خطأ واضحة
3. **Handle Loading States**: اعرض حالات التحميل أثناء المعالجة
4. **Confirm Actions**: اطلب تأكيد قبل إنشاء الشهادة (لأن الكود سيُستخدم)
5. **Display Status Clearly**: اعرض حالة الكودات والشهادات بوضوح

---

## Future Enhancements

### Planned Features
- إنشاء PDF للشهادات تلقائياً
- إرسال الشهادات بالبريد الإلكتروني للمتدربين
- إمكانية طباعة الشهادات
- QR Code على الشهادات للتحقق السريع
- إمكانية إلغاء الشهادات
- تجديد الشهادات المنتهية

---

## Certificate Template Creation - Step by Step

### For ACC Admin (Creating Template)

#### Step 1: Access Templates Page
- اذهب إلى Certificate Templates في ACC Dashboard
- اضغط على "Create New Template"

#### Step 2: Fill Basic Information
- **Category**: اختر الفئة (Category) التي ينتمي إليها Template
  - مهم: Template سيُستخدم فقط للدورات في هذه الفئة
- **Template Name**: أدخل اسم واضح للـ Template (مثل: "Fire Safety Certificate Template")
- **Status**: اختر `active` إذا كنت تريد استخدامه فوراً

#### Step 3: Configure Template Design (New Approach - Recommended)
بدلاً من إدخال HTML كامل، يمكنك إدخال كل متغير على حدة:

**Title Section:**
- **Show Title**: حدد إذا كنت تريد عرض العنوان
- **Title Text**: أدخل نص العنوان (مثل: "Certificate of Completion")
- **Title Position**: اختر موقع العنوان (top-center, top-left, etc.)
- **Title Font Size**: حدد حجم الخط (مثل: 48px)
- **Title Font Weight**: حدد وزن الخط (bold, normal, etc.)
- **Title Color**: اختر لون النص

**Trainee Name Section:**
- **Show Trainee Name**: حدد إذا كنت تريد عرض اسم المتدرب
- **Position**: اختر موقع اسم المتدرب
- **Font Size**: حدد حجم الخط
- **Font Weight**: حدد وزن الخط
- **Color**: اختر لون النص

**Course Name Section:**
- **Show Course Name**: حدد إذا كنت تريد عرض اسم الدورة
- **Position**: اختر موقع اسم الدورة
- **Font Size**: حدد حجم الخط
- **Color**: اختر لون النص

**Other Fields:**
- **Certificate Number**: حدد إذا كنت تريد عرض رقم الشهادة وموقعه
- **Issue Date**: حدد إذا كنت تريد عرض تاريخ الإصدار وموقعه
- **Verification Code**: حدد إذا كنت تريد عرض كود التحقق وموقعه

**Layout Settings:**
- **Orientation**: اختر الاتجاه (Portrait أو Landscape)
- **Border Color**: اختر لون الإطار
- **Border Width**: حدد عرض الإطار
- **Background Color**: اختر لون الخلفية

#### Step 4: Upload Background Image (Optional)
- ارفع صورة خلفية للشهادة (JPEG, PNG, JPG, GIF - max 5MB)
- تأكد من أن الصورة مناسبة للطباعة
- استخدم أحجام قياسية (A4)
- النظام سيستخدم الصورة كخلفية للشهادة

**Note:** بعد إدخال جميع المعلومات، النظام سيقوم بإنشاء HTML تلقائياً بناءً على الإعدادات التي أدخلتها.

#### Step 6: Preview Template
- اضغط على "Preview" لمعاينة Template
- أدخل بيانات تجريبية
- تأكد من أن التصميم يبدو جيداً

#### Step 7: Save Template
- اضغط على "Save" لحفظ Template
- تأكد من أن Status = `active` إذا كنت تريد استخدامه

---

### For Frontend Developer (Implementing Template Creation)

#### Page Structure: Create Certificate Template (New Approach)

**Layout:**
```
┌─────────────────────────────────────────┐
│  Create Certificate Template             │
├─────────────────────────────────────────┤
│                                         │
│  [Basic Information Section]            │
│  - Category (dropdown)                  │
│  - Template Name (text)                 │
│  - Status (radio: Active/Inactive)      │
│                                         │
│  [Template Design Configuration]        │
│  ┌─────────────────────────────────┐   │
│  │ Title Section                    │   │
│  │ - Show Title (checkbox)          │   │
│  │ - Title Text (text input)        │   │
│  │ - Position (dropdown)            │   │
│  │ - Font Size (text input)          │   │
│  │ - Font Weight (dropdown)         │   │
│  │ - Color (color picker)            │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ Trainee Name Section              │   │
│  │ - Show (checkbox)                 │   │
│  │ - Position (dropdown)              │   │
│  │ - Font Size (text input)          │   │
│  │ - Font Weight (dropdown)          │   │
│  │ - Color (color picker)            │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ Course Name Section               │   │
│  │ - Show (checkbox)                 │   │
│  │ - Position (dropdown)             │   │
│  │ - Font Size (text input)         │   │
│  │ - Color (color picker)            │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ Other Fields                     │   │
│  │ - Certificate Number (show/pos)  │   │
│  │ - Issue Date (show/pos)          │   │
│  │ - Verification Code (show/pos)   │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ Layout Settings                   │   │
│  │ - Orientation (radio)             │   │
│  │ - Border Color (color picker)     │   │
│  │ - Border Width (text input)       │   │
│  │ - Background Color (color picker)│   │
│  └─────────────────────────────────┘   │
│                                         │
│  [Background Image Section]             │
│  - File Upload (drag & drop)            │
│  - Image Preview                        │
│  - Remove Image Button                  │
│                                         │
│  [Live Preview Section]                 │
│  - Preview Area (shows generated HTML)   │
│  - Sample Data Input                    │
│                                         │
│  [Actions]                              │
│  [Preview] [Save] [Cancel]               │
└─────────────────────────────────────────┘
```

**Form Fields Details:**

**Title Section:**
- `title.show` (boolean): عرض/إخفاء العنوان
- `title.text` (string): نص العنوان
- `title.position` (string): الموقع (top-center, top-left, top-right, center, etc.)
- `title.font_size` (string): حجم الخط (مثل: "48px")
- `title.font_weight` (string): وزن الخط (bold, normal, etc.)
- `title.color` (string): لون النص (hex code)

**Trainee Name Section:**
- `trainee_name.show` (boolean): عرض/إخفاء اسم المتدرب
- `trainee_name.position` (string): الموقع
- `trainee_name.font_size` (string): حجم الخط
- `trainee_name.font_weight` (string): وزن الخط
- `trainee_name.color` (string): لون النص

**Course Name Section:**
- `course_name.show` (boolean): عرض/إخفاء اسم الدورة
- `course_name.position` (string): الموقع
- `course_name.font_size` (string): حجم الخط
- `course_name.color` (string): لون النص

**Other Fields:**
- `certificate_number.show` (boolean): عرض/إخفاء رقم الشهادة
- `certificate_number.position` (string): الموقع
- `issue_date.show` (boolean): عرض/إخفاء تاريخ الإصدار
- `issue_date.position` (string): الموقع
- `verification_code.show` (boolean): عرض/إخفاء كود التحقق
- `verification_code.position` (string): الموقع

**Layout Settings:**
- `layout.orientation` (string): الاتجاه (portrait, landscape)
- `layout.border_color` (string): لون الإطار (hex code)
- `layout.border_width` (string): عرض الإطار (مثل: "15px")
- `layout.background_color` (string): لون الخلفية (hex code)

**Background Image:**
- File upload component (drag & drop)
- Supported formats: JPEG, PNG, JPG, GIF
- Max size: 5MB
- Image preview after upload
- Remove image button
- When uploaded, the file will be stored and URL will be returned

**Live Preview:**
- Preview Area يعرض Template المُنشأ تلقائياً
- يتم تحديث Preview تلقائياً عند تغيير أي إعداد
- يمكن إدخال بيانات تجريبية لمعاينة الشهادة
- يعرض HTML المُنشأ تلقائياً

**API Request Format:**
```json
{
  "category_id": 1,
  "name": "Fire Safety Certificate Template",
  "status": "active",
  "template_config": {
    "title": {
      "text": "Certificate of Completion",
      "show": true,
      "position": "top-center",
      "font_size": "48px",
      "font_weight": "bold",
      "color": "#2c3e50"
    },
    "trainee_name": {
      "show": true,
      "position": "center",
      "font_size": "36px",
      "font_weight": "bold",
      "color": "#2c3e50"
    },
    "course_name": {
      "show": true,
      "position": "center",
      "font_size": "24px",
      "color": "#34495e"
    },
    "certificate_number": {
      "show": true,
      "position": "bottom-left"
    },
    "issue_date": {
      "show": true,
      "position": "bottom-center"
    },
    "verification_code": {
      "show": true,
      "position": "bottom-right"
    },
    "layout": {
      "orientation": "landscape",
      "border_color": "#D4AF37",
      "border_width": "15px",
      "background_color": "#ffffff"
    }
  },
  "background_image": "[FILE UPLOAD]"
}
```

**Important Notes:**
- النظام يقوم بإنشاء HTML تلقائياً من `template_config`
- لا حاجة لإدخال HTML يدوياً
- يمكن إدخال `template_html` مباشرة إذا كنت تريد HTML مخصص (legacy support)
- عند إدخال `template_config`، `template_html` سيتم إنشاؤه تلقائياً

**Validation:**
- Category: مطلوب
- Template Name: مطلوب، max 255 characters
- Template HTML: مطلوب
- Status: مطلوب
- Template Variables: اختياري، يجب أن يكون array
- Logo/Signature Positions: اختياري، يجب أن يكون JSON valid

**API Integration:**
- `POST /acc/certificate-templates` عند حفظ Template جديد
- `PUT /acc/certificate-templates/{id}` عند تحديث Template
- `POST /acc/certificate-templates/{id}/preview` عند Preview

---

## System Verification Checklist

### For ACC Admin
- [ ] تم إنشاء Certificate Template لكل Category
- [ ] Templates في حالة `active`
- [ ] Templates تحتوي على جميع المتغيرات المطلوبة
- [ ] تم اختبار Preview للـ Templates

### For Training Center Admin
- [ ] تم شراء Certificate Codes من ACC
- [ ] الكودات متاحة في Inventory
- [ ] تم إتمام الصفوف التدريبية (Mark as Complete)
- [ ] تم إنشاء شهادات بنجاح
- [ ] تم التحقق من صحة الشهادات

### For Frontend Developer
- [ ] تم إنشاء صفحة Certificate Templates Management
- [ ] تم إنشاء صفحة Create/Edit Template
- [ ] تم إنشاء صفحة Preview Template
- [ ] تم إنشاء صفحة Certificate Generation
- [ ] تم إنشاء صفحة Certificates List
- [ ] تم إنشاء صفحة Certificate Verification (Public)
- [ ] تم اختبار جميع الـ APIs
- [ ] تم اختبار Template Variables replacement

---

## Testing the Complete Flow

### Test Scenario 1: Full Certificate Flow
1. **ACC Admin**: إنشاء Template جديد
   - اذهب إلى Certificate Templates
   - اضغط "Create Template"
   - أدخل البيانات المطلوبة
   - استخدم Template HTML مع متغيرات
   - احفظ Template

2. **Training Center**: شراء كودات
   - اذهب إلى Certificate Codes Purchase
   - اختر ACC و Course
   - اشترِ كودات

3. **Training Center**: إتمام صف
   - اذهب إلى Classes
   - حدد صف مكتمل
   - اضغط "Mark as Complete"

4. **Training Center**: إنشاء شهادة
   - اذهب إلى Certificates
   - اضغط "Generate Certificate"
   - اختر الصف المكتمل
   - اختر كود من Inventory
   - أدخل اسم المتدرب
   - اضغط "Generate"

5. **Verify**: التحقق من الشهادة
   - اذهب إلى صفحة Verification العامة
   - أدخل كود التحقق
   - تأكد من عرض معلومات الشهادة

### Test Scenario 2: Template Selection
1. **ACC Admin**: إنشاء Templateين لنفس Category
   - Template 1: status = `active`
   - Template 2: status = `inactive`

2. **Training Center**: إنشاء شهادة
   - يجب أن يتم اختيار Template 1 (active) تلقائياً

3. **ACC Admin**: تعطيل Template 1 وتفعيل Template 2
   - Template 1: status = `inactive`
   - Template 2: status = `active`

4. **Training Center**: إنشاء شهادة أخرى
   - يجب أن يتم اختيار Template 2 (active) تلقائياً

---

## Common Issues and Solutions

### Issue 1: Cannot Generate Certificate - No Template Found
**Problem**: خطأ "Certificate template not found" عند إنشاء الشهادة

**Causes:**
1. لا يوجد Template للـ ACC و Category
2. Template موجود لكن status = `inactive`
3. Template تم حذفه

**Solutions:**
1. تأكد من وجود Template للـ ACC و Category
2. تأكد من أن Template status = `active`
3. أنشئ Template جديد إذا لزم الأمر

### Issue 2: Template Variables Not Replaced
**Problem**: المتغيرات تظهر كما هي في الشهادة (مثل: `{{trainee_name}}`)

**Causes:**
1. Template HTML لا يحتوي على المتغيرات بشكل صحيح
2. النظام لم يقم باستبدال المتغيرات بعد (TODO في الكود)

**Solutions:**
1. تأكد من استخدام صيغة `{{variable_name}}` في Template
2. تأكد من أن المتغيرات موجودة في قائمة المتغيرات المتاحة
3. انتظر تحديث النظام لاستبدال المتغيرات تلقائياً

### Issue 3: Template Preview Not Working
**Problem**: Preview لا يعمل أو يعرض خطأ

**Causes:**
1. HTML Template غير صحيح
2. بيانات تجريبية غير كافية

**Solutions:**
1. تحقق من صحة HTML Template
2. تأكد من إرسال جميع البيانات المطلوبة في `sample_data`
3. تحقق من console للأخطاء

---

## System Verification - How Everything Works Together

### Template Selection Logic (Verified)

عند إنشاء شهادة، النظام يبحث عن Template بهذا الترتيب:

1. **Get ACC from Code**: يحصل على ACC من الكود المستخدم
2. **Get Category from Course**: يحصل على Category من الدورة (course → subCategory → category)
3. **Search Template**: يبحث عن Template حيث:
   - `acc_id` = ACC من الكود
   - `category_id` = Category من الدورة
   - `status` = `active`
4. **Use Template**: إذا وُجد، يتم استخدامه. إذا لم يوجد، خطأ 404

**Code Location:** `app/Http/Controllers/API/TrainingCenter/CertificateController.php` (lines 88-91)

### Certificate Generation Process (Verified)

1. ✅ **Validation**: التحقق من البيانات المرسلة
2. ✅ **Training Center Check**: التحقق من وجود Training Center
3. ✅ **Training Class Check**: التحقق من وجود الصف وأنه ينتمي لـ Training Center
4. ✅ **Class Completion Check**: التحقق من أن الصف مكتمل
5. ✅ **Code Validation**: التحقق من أن الكود متاح وينتمي لـ Training Center
6. ✅ **Template Selection**: اختيار Template تلقائياً
7. ✅ **Certificate Creation**: إنشاء الشهادة
8. ✅ **Code Update**: تحديث حالة الكود إلى `used`
9. ✅ **Completion Update**: تحديث Class Completion count
10. ✅ **Notifications**: إرسال إشعارات

**All Steps Verified**: ✅ كل الخطوات تعمل بشكل صحيح

### Template Creation Process (Verified)

1. ✅ **Validation**: التحقق من البيانات المرسلة
2. ✅ **ACC Check**: التحقق من وجود ACC
3. ✅ **Category Check**: التحقق من وجود Category
4. ✅ **Template Creation**: إنشاء Template
5. ✅ **Response**: إرجاع بيانات Template

**All Steps Verified**: ✅ كل الخطوات تعمل بشكل صحيح

### What Works

✅ **Certificate Code Purchase**: يعمل بشكل صحيح
✅ **Class Completion**: يعمل بشكل صحيح
✅ **Certificate Generation**: يعمل بشكل صحيح
✅ **Template Selection**: يعمل بشكل صحيح
✅ **Certificate Viewing**: يعمل بشكل صحيح
✅ **Certificate Verification**: يعمل بشكل صحيح
✅ **Template Creation**: يعمل بشكل صحيح
✅ **Template Update**: يعمل بشكل صحيح
✅ **Template Deletion**: يعمل بشكل صحيح
✅ **Template Preview**: يعمل (لكن PDF generation TODO)

### What Needs Implementation (TODOs)

⚠️ **PDF Generation**: حالياً، PDF الشهادة لم يتم إنشاؤه بعد
- يجب إنشاء PDF من Template HTML
- يجب استبدال المتغيرات في HTML
- يجب حفظ PDF في Storage
- يجب تحديث `certificate_pdf_url`

⚠️ **Template Variable Replacement**: حالياً، المتغيرات في Template HTML لم يتم استبدالها بعد
- يجب استبدال `{{variable_name}}` بالبيانات الحقيقية
- يجب معالجة التواريخ والتنسيق
- يجب التعامل مع المتغيرات الفارغة

⚠️ **Email Sending**: إرسال الشهادات بالبريد الإلكتروني لم يتم تنفيذه بعد

---

## Summary

نظام الشهادات يتكون من:
1. **Certificate Templates**: تصميمات الشهادات (ACC Admin)
2. **Certificate Codes**: كودات يتم شراؤها من ACC
3. **Training Classes**: صفوف تدريبية يجب إتمامها
4. **Certificates**: شهادات يتم إنشاؤها للمتدربين
5. **Verification**: نظام للتحقق من صحة الشهادات

**Complete Flow:**
1. ACC Admin: إنشاء Certificate Template
2. Training Center: شراء Certificate Codes
3. Training Center: إتمام Training Class
4. Training Center: إنشاء Certificate (يستخدم Template تلقائياً)
5. Training Center/Public: عرض/التحقق من Certificate

**Key Points:**
- يجب إنشاء Template قبل إنشاء الشهادات
- يجب شراء الكودات قبل إنشاء الشهادات
- يجب إتمام الصف قبل إنشاء الشهادة
- Template يتم اختياره تلقائياً بناءً على ACC و Category
- كل كود يُستخدم مرة واحدة فقط
- كل شهادة لها كود تحقق فريد
- يمكن التحقق من الشهادات بدون تسجيل دخول

---

## Questions or Issues

إذا كان لديك أي استفسارات أو مشاكل متعلقة بنظام الشهادات، يرجى التواصل مع فريق Backend.

