# Certificate PDF Generation - Frontend Developer Guide

## Overview
تم تحديث نظام الشهادات لدعم إنشاء PDF تلقائياً عند إنشاء الشهادة. كما تم إضافة نظام جديد لإنشاء Templates باستخدام حقول منفصلة بدلاً من HTML كامل.

---

## What's New

### 1. Automatic PDF Generation
- عند إنشاء شهادة، يتم إنشاء PDF تلقائياً
- PDF يتم حفظه في Storage
- الرابط يتم إرجاعه في `certificate_pdf_url`

### 2. Template Configuration System
- بدلاً من إدخال HTML كامل، يمكن إدخال حقول منفصلة لكل متغير
- النظام يقوم بإنشاء HTML تلقائياً من هذه الحقول
- دعم رفع صورة خلفية مباشرة

### 3. PDF Download Endpoints
- إضافة endpoint جديد لتحميل PDF
- دعم الروابط المباشرة للـ PDF

---

## API Changes

### 1. Create Certificate Template (Updated)

**Endpoint:** `POST /acc/certificate-templates`

**Changes:**
- الآن يمكن إرسال `template_config` بدلاً من `template_html`
- دعم رفع ملف للصورة الخلفية (`background_image`)

**Request Format (New Way - Recommended):**
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

**Request Format (Old Way - Still Supported):**
```json
{
  "category_id": 1,
  "name": "Fire Safety Certificate Template",
  "status": "active",
  "template_html": "<div>...</div>",
  "background_image_url": "/storage/templates/background.jpg"
}
```

**Important Notes:**
- يمكن إرسال `template_config` أو `template_html` أو كليهما
- إذا تم إرسال `template_config` فقط، سيتم إنشاء HTML تلقائياً
- إذا تم إرسال `background_image` كملف، سيتم تجاهل `background_image_url`
- `background_image` يجب أن يكون: JPEG, PNG, JPG, GIF - max 5MB

---

### 2. Generate Certificate (Updated)

**Endpoint:** `POST /training-center/certificates/generate`

**Changes:**
- الآن يتم إنشاء PDF تلقائياً عند إنشاء الشهادة
- `certificate_pdf_url` يحتوي على رابط PDF الفعلي

**Request:** (No changes)
```json
{
  "training_class_id": 1,
  "code_id": 1,
  "trainee_name": "John Doe",
  "trainee_id_number": "ID123456",
  "issue_date": "2024-01-15",
  "expiry_date": "2025-01-15"
}
```

**Response:** (Updated)
```json
{
  "message": "Certificate generated successfully",
  "certificate": {
    "id": 1,
    "certificate_number": "CERT-ABC123XYZ",
    "trainee_name": "John Doe",
    "certificate_pdf_url": "https://aeroenix.com/storage/certificates/7Fw4OM3V8TVDrNboGQu1.pdf",
    "verification_code": "VERIFY-ABC123",
    "status": "valid",
    ...
  }
}
```

**Important Notes:**
- `certificate_pdf_url` الآن يحتوي على رابط PDF فعلي
- PDF يتم إنشاؤه تلقائياً عند إنشاء الشهادة
- إذا فشل إنشاء PDF، سيتم تسجيل خطأ لكن الشهادة ستُنشأ بدون PDF

---

### 3. Download Certificate PDF (New)

**Endpoint:** `GET /training-center/certificates/{id}/download`

**Description:** تحميل PDF للشهادة من Dashboard

**Authentication:** Required (Training Center Admin)

**Response:**
- PDF file مباشرة
- Content-Type: `application/pdf`
- Content-Disposition: `inline; filename="certificate-{certificate_number}.pdf"`

**Example:**
```javascript
// Using fetch
const response = await fetch(`/api/training-center/certificates/${certificateId}/download`, {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const blob = await response.blob();
const url = window.URL.createObjectURL(blob);
const a = document.createElement('a');
a.href = url;
a.download = `certificate-${certificateNumber}.pdf`;
a.click();
```

