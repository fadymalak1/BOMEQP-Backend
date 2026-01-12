# دليل نظام الشهادات الذكي للمطورين - Frontend Guide

## نظرة عامة

تم إعادة بناء نظام توليد شهادات PDF بشكل كامل لضمان ظهور كل المحتوى داخل PDF بدون أي قطع، مع دعم كامل للصور والخلفيات. هذا الدليل يشرح للمطورين في Frontend كيفية التعامل مع النظام الجديد.

---

## الأبعاد المطلوبة للشهادات

### Landscape (أفقي)
- **العرض**: 297mm
- **الارتفاع**: 210mm
- **النوع**: A4 Landscape

### Portrait (عمودي)
- **العرض**: 210mm
- **الارتفاع**: 297mm
- **النوع**: A4 Portrait

### ملاحظات مهمة
- يجب استخدام هذه الأبعاد بالضبط في Preview
- الـ Preview يجب أن يطابق PDF النهائي تماماً
- لا تستخدم نسب مئوية (percentages) للأبعاد الرئيسية

---

## التحسينات الجديدة

### 1. منع قطع المحتوى
- تم تحسين CSS لضمان عدم تجاوز أي محتوى حدود PDF
- جميع العناصر تستخدم `box-sizing: border-box`
- تم إضافة `overflow: hidden` لمنع تجاوز الحدود
- تم ضبط المسافات والهوامش لضمان ظهور كل المحتوى

### 2. معالجة الصور
- **الصور الخلفية**: يتم تحميلها بشكل صحيح وتظهر كاملة داخل PDF
- **Logos و Signatures**: يتم ضبط حجمها تلقائياً لتناسب الصفحة
- **الصور المضمنة**: يتم معالجتها لضمان عدم تجاوز الحدود

### 3. معالجة النصوص الطويلة
- النصوص الطويلة يتم تقسيمها تلقائياً (`word-wrap`)
- الخطوط تتكيف مع المساحة المتاحة
- لا يوجد قطع للنصوص

### 4. جودة عالية
- DPI عالي (150) لجودة أفضل للصور
- خطوط واضحة ومقروءة
- ألوان دقيقة

---

## API Endpoints

### 1. إنشاء Template جديد
**POST** `/acc/certificate-templates`

**المتطلبات:**
- `category_id`: معرف الفئة
- `name`: اسم الـ Template
- `status`: الحالة (active/inactive)
- `template_config`: كائن JSON يحتوي على إعدادات التصميم (مطلوب أو `template_html`)

**رفع الصور:**
- يمكن رفع صورة خلفية عبر `background_image` (multipart/form-data)
- أو استخدام `background_image_url` إذا كانت الصورة موجودة مسبقاً

**ملاحظات:**
- إذا تم إرسال `template_config`، سيتم توليد HTML تلقائياً
- إذا تم إرسال `template_html` فقط، سيتم استخدامه كما هو
- يجب إرسال واحد على الأقل: `template_config` أو `template_html`

### 2. تحديث Template
**PUT** `/acc/certificate-templates/{id}`

**المتطلبات:**
- نفس متطلبات الإنشاء (كلها اختيارية)
- يمكن تحديث أي حقل من حقول Template

**رفع الصور:**
- إذا تم رفع صورة جديدة، سيتم حذف الصورة القديمة تلقائياً
- يمكن تحديث `background_image_url` بدون رفع ملف جديد

### 3. معاينة Template
**POST** `/acc/certificate-templates/{id}/preview`

**المتطلبات:**
- `sample_data`: كائن يحتوي على بيانات تجريبية

**البيانات التجريبية المطلوبة:**
- `trainee_name`: اسم المتدرب
- `course_name`: اسم الدورة
- `certificate_number`: رقم الشهادة
- `issue_date`: تاريخ الإصدار
- `verification_code`: كود التحقق
- (وغيرها من المتغيرات حسب الحاجة)

**الاستجابة:**
- `html`: HTML كامل جاهز للعرض في iframe
- هذا HTML يطابق PDF النهائي تماماً

### 4. توليد شهادة
**POST** `/training-center/certificates/generate`

**المتطلبات:**
- `training_class_id`: معرف الفصل
- `code_id`: معرف الكود
- `trainee_name`: اسم المتدرب
- `trainee_id_number`: رقم هوية المتدرب
- `issue_date`: تاريخ الإصدار
- `template_id`: (اختياري) معرف Template محدد

**ملاحظات:**
- إذا لم يتم إرسال `template_id`، سيتم اختيار Template تلقائياً حسب ACC و Category
- إذا تم إرسال `template_id`، سيتم استخدامه مباشرة (يجب أن يكون active)

