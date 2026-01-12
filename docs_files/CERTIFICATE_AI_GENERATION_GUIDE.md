# دليل توليد Templates الشهادات باستخدام AI (Gemini)

## نظرة عامة

تم إضافة ميزة جديدة تسمح لـ ACC بتحميل صورة شهادة موجودة لديه، واستخدام AI (Google Gemini) لتحليل الصورة تلقائياً وإنشاء Template كامل مع HTML و `template_config`.

---

## كيف تعمل الميزة

تم تصميم الميزة باستخدام **Pipeline ذكي من مرحلتين** لضمان أعلى مستويات الاستقرار والدقة:

### المرحلة 1: تحليل التصميم (Vision → JSON)
1. يقوم AI (Gemini Vision) بتحليل الصورة المرفوعة.
2. يستخرج AI جميع عناصر التصميم (الألوان، الخطوط، المواضع) ويحولها إلى كائن JSON صغير ومنظم يسمى `template_config`.
3. يتم التحقق من صحة JSON وتوازنه لضمان عدم وجود أخطاء برمجية.

### المرحلة 2: توليد الكود (JSON → HTML)
1. يتم إرسال `template_config` المستخرج في المرحلة الأولى مرة أخرى إلى AI.
2. يقوم AI بتوليد كود HTML كامل مع CSS مدمج بناءً على الإعدادات الدقيقة المستخرجة.
3. يتم دمج الكود مع الصورة المرفوعة (كخلفية) لإنشاء Template نهائي مطابق للصورة الأصلية.

### الفوائد التقنية لهذا التصميم:
- **استقرار عالٍ**: تقسيم المهمة يمنع انقطاع الاستجابة بسبب طول الكود.
- **دقة 100%**: معالجة JSON بشكل منفصل تضمن صحة البيانات قبل توليد HTML.
- **إعادة محاولة ذكية**: في حال فشل التحليل، يقوم النظام بإعادة المحاولة تلقائياً بتعليمات أقوى.
- **تطابق تام**: الـ Preview الناتج يطابق PDF النهائي لأن كلاهما يعتمد على نفس الـ `template_config`.

---

## API Endpoint الجديد

### POST `/acc/certificate-templates/generate-from-image`

**الوصف**: رفع صورة شهادة وتحليلها باستخدام AI لإنشاء Template تلقائياً.

**المتطلبات:**
- `category_id` (required): معرف الفئة
- `name` (required): اسم الـ Template
- `certificate_image` (required): ملف صورة الشهادة (JPEG, PNG, JPG - حد أقصى 10MB)
- `orientation` (required): اتجاه الشهادة (`landscape` أو `portrait`)
- `status` (required): حالة الـ Template (`active` أو `inactive`)

**نوع الطلب**: `multipart/form-data`

**مثال على الطلب:**
```javascript
const formData = new FormData();
formData.append('category_id', 1);
formData.append('name', 'Fire Safety Certificate Template');
formData.append('certificate_image', fileInput.files[0]);
formData.append('orientation', 'landscape');
formData.append('status', 'active');

fetch('/api/acc/certificate-templates/generate-from-image', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token
    },
    body: formData
});
```

**الاستجابة الناجحة (201):**
```json
{
    "message": "Template generated successfully from image",
    "template": {
        "id": 1,
        "name": "Fire Safety Certificate Template",
        "category_id": 1,
        "template_config": {
            "layout": {
                "orientation": "landscape",
                "border_color": "#D4AF37",
                "border_width": "10px",
                "background_color": "#ffffff"
            },
            "title": {
                "show": true,
                "text": "Certificate of Completion",
                "position": "top-center",
                "font_size": "32pt",
                "font_weight": "bold",
                "color": "#2c3e50",
                "text_align": "center"
            },
            // ... باقي العناصر
        },
        "template_html": "<!DOCTYPE html>...",
        "background_image_url": "/storage/certificate-templates/source-images/...",
        "status": "active"
    },
    "ai_analysis": {
        "source_image": "/storage/certificate-templates/source-images/...",
        "orientation": "landscape"
    }
}
```

**الأخطاء المحتملة:**

- **422 Validation Error**: بيانات غير صحيحة
- **404 ACC not found**: ACC غير موجود
- **500 AI analysis failed**: فشل تحليل الصورة بواسطة AI

---

## الإعدادات المطلوبة

### 1. Gemini API Key

يجب إضافة `GEMINI_API_KEY` في ملف `.env`:

```env
GEMINI_API_KEY=your_gemini_api_key_here
```