---

### 4. Direct PDF Link (New)

**Endpoint:** `GET /certificates/{filename}`

**Description:** رابط مباشر لتحميل PDF (public)

**Authentication:** Not required

**Example URL:**
```
https://aeroenix.com/api/certificates/7Fw4OM3V8TVDrNboGQu1.pdf
```

**Response:**
- PDF file مباشرة
- إذا لم يوجد الملف، سيتم محاولة إعادة إنشائه تلقائياً
- إذا فشل، سيتم إرجاع 404

**Important Notes:**
- هذا endpoint عام (لا يحتاج authentication)
- يمكن استخدامه في أي مكان (email, website, etc.)
- إذا كان الملف غير موجود، سيتم محاولة إعادة إنشائه

---

## Frontend Implementation Guide

### 1. Create Certificate Template Page

#### Form Structure

**Basic Information:**
- Category (dropdown)
- Template Name (text input)
- Status (radio: Active/Inactive)

**Template Design Configuration:**

**Title Section:**
```javascript
{
  title: {
    text: "Certificate of Completion", // text input
    show: true, // checkbox
    position: "top-center", // dropdown: top-center, top-left, top-right, center, etc.
    font_size: "48px", // text input
    font_weight: "bold", // dropdown: bold, normal, etc.
    color: "#2c3e50" // color picker
  }
}
```

**Trainee Name Section:**
```javascript
{
  trainee_name: {
    show: true, // checkbox
    position: "center", // dropdown
    font_size: "36px", // text input
    font_weight: "bold", // dropdown
    color: "#2c3e50" // color picker
  }
}
```

**Course Name Section:**
```javascript
{
  course_name: {
    show: true, // checkbox
    position: "center", // dropdown
    font_size: "24px", // text input
    color: "#34495e" // color picker
  }
}
```

**Other Fields:**
```javascript
{
  certificate_number: {
    show: true, // checkbox
    position: "bottom-left" // dropdown
  },
  issue_date: {
    show: true, // checkbox
    position: "bottom-center" // dropdown
  },
  verification_code: {
    show: true, // checkbox
    position: "bottom-right" // dropdown
  }
}
```

**Layout Settings:**
```javascript
{
  layout: {
    orientation: "landscape", // radio: portrait, landscape
    border_color: "#D4AF37", // color picker
    border_width: "15px", // text input
    background_color: "#ffffff" // color picker
  }
}
```

**Background Image:**
- File upload component (drag & drop)
- Supported formats: JPEG, PNG, JPG, GIF
- Max size: 5MB
- Preview after upload

#### Form Submission

**Using FormData (for file upload):**
```javascript
const formData = new FormData();
formData.append('category_id', categoryId);
formData.append('name', templateName);
formData.append('status', status);
formData.append('template_config', JSON.stringify(templateConfig));
formData.append('background_image', backgroundImageFile); // if uploaded

const response = await fetch('/api/acc/certificate-templates', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`
    // Don't set Content-Type header - browser will set it automatically with boundary
  },
  body: formData
});
```

**Using JSON (without file upload):**
```javascript
const response = await fetch('/api/acc/certificate-templates', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    category_id: categoryId,
    name: templateName,
    status: status,
    template_config: templateConfig,
    background_image_url: backgroundImageUrl // if using URL instead of file
  })
});
```

---

### 2. Generate Certificate Page

**No Changes Required** - نفس الطريقة السابقة

**After Generation:**
- عرض `certificate_pdf_url` في النتيجة
- إضافة زر "Download PDF" يفتح الرابط مباشرة
- أو استخدام download endpoint

---

### 3. Certificate List Page

**Display PDF Link:**
```javascript
// In certificate card/item
{certificate.certificate_pdf_url && (
  <a 
    href={certificate.certificate_pdf_url} 
    target="_blank"
    className="btn-download"
  >
    View PDF
  </a>
)}