---

## هيكل template_config

### Layout (التخطيط)
```json
{
  "layout": {
    "orientation": "landscape" | "portrait",
    "border_color": "#D4AF37",
    "border_width": "10px",
    "background_color": "#ffffff"
  }
}
```

### Title (العنوان)
```json
{
  "title": {
    "show": true,
    "text": "Certificate of Completion",
    "position": "top-center",
    "font_size": "32pt",
    "font_weight": "bold",
    "color": "#2c3e50",
    "text_align": "center"
  }
}
```

### Trainee Name (اسم المتدرب)
```json
{
  "trainee_name": {
    "show": true,
    "position": "center",
    "font_size": "26pt",
    "font_weight": "bold",
    "color": "#2c3e50",
    "text_align": "center"
  }
}
```

### Course Name (اسم الدورة)
```json
{
  "course_name": {
    "show": true,
    "position": "center",
    "font_size": "18pt",
    "color": "#34495e",
    "text_align": "center"
  }
}
```

### Subtitle Before (نص قبل اسم المتدرب)
```json
{
  "subtitle_before": {
    "show": true,
    "text": "This is to certify that",
    "position": "center",
    "font_size": "14pt",
    "color": "#7f8c8d",
    "text_align": "center"
  }
}
```

### Subtitle After (نص بعد اسم المتدرب)
```json
{
  "subtitle_after": {
    "show": true,
    "text": "has successfully completed the course",
    "position": "center",
    "font_size": "14pt",
    "color": "#7f8c8d",
    "text_align": "center"
  }
}
```

### Certificate Number (رقم الشهادة)
```json
{
  "certificate_number": {
    "show": true,
    "position": "bottom-left",
    "text_align": "left"
  }
}
```

### Issue Date (تاريخ الإصدار)
```json
{
  "issue_date": {
    "show": true,
    "position": "bottom-center",
    "text_align": "center"
  }
}
```

### Verification Code (كود التحقق)
```json
{
  "verification_code": {
    "show": true,
    "position": "bottom-right",
    "text_align": "right"
  }
}
```

---

## قيم text_align المدعومة

النظام يدعم جميع قيم CSS `text-align`:
- `left`
- `right`
- `center`
- `justify`
- `start`
- `end`
- `initial`
- `inherit`

**دعم إضافي للتوافق مع الإصدارات القديمة:**
- `right-center` → يتم تحويلها إلى `right`
- `left-center` → يتم تحويلها إلى `left`

---

## معالجة الصور

### رفع صورة خلفية
1. استخدم `multipart/form-data` عند إرسال الطلب
2. أرفع الملف في `background_image`
3. الحد الأقصى لحجم الملف: 5MB
4. الصيغ المدعومة: JPEG, PNG, JPG, GIF

### استخدام URL موجود
1. استخدم `background_image_url` مع URL الصورة
2. يمكن أن يكون URL نسبي أو مطلق
3. سيتم تحويله تلقائياً إلى URL مطلق

### ملاحظات مهمة
- عند تحديث Template برفع صورة جديدة، سيتم حذف الصورة القديمة تلقائياً
- الصور يتم حفظها في `storage/certificate-templates/backgrounds/`
- الصور الخلفية يتم ضبطها تلقائياً لتملأ الصفحة (`background-size: cover`)

---

## Preview في Frontend

### كيفية عرض Preview
1. استدعي API `/acc/certificate-templates/{id}/preview` مع `sample_data`
2. احصل على HTML من الاستجابة
3. اعرض HTML في iframe بنفس أبعاد PDF:
   - Landscape: `width: 297mm; height: 210mm`
   - Portrait: `width: 210mm; height: 297mm`

### مثال على iframe
```html
<!-- Landscape -->
<iframe 
  style="width: 297mm; height: 210mm; border: none;"
  srcdoc="[HTML من API]"
></iframe>

<!-- Portrait -->
<iframe 
  style="width: 210mm; height: 297mm; border: none;"
  srcdoc="[HTML من API]"
></iframe>
```

### ملاحظات مهمة
- Preview يطابق PDF النهائي تماماً
- استخدم نفس أبعاد PDF في iframe
- لا تستخدم نسب مئوية للأبعاد الرئيسية
- يمكن استخدام CSS transform للتصغير إذا لزم الأمر

---

## المتغيرات المتاحة

عند توليد شهادة، يمكن استخدام المتغيرات التالية:

