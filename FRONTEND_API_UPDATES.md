# تحديثات API للفرونت إند - Frontend API Updates

## تاريخ التحديث / Update Date
**2026-01-03**

---

## ملخص التغييرات / Summary of Changes

تم إصلاح مشكلتين رئيسيتين:
1. **خطأ 403 عند الوصول إلى discount codes**
2. **خطأ 500 عند الشراء بسبب payment_method غير صالح**

---

## 1. إصلاح خطأ 403 - Discount Codes Endpoint

### المشكلة / Problem
كان الـ endpoint `/acc/{id}/discount-codes` محمي بـ `role:acc_admin` فقط، مما يمنع training centers من الوصول إليه.

### الحل / Solution
تم إضافة route عامة متاحة لجميع المستخدمين المسجلين دخول.

### الـ Endpoint الجديد / New Endpoint

```
GET /api/acc/{id}/discount-codes
```

**المتطلبات / Requirements:**
- Authentication: مطلوب (Bearer Token)
- Role: أي مستخدم مسجل دخول (training_center_admin, acc_admin, إلخ)

**المعاملات / Parameters:**
- `id` (path parameter): رقم ACC

**مثال على الطلب / Request Example:**
```javascript
// يمكنك استخدام نفس الكود الحالي
GET https://aeroenix.com/v1/api/acc/7/discount-codes
Headers: {
  Authorization: 'Bearer YOUR_TOKEN'
}
```

**مثال على الـ Response / Response Example:**
```json
{
  "discount_codes": [
    {
      "id": 1,
      "code": "DISCOUNT10",
      "discount_percentage": 10,
      "discount_type": "time_limited",
      "start_date": "2026-01-01",
      "end_date": "2026-12-31",
      "status": "active",
      "acc_id": 7,
      ...
    }
  ]
}
```

**ملاحظة مهمة / Important Note:**
- الـ endpoint يعيد فقط discount codes النشطة (`status: 'active'`)
- لا حاجة لتغيير الكود في الفرونت إند - نفس الـ endpoint يعمل الآن

---

## 2. إصلاح خطأ 500 - Purchase Endpoint

### المشكلة / Problem
كان يحدث خطأ 500 عند محاولة الشراء بسبب:
- التحقق من `payment_method` كان يحدث بعد بدء Transaction
- عند وجود قيمة غير صالحة، كان يتم عمل rollback مما يسبب خطأ 500

### الحل / Solution
تم نقل التحقق من `payment_method` إلى قبل بدء Transaction، مما يضمن:
- إرجاع خطأ 422 بدلاً من 500 عند وجود قيمة غير صالحة
- رسالة خطأ واضحة تساعد في التصحيح

### القيم المسموحة لـ payment_method / Valid payment_method Values

```javascript
const VALID_PAYMENT_METHODS = [
  'wallet',        // الدفع من المحفظة
  'credit_card',   // الدفع بالبطاقة الائتمانية
  'manual_payment' // الدفع اليدوي (تحويل بنكي)
];
```

### الـ Endpoint

```
POST /api/training-center/codes/purchase
```

**مثال على الطلب الصحيح / Correct Request Example:**

```javascript
// للدفع بالبطاقة الائتمانية
POST https://aeroenix.com/v1/api/training-center/codes/purchase
Headers: {
  Authorization: 'Bearer YOUR_TOKEN',
  'Content-Type': 'application/json'
}
Body: {
  "acc_id": 7,
  "course_id": 25,
  "quantity": 1,
  "payment_method": "credit_card",  // ✅ قيمة صحيحة
  "payment_intent_id": "pi_3Sla6FC7FGiektWu2BpjLvlV",
  "payment_method_id": "pm_xxxxx",  // اختياري - لإرفاق payment method
  "discount_code": "DISCOUNT10"     // اختياري
}
```

**مثال على الطلب الخاطئ / Incorrect Request Example:**

```javascript
// ❌ خطأ - payment_method غير صالح
Body: {
  "payment_method": "cash",  // ❌ قيمة غير مسموحة
  ...
}
```

**مثال على الـ Response عند الخطأ / Error Response Example:**

```json
{
  "message": "Invalid payment method. Allowed values: wallet, credit_card, manual_payment",
  "error": "Invalid payment_method value: cash",
  "error_code": "invalid_payment_method"
}
```

**Status Code:** `422 Unprocessable Entity`

---

## 3. التحقق من payment_method في الفرونت إند

### توصية / Recommendation

نوصي بإضافة التحقق في الفرونت إند قبل إرسال الطلب:

