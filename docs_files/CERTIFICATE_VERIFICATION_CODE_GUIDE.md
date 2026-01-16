# دليل كود التحقق من الشهادات (Verification Code)

## نظرة عامة

يتم إنشاء كود التحقق (`verification_code`) تلقائياً من الـ backend عند إنشاء أي شهادة. هذا الكود يظهر في الـ PDF الخاص بالشهادة ويمكن استخدامه لاحقاً للتحقق من صحة الشهادة.

## كيفية عمل النظام

### 1. إنشاء كود التحقق

- يتم إنشاء `verification_code` تلقائياً عند إنشاء شهادة جديدة
- الصيغة: `VERIFY-` متبوع بـ 10 أحرف عشوائية (مثال: `VERIFY-ABC123XYZ`)
- الكود فريد ولا يتكرر (unique)
- يتم حفظه في قاعدة البيانات في جدول `certificates`

### 2. ظهور الكود في الشهادة (PDF)

- يتم إضافة `verification_code` تلقائياً إلى بيانات القالب (`certificateData`)
- يمكن استخدامه في تصميم القالب باستخدام المتغير: `{{verification_code}}`

#### مثال في Template HTML:

```html
<div style="position: absolute; bottom: 50px; right: 50px; font-size: 12px; color: #666;">
    Verification Code: {{verification_code}}
</div>
```

أو:

```html
<div class="verification-code">
    Code: {{ verification_code }}
</div>
```

### 3. التحقق من صحة الشهادة

#### Endpoint التحقق:

**GET** `/v1/api/certificates/verify/{code}`

هذا endpoint عام (public) ولا يتطلب مصادقة.

#### مثال الطلب:

```
GET /v1/api/certificates/verify/VERIFY-ABC123XYZ
```

#### Response عند نجاح التحقق:

```json
{
  "valid": true,
  "message": "Certificate is valid",
  "certificate": {
    "id": 1,
    "certificate_number": "CERT-2024-12345678",
    "verification_code": "VERIFY-ABC123XYZ",
    "trainee_name": "John Doe",
    "trainee_id_number": "ID123456",
    "course": {
      "id": 1,
      "name": "Fire Safety Training"
    },
    "issue_date": "2024-01-15",
    "expiry_date": "2026-01-15",
    "status": "valid",
    "training_center": {
      "id": 1,
      "name": "ABC Training Center"
    },
    "instructor": {
      "id": 1,
      "name": "Jane Smith"
    },
    "certificate_pdf_url": "https://..."
  }
}
```

#### Response عند فشل التحقق:

**الشهادة غير موجودة (404):**
```json
{
  "message": "Certificate not found"
}
```

**الشهادة ملغاة (400):**
```json
{
  "message": "Certificate has been revoked"
}
```

**الشهادة منتهية الصلاحية (400):**
```json
{
  "message": "Certificate has expired"
}
```

## المتغيرات المتاحة في Template

عند تصميم قالب الشهادة، يمكنك استخدام المتغيرات التالية:

| المتغير | الوصف | مثال |
|---------|-------|------|
| `{{verification_code}}` | كود التحقق الفريد | `VERIFY-ABC123XYZ` |
| `{{student_name}}` | اسم الطالب | `John Doe` |
| `{{trainee_name}}` | اسم المتدرب (نفس student_name) | `John Doe` |
| `{{course_name}}` | اسم الكورس | `Fire Safety Training` |
| `{{certificate_number}}` | رقم الشهادة | `CERT-2024-12345678` |
| `{{cert_id}}` | رقم الشهادة (نفس certificate_number) | `CERT-2024-12345678` |
| `{{date}}` | تاريخ الإصدار | `2024-01-15` |
| `{{issue_date}}` | تاريخ الإصدار | `2024-01-15` |
| `{{expiry_date}}` | تاريخ انتهاء الصلاحية | `2026-01-15` |

## أمثلة على استخدام Verification Code في Template

### مثال 1: في أسفل الشهادة

```html
<div style="position: absolute; bottom: 30px; left: 50px; font-size: 10px; color: #999;">
    Verify this certificate at: www.example.com/verify<br>
    Code: <strong>{{verification_code}}</strong>
</div>
```

### مثال 2: في زاوية الشهادة

```html
<div style="position: absolute; top: 20px; right: 20px; font-size: 9px; color: #ccc; text-align: right;">
    Verification Code:<br>
    <span style="font-family: monospace; font-weight: bold;">{{verification_code}}</span>
</div>
```

### مثال 3: في قسم منفصل

```html
<div class="verification-section" style="position: absolute; bottom: 0; width: 100%; text-align: center; padding: 20px; background: rgba(0,0,0,0.05);">
    <p style="font-size: 11px; margin: 5px 0;">
        This certificate can be verified using the code below:
    </p>
    <p style="font-size: 14px; font-weight: bold; font-family: monospace; letter-spacing: 2px;">
        {{verification_code}}
    </p>
</div>
```

## ملاحظات مهمة

1. **التصميم**: تأكد من وضع `verification_code` في مكان واضح ومرئي في الشهادة
2. **الخط**: يُنصح باستخدام خط monospace لسهولة القراءة
3. **الحجم**: يجب أن يكون حجم الخط مناسباً للقراءة (على الأقل 9-10px)
4. **اللون**: استخدم لوناً يبرز عن الخلفية
5. **الموقع**: ضع الكود في مكان لا يمكن حذفه أو تعديله بسهولة (مثل الزوايا أو الأسفل)

## استخدامات Verification Code

1. **التحقق من صحة الشهادة**: يمكن لأي شخص التحقق من صحة الشهادة باستخدام الكود
2. **منع التزوير**: الكود الفريد يجعل من الصعب تزوير الشهادات
3. **التتبع**: يمكن تتبع الشهادة في النظام باستخدام الكود
4. **التحقق الذاتي**: يمكن للمتدربين التحقق من شهاداتهم بسهولة

## الأمان

- الكود فريد ولا يتكرر
- لا يمكن تخمين الكود (10 أحرف عشوائية)
- الكود مرتبط بشهادة واحدة فقط
- يمكن إلغاء الشهادة مما يجعل الكود غير صالح

## Frontend Integration

### مثال على صفحة التحقق:

```javascript
// Function to verify certificate
async function verifyCertificate(code) {
  try {
    const response = await fetch(`/v1/api/certificates/verify/${code}`);
    const data = await response.json();
    
    if (response.ok && data.valid) {
      // Display certificate details
      console.log('Certificate is valid:', data.certificate);
      return data.certificate;
    } else {
      // Display error message
      console.error('Verification failed:', data.message);
      return null;
    }
  } catch (error) {
    console.error('Error verifying certificate:', error);
    return null;
  }
}

// Usage
const certificate = await verifyCertificate('VERIFY-ABC123XYZ');
```

## API Endpoints Summary

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/v1/api/certificates/verify/{code}` | التحقق من صحة الشهادة | No (Public) |
| POST | `/v1/api/training-center/certificates` | إنشاء شهادة جديدة | Yes (Training Center) |

## الخلاصة

- `verification_code` يتم إنشاؤه تلقائياً عند إنشاء الشهادة
- يظهر في الـ PDF باستخدام `{{verification_code}}` في Template
- يمكن التحقق من صحة الشهادة باستخدام endpoint `/certificates/verify/{code}`
- الكود فريد وآمن ويساعد في منع التزوير