// Or use download endpoint
<button onClick={() => downloadCertificate(certificate.id)}>
  Download PDF
</button>
```

**Download Function:**
```javascript
const downloadCertificate = async (certificateId) => {
  try {
    const response = await fetch(`/api/training-center/certificates/${certificateId}/download`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    
    if (!response.ok) {
      throw new Error('Failed to download PDF');
    }
    
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `certificate-${certificateId}.pdf`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  } catch (error) {
    console.error('Error downloading certificate:', error);
    alert('Failed to download certificate PDF');
  }
};
```

---

### 4. Certificate Details Page

**Display PDF:**
```javascript
// Option 1: Direct link
<a href={certificate.certificate_pdf_url} target="_blank">
  View Certificate PDF
</a>

// Option 2: Embed PDF
<iframe 
  src={certificate.certificate_pdf_url} 
  width="100%" 
  height="600px"
  title="Certificate PDF"
/>

// Option 3: Download button
<button onClick={() => downloadCertificate(certificate.id)}>
  Download PDF
</button>
```

---

## Template Configuration Structure

### Complete Example

```javascript
const templateConfig = {
  title: {
    text: "Certificate of Completion",
    show: true,
    position: "top-center",
    font_size: "48px",
    font_weight: "bold",
    color: "#2c3e50"
  },
  trainee_name: {
    show: true,
    position: "center",
    font_size: "36px",
    font_weight: "bold",
    color: "#2c3e50"
  },
  course_name: {
    show: true,
    position: "center",
    font_size: "24px",
    color: "#34495e"
  },
  certificate_number: {
    show: true,
    position: "bottom-left"
  },
  issue_date: {
    show: true,
    position: "bottom-center"
  },
  verification_code: {
    show: true,
    position: "bottom-right"
  },
  layout: {
    orientation: "landscape", // or "portrait"
    border_color: "#D4AF37",
    border_width: "15px",
    background_color: "#ffffff"
  }
};
```

### Position Options

**Available positions:**
- `top-center`
- `top-left`
- `top-right`
- `center`
- `bottom-center`
- `bottom-left`
- `bottom-right`

### Font Weight Options

**Available weights:**
- `normal`
- `bold`
- `100` - `900`

### Orientation Options

- `portrait` - عمودي
- `landscape` - أفقي

---

## Error Handling

### PDF Generation Failed

**Scenario:** PDF لم يتم إنشاؤه عند إنشاء الشهادة

**Response:**
```json
{
  "message": "Certificate generated successfully",
  "certificate": {
    "certificate_pdf_url": "/certificates/placeholder.pdf",
    ...
  }
}
```

**Frontend Handling:**
```javascript
// Check if PDF URL is valid
if (certificate.certificate_pdf_url && 
    !certificate.certificate_pdf_url.includes('placeholder')) {
  // PDF generated successfully
  showDownloadButton();
} else {
  // PDF generation failed
  showWarning('PDF will be generated shortly. Please try again later.');
}
```

### PDF Not Found

**Scenario:** محاولة فتح رابط PDF غير موجود

**Response:** 404 Not Found

**Frontend Handling:**
```javascript
// When opening PDF link
const openPdf = async (url) => {
  try {
    const response = await fetch(url, { method: 'HEAD' });
    if (response.ok) {
      window.open(url, '_blank');
    } else {
      // PDF not found - try to regenerate
      await regeneratePdf(certificateId);
    }
  } catch (error) {
    alert('PDF not available. Please try again later.');
  }
};
```

---

## Best Practices

### 1. Template Creation
- استخدم `template_config` بدلاً من `template_html` (أسهل وأبسط)
- اختبر Template مع Preview قبل الحفظ
- تأكد من أن جميع الحقول المطلوبة موجودة

### 2. PDF Handling
- دائماً تحقق من وجود `certificate_pdf_url` قبل عرضه
- استخدم download endpoint للتحميل المباشر
- استخدم direct link للعرض في iframe أو نافذة جديدة

### 3. Error Handling
- تعامل مع حالات فشل إنشاء PDF
- اعرض رسائل واضحة للمستخدم
- أضف retry mechanism إذا لزم الأمر

### 4. Performance
- استخدم lazy loading للـ PDFs
- اعرض loading state أثناء التحميل
- cache PDF URLs إذا أمكن

---

## Migration Guide

### For Existing Templates

**If you have templates with `template_html`:**
- لا حاجة لتغيير شيء - النظام يدعم الطريقتين
- يمكنك تحديث Templates تدريجياً لاستخدام `template_config`

**If you want to migrate:**
1. افتح Template في Edit mode
2. أضف `template_config` مع الحقول المطلوبة
3. احفظ Template
4. النظام سيستخدم `template_config` تلقائياً

---

## Testing Checklist

### Template Creation
- [ ] إنشاء Template باستخدام `template_config`
- [ ] رفع صورة خلفية
- [ ] Preview Template
- [ ] حفظ Template

### Certificate Generation
- [ ] إنشاء شهادة جديدة
- [ ] التحقق من إنشاء PDF
- [ ] التحقق من `certificate_pdf_url`

### PDF Download
- [ ] تحميل PDF من Dashboard
- [ ] فتح رابط PDF المباشر
- [ ] التحقق من أن PDF يعرض بشكل صحيح

### Error Handling
- [ ] اختبار حالة فشل إنشاء PDF
- [ ] اختبار حالة PDF غير موجود
- [ ] اختبار رسائل الخطأ

---

## Common Issues and Solutions

### Issue 1: PDF Shows White Screen
**Problem:** PDF يفتح لكن صفحة بيضاء

**Causes:**
1. PDF لم يتم إنشاؤه بعد
2. الملف غير موجود في Storage
3. مشكلة في الرابط

**Solutions:**
1. انتظر قليلاً ثم حاول مرة أخرى
2. استخدم download endpoint بدلاً من direct link
3. تحقق من console للأخطاء

### Issue 2: Template Config Not Working
**Problem:** Template Config لا يعمل

**Causes:**
1. JSON format غير صحيح
2. حقول مفقودة

**Solutions:**
1. تحقق من صحة JSON format
2. تأكد من وجود جميع الحقول المطلوبة
3. استخدم Preview لاختبار Template

### Issue 3: Background Image Not Showing
**Problem:** صورة الخلفية لا تظهر

**Causes:**
1. الملف لم يتم رفعه بشكل صحيح
2. URL غير صحيح

**Solutions:**
1. تأكد من رفع الملف باستخدام FormData
2. تحقق من حجم الملف (max 5MB)
3. تحقق من صيغة الملف (JPEG, PNG, JPG, GIF)

---

## Summary

### Key Changes
1. ✅ PDF يتم إنشاؤه تلقائياً عند إنشاء الشهادة
2. ✅ نظام جديد لإنشاء Templates باستخدام حقول منفصلة
3. ✅ دعم رفع صورة خلفية مباشرة
4. ✅ Endpoints جديدة لتحميل PDF

### What You Need to Do
1. تحديث صفحة Create Template لدعم `template_config`
2. إضافة زر Download PDF في صفحات الشهادات
3. تحديث عرض `certificate_pdf_url` في النتائج
4. إضافة error handling لـ PDF generation

### API Endpoints Summary
- `POST /acc/certificate-templates` - إنشاء Template (محدث)
- `POST /training-center/certificates/generate` - إنشاء شهادة (محدث)
- `GET /training-center/certificates/{id}/download` - تحميل PDF (جديد)
- `GET /certificates/{filename}` - رابط مباشر للـ PDF (جديد)

---

## Questions or Issues

إذا كان لديك أي استفسارات أو مشاكل متعلقة بهذه التغييرات، يرجى التواصل مع فريق Backend.