```javascript
// مثال على التحقق في JavaScript/React
const VALID_PAYMENT_METHODS = ['wallet', 'credit_card', 'manual_payment'];

function validatePurchaseRequest(data) {
  // التحقق من payment_method
  if (!VALID_PAYMENT_METHODS.includes(data.payment_method)) {
    throw new Error(
      `Invalid payment method. Allowed values: ${VALID_PAYMENT_METHODS.join(', ')}`
    );
  }
  
  // التحقق من الحقول المطلوبة
  if (data.payment_method === 'credit_card' && !data.payment_intent_id) {
    throw new Error('payment_intent_id is required for credit card payments');
  }
  
  if (data.payment_method === 'manual_payment') {
    if (!data.payment_receipt) {
      throw new Error('Payment receipt is required for manual payment');
    }
    if (!data.payment_amount || data.payment_amount <= 0) {
      throw new Error('Payment amount is required and must be greater than 0');
    }
  }
  
  return true;
}

// استخدام التحقق قبل الإرسال
try {
  validatePurchaseRequest(purchaseData);
  const response = await purchaseCodes(purchaseData);
  // معالجة النجاح
} catch (error) {
  // معالجة الخطأ
  console.error('Validation error:', error.message);
}
```

---

## 4. ملخص التغييرات في الـ API

### Endpoints المتأثرة / Affected Endpoints

| Endpoint | Method | التغيير / Change |
|----------|--------|------------------|
| `/api/acc/{id}/discount-codes` | GET | ✅ أصبح متاحاً لجميع المستخدمين المسجلين دخول |
| `/api/training-center/codes/purchase` | POST | ✅ تحسين معالجة الأخطاء - إرجاع 422 بدلاً من 500 |

### Error Codes الجديدة / New Error Codes

| Error Code | الوصف / Description | Status Code |
|------------|---------------------|-------------|
| `invalid_payment_method` | قيمة payment_method غير صالحة | 422 |

---

## 5. أمثلة على الكود / Code Examples

### مثال كامل على الشراء / Complete Purchase Example

```javascript
// React Component Example
import { purchaseCodes } from './api';

async function handlePurchase() {
  try {
    // 1. التحقق من البيانات
    const purchaseData = {
      acc_id: selectedAccId,
      course_id: selectedCourseId,
      quantity: quantity,
      payment_method: 'credit_card', // أو 'wallet' أو 'manual_payment'
      payment_intent_id: paymentIntentId,
      payment_method_id: paymentMethodId, // اختياري
      discount_code: discountCode || null
    };
    
    // 2. التحقق من القيم المسموحة
    const validMethods = ['wallet', 'credit_card', 'manual_payment'];
    if (!validMethods.includes(purchaseData.payment_method)) {
      setError('Invalid payment method');
      return;
    }
    
    // 3. إرسال الطلب
    const response = await purchaseCodes(purchaseData);
    
    // 4. معالجة النجاح
    if (response.success) {
      console.log('Purchase successful:', response.data);
      // تحديث الواجهة
    }
    
  } catch (error) {
    // 5. معالجة الأخطاء
    if (error.response?.status === 422) {
      // خطأ في التحقق من البيانات
      const errorData = error.response.data;
      if (errorData.error_code === 'invalid_payment_method') {
        setError(errorData.message);
      } else {
        setError(errorData.message || 'Validation error');
      }
    } else if (error.response?.status === 400) {
      // خطأ في الدفع
      setError(error.response.data.message || 'Payment error');
    } else if (error.response?.status === 500) {
      // خطأ في السيرفر
      setError('Server error. Please try again later.');
    } else {
      setError('An unexpected error occurred');
    }
  }
}
```

---

## 6. الاختبار / Testing

### سيناريوهات الاختبار / Test Scenarios

1. **اختبار Discount Codes:**
   ```bash
   # يجب أن يعمل الآن بدون خطأ 403
   GET /api/acc/7/discount-codes
   Authorization: Bearer TRAINING_CENTER_TOKEN
   ```

2. **اختبار Purchase مع payment_method صالح:**
   ```bash
   POST /api/training-center/codes/purchase
   {
     "payment_method": "credit_card",
     ...
   }
   # يجب أن يعمل بدون خطأ 500
   ```

3. **اختبار Purchase مع payment_method غير صالح:**
   ```bash
   POST /api/training-center/codes/purchase
   {
     "payment_method": "invalid_method",
     ...
   }
   # يجب أن يعيد 422 مع رسالة خطأ واضحة
   ```

---

## 7. الأسئلة الشائعة / FAQ

### Q: هل أحتاج لتغيير الكود في الفرونت إند؟
**A:** لا، بالنسبة لـ discount codes endpoint - نفس الـ endpoint يعمل الآن. بالنسبة لـ purchase endpoint، نوصي بإضافة التحقق من `payment_method` قبل الإرسال.

### Q: ما هي القيم المسموحة لـ payment_method؟
**A:** `wallet`, `credit_card`, `manual_payment` فقط.

### Q: ماذا يحدث إذا أرسلت payment_method غير صالح؟
**A:** ستحصل على خطأ 422 مع رسالة توضح القيم المسموحة.

### Q: هل يمكنني استخدام نفس endpoint للـ discount codes؟
**A:** نعم، نفس الـ endpoint `/api/acc/{id}/discount-codes` يعمل الآن لجميع المستخدمين المسجلين دخول.

---

## 8. الدعم / Support

إذا واجهت أي مشاكل أو لديك أسئلة، يرجى التواصل مع فريق التطوير.

---

**آخر تحديث / Last Updated:** 2026-01-03
**الإصدار / Version:** 1.0.0