**كيفية الحصول على API Key:**
1. اذهب إلى [Google AI Studio](https://makersuite.google.com/app/apikey)
2. سجل الدخول بحساب Google
3. أنشئ API Key جديد
4. انسخ الـ API Key وأضفه في `.env`

### 2. Gemini API URL (اختياري)

يمكن تخصيص URL الـ API إذا لزم الأمر:

```env
GEMINI_API_URL=https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent
```

**ملاحظة**: القيمة الافتراضية تعمل بشكل جيد، لا حاجة لتغييرها إلا في حالات خاصة.

---

## ما الذي يحلله AI؟

### 1. التخطيط (Layout)
- اتجاه الشهادة (Landscape/Portrait)
- لون الحدود (Border Color)
- عرض الحدود (Border Width)
- لون الخلفية (Background Color)
- وجود صورة خلفية

### 2. العناصر النصية
- **العنوان (Title)**: النص، الحجم، اللون، الموضع
- **اسم المتدرب (Trainee Name)**: الحجم، اللون، الموضع
- **اسم الدورة (Course Name)**: الحجم، اللون، الموضع
- **النصوص الفرعية (Subtitles)**: النصوص قبل وبعد اسم المتدرب
- **رقم الشهادة (Certificate Number)**: الموضع والمحاذاة
- **تاريخ الإصدار (Issue Date)**: الموضع والمحاذاة
- **كود التحقق (Verification Code)**: الموضع والمحاذاة

### 3. التصميم
- أحجام الخطوط (Font Sizes)
- ألوان النصوص (Text Colors)
- أوزان الخطوط (Font Weights)
- محاذاة النصوص (Text Alignment)
- المسافات والهوامش (Spacing & Margins)

---

## استخدام Template المُنشأ

بعد إنشاء Template باستخدام AI:

### 1. مراجعة Template
- راجع `template_config` للتأكد من دقة التحليل
- راجع `template_html` للتأكد من التصميم
- استخدم Preview لمعاينة Template

### 2. تعديل Template (اختياري)
- يمكن تعديل أي عنصر في `template_config`
- يمكن تعديل `template_html` مباشرة
- يمكن تحديث الصورة الخلفية

### 3. استخدام Template
- Training Center يستخدم Template عند توليد شهادة
- يتم استبدال المتغيرات تلقائياً:
  - `{{trainee_name}}` → اسم المتدرب
  - `{{course_name}}` → اسم الدورة
  - `{{certificate_number}}` → رقم الشهادة
  - `{{issue_date}}` → تاريخ الإصدار
  - `{{verification_code}}` → كود التحقق

---

## أفضل الممارسات

### 1. جودة الصورة
- استخدم صور عالية الجودة (300 DPI أو أعلى)
- تأكد من وضوح النصوص في الصورة
- تجنب الصور المشوشة أو المظلمة

### 2. اتجاه الشهادة
- حدد الاتجاه الصحيح (`landscape` أو `portrait`)
- تأكد من أن الصورة المرفوعة تطابق الاتجاه المحدد

### 3. مراجعة النتائج
- دائماً راجع Template المُنشأ قبل تفعيله
- استخدم Preview للتأكد من المطابقة
- قم بتعديل أي أخطاء في التحليل

### 4. التعديلات اليدوية
- يمكن تعديل `template_config` بعد الإنشاء
- يمكن تعديل `template_html` إذا لزم الأمر
- يمكن إضافة أو إزالة عناصر

---

## الأخطاء الشائعة وحلولها

### 1. "Failed to analyze certificate image"
**السبب**: مشكلة في Gemini API أو الصورة غير صالحة
**الحل**: 
- تحقق من صحة `GEMINI_API_KEY`
- تأكد من أن الصورة بصيغة مدعومة (JPEG, PNG, JPG)
- تأكد من أن حجم الصورة أقل من 10MB

### 2. "Invalid JSON response from Gemini"
**السبب**: Gemini لم يعد JSON صالح
**الحل**:
- جرب رفع صورة أخرى
- تأكد من وضوح الصورة وجودتها
- راجع سجلات الأخطاء للتفاصيل

### 3. "Template config missing required fields"
**السبب**: Gemini لم يستخرج جميع الحقول المطلوبة
**الحل**:
- راجع الصورة وتأكد من وضوحها
- جرب رفع صورة أخرى
- يمكن إضافة الحقول المفقودة يدوياً بعد الإنشاء

---

## مقارنة الطريقتين

### الطريقة القديمة (Manual)
- ACC يدخل `template_config` يدوياً
- ACC يكتب HTML يدوياً
- يستغرق وقتاً أطول
- يحتاج معرفة تقنية

### الطريقة الجديدة (AI)
- ACC يرفع صورة فقط
- AI يحلل ويولد Template تلقائياً
- أسرع بكثير
- لا يحتاج معرفة تقنية
- دقة عالية في التحليل

---

## ملاحظات مهمة

1. **الخصوصية**: الصور المرفوعة يتم حفظها في `storage/certificate-templates/source-images/`
2. **التكلفة**: استخدام Gemini API قد يتطلب دفع (تحقق من خطة Google AI Studio)
3. **الدقة**: دقة التحليل تعتمد على جودة الصورة ووضوحها
4. **التعديلات**: يمكن دائماً تعديل Template المُنشأ يدوياً

---

## أمثلة الاستخدام

### مثال 1: رفع صورة Landscape
```javascript
// Frontend
const formData = new FormData();
formData.append('category_id', 2);
formData.append('name', 'Safety Certificate');
formData.append('certificate_image', imageFile);
formData.append('orientation', 'landscape');
formData.append('status', 'active');

const response = await fetch('/api/acc/certificate-templates/generate-from-image', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token
    },
    body: formData
});

const result = await response.json();
console.log('Template created:', result.template);
```

### مثال 2: رفع صورة Portrait
```javascript
// Frontend
const formData = new FormData();
formData.append('category_id', 3);
formData.append('name', 'Training Certificate');
formData.append('certificate_image', imageFile);
formData.append('orientation', 'portrait');
formData.append('status', 'active');

const response = await fetch('/api/acc/certificate-templates/generate-from-image', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token
    },
    body: formData
});
```

---

## الدعم الفني

إذا واجهت أي مشاكل:
1. تحقق من صحة `GEMINI_API_KEY` في `.env`
2. تحقق من جودة الصورة المرفوعة
3. راجع سجلات الأخطاء في Laravel
4. تأكد من أن الصورة بصيغة مدعومة وحجمها أقل من 10MB

---

**تاريخ الإنشاء**: 2026-01-12  
**الإصدار**: 1.0  
**الميزة**: AI-Powered Certificate Template Generation