- `{{trainee_name}}` - اسم المتدرب
- `{{trainee_id_number}}` - رقم هوية المتدرب
- `{{course_name}}` - اسم الدورة
- `{{course_code}}` - كود الدورة
- `{{certificate_number}}` - رقم الشهادة
- `{{verification_code}}` - كود التحقق
- `{{issue_date}}` - تاريخ الإصدار (YYYY-MM-DD)
- `{{issue_date_formatted}}` - تاريخ الإصدار منسق (January 12, 2026)
- `{{expiry_date}}` - تاريخ الانتهاء (YYYY-MM-DD)
- `{{expiry_date_formatted}}` - تاريخ الانتهاء منسق
- `{{training_center_name}}` - اسم مركز التدريب
- `{{instructor_name}}` - اسم المدرب
- `{{class_name}}` - اسم الفصل
- `{{acc_name}}` - اسم هيئة الاعتماد

---

## أفضل الممارسات

### 1. إنشاء Template
- استخدم `template_config` بدلاً من `template_html` (أسهل وأكثر مرونة)
- تأكد من إرسال جميع الحقول المطلوبة في `template_config`
- استخدم أحجام خطوط مناسبة (32pt للعنوان، 26pt لاسم المتدرب، إلخ)

### 2. معالجة الأخطاء
- تحقق من وجود Template قبل توليد الشهادة
- تأكد من أن Template في حالة `active`
- تحقق من صحة البيانات قبل الإرسال

### 3. Preview
- استخدم بيانات تجريبية واقعية في Preview
- تأكد من أن Preview يطابق PDF النهائي
- اختبر Preview مع نصوص طويلة وقصيرة

### 4. توليد الشهادات
- استخدم `template_id` لتحديد Template محدد
- تأكد من أن Template ينتمي إلى ACC الصحيح
- تحقق من وجود جميع البيانات المطلوبة

---

## الأخطاء الشائعة وكيفية تجنبها

### 1. قطع المحتوى في PDF
**السبب**: استخدام أبعاد خاطئة أو CSS غير مناسب
**الحل**: استخدم الأبعاد المحددة (297mm × 210mm للـ landscape)

### 2. الصور لا تظهر
**السبب**: URL غير صحيح أو صورة غير موجودة
**الحل**: تأكد من أن URL صحيح وأن الصورة موجودة

### 3. Preview لا يطابق PDF
**السبب**: استخدام أبعاد مختلفة في Preview
**الحل**: استخدم نفس الأبعاد في iframe كما في PDF

### 4. النصوص طويلة جداً
**السبب**: استخدام أحجام خطوط كبيرة جداً
**الحل**: استخدم أحجام خطوط مناسبة (32pt للعنوان، 26pt لاسم المتدرب)

---

## الاختبار

### قبل الإطلاق
1. اختبر إنشاء Template جديد
2. اختبر تحديث Template موجود
3. اختبر Preview مع بيانات مختلفة
4. اختبر توليد شهادة فعلية
5. تأكد من أن PDF يظهر كاملاً بدون قطع
6. تأكد من ظهور الصور بشكل صحيح
7. اختبر مع نصوص طويلة وقصيرة

### حالات الاختبار
- Template بدون صورة خلفية
- Template مع صورة خلفية
- Template مع نصوص طويلة
- Template مع نصوص قصيرة
- Landscape و Portrait
- جميع قيم text_align

---

## الدعم الفني

إذا واجهت أي مشاكل:
1. تحقق من الأبعاد المستخدمة
2. تحقق من صحة البيانات المرسلة
3. تحقق من حالة Template (active/inactive)
4. تحقق من وجود جميع الحقول المطلوبة
5. راجع سجلات الأخطاء في Backend

---

## ملخص التغييرات

### ما تم تحسينه
- ✅ منع قطع المحتوى في PDF
- ✅ معالجة أفضل للصور
- ✅ دعم كامل للصور الخلفية
- ✅ معالجة النصوص الطويلة
- ✅ Preview يطابق PDF تماماً
- ✅ جودة عالية للصور
- ✅ كود منظم وسهل الصيانة

### ما يجب على Frontend فعله
- ✅ استخدام الأبعاد المحددة في Preview
- ✅ إرسال `template_config` بشكل صحيح
- ✅ معالجة رفع الصور بشكل صحيح
- ✅ استخدام بيانات تجريبية واقعية في Preview
- ✅ التحقق من صحة البيانات قبل الإرسال

---

**تاريخ الإنشاء**: 2026-01-12  
**آخر تحديث**: 2026-01-12  
**الإصدار**: 2.0

