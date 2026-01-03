# Payment Splitting System Documentation

## نظرة عامة

يستخدم النظام **Stripe Destination Charges** لتقسيم المدفوعات تلقائياً بين ACC (مزود الخدمة) و Group Admin (المنصة). يتم تقسيم الأموال تلقائياً دون الحاجة لتحويلات يدوية.

---

## كيف يعمل النظام

### آلية التقسيم التلقائي

عند قيام العميل بالدفع:

1. **العميل يدفع المبلغ الكامل** (مثال: 1000 جنيه مصري)
2. **Stripe يقوم بالتقسيم تلقائياً**:
   - ACC (مزود الخدمة) يستلم: المبلغ الكامل - نسبة العمولة
   - Group Admin (المنصة) يستلم: نسبة العمولة
3. **كل شيء تلقائي** - لا حاجة لتحويلات يدوية

### مثال عملي

- **المبلغ الكامل**: 1000 جنيه مصري
- **نسبة العمولة**: 10% (100 جنيه مصري)
- **ACC يستلم**: 900 جنيه مصري (تلقائياً في حسابه على Stripe)
- **Group Admin يستلم**: 100 جنيه مصري (تلقائياً في حساب المنصة)

---

## متطلبات النظام

### 1. Stripe Connect Account للـ ACC

لكي يعمل التقسيم التلقائي، يجب أن يكون لكل ACC:

- **حساب Stripe Connect** نشط ومتصل
- **معرف الحساب** (Account ID) يبدأ بـ `acct_`
- **الحساب مفعّل** وقادر على استقبال المدفوعات

### 2. إعدادات ACC

يجب أن يحتوي سجل ACC على:

- **stripe_account_id**: معرف حساب Stripe Connect
- **commission_percentage**: نسبة العمولة (مثال: 10 يعني 10%)

### 3. حساب Stripe للمنصة

حساب Group Admin (المنصة) يجب أن يكون:

- **مفعّل Stripe Connect**
- **مكوّن بشكل صحيح** في إعدادات النظام

---

## حساب العمولة

### طريقة الحساب

العمولة تُحسب بناءً على:

- **نسبة العمولة** (`commission_percentage`) المخزنة في سجل ACC
- **المبلغ الكامل** للدفع

**الصيغة:**
- مبلغ العمولة = (المبلغ الكامل × نسبة العمولة) ÷ 100
- مبلغ ACC = المبلغ الكامل - مبلغ العمولة

### أمثلة

**مثال 1:**
- المبلغ الكامل: 1000 جنيه
- نسبة العمولة: 10%
- مبلغ العمولة: 100 جنيه
- مبلغ ACC: 900 جنيه

**مثال 2:**
- المبلغ الكامل: 5000 جنيه
- نسبة العمولة: 15%
- مبلغ العمولة: 750 جنيه
- مبلغ ACC: 4250 جنيه

---

## تدفق الدفع

### الخطوات الكاملة

1. **إنشاء Payment Intent**
   - النظام يتحقق من وجود `stripe_account_id` للـ ACC
   - يحسب مبلغ العمولة بناءً على `commission_percentage`
   - ينشئ Payment Intent مع Destination Charge

2. **العميل يدفع**
   - العميل يكمل عملية الدفع
   - Stripe يستقبل المبلغ الكامل

3. **التقسيم التلقائي**
   - Stripe يرسل مبلغ ACC مباشرة لحساب Stripe Connect الخاص به
   - Stripe يرسل مبلغ العمولة لحساب المنصة
   - كل شيء يحدث تلقائياً في نفس اللحظة

4. **التأكيد**
   - النظام يتلقى تأكيد نجاح الدفع
   - يتم تسجيل المعاملة في قاعدة البيانات
   - يتم تحديث سجلات العمولة

---

## حالات الاستخدام

### الحالة 1: ACC لديه Stripe Account

**السيناريو:**
- ACC لديه `stripe_account_id` و `commission_percentage`
- العميل يدفع 1000 جنيه

**النتيجة:**
- ✅ استخدام Destination Charges
- ✅ ACC يستلم 900 جنيه تلقائياً
- ✅ Group Admin يستلم 100 جنيه تلقائياً
- ✅ لا حاجة لتحويلات يدوية

### الحالة 2: ACC لا يملك Stripe Account

**السيناريو:**
- ACC لا يملك `stripe_account_id`
- العميل يدفع 1000 جنيه

**النتيجة:**
- ⚠️ استخدام الدفع العادي (Standard Payment)
- ⚠️ المبلغ الكامل يذهب لحساب المنصة
- ⚠️ يتم تسجيل العمولة في سجل المعاملات
- ⚠️ يتم التعامل مع العمولة يدوياً لاحقاً

---

## API Endpoints

### 1. التحقق من حساب Stripe

**Endpoint:** `POST /acc/profile/verify-stripe-account`

**الوصف:**
يتحقق من صحة معرف حساب Stripe Connect ويرجع معلومات الحساب إذا كان صحيحاً ومتصلاً بالمنصة.

**المصادقة:**
يتطلب Bearer Token (ACC Admin فقط)

**Request Body:**
- `stripe_account_id` (مطلوب): معرف حساب Stripe Connect للتحقق منه

**Response Success (200):**
- `valid`: true/false - هل الحساب صحيح ومتصل
- `account`: معلومات الحساب (إذا كان صحيحاً)
  - `id`: معرف الحساب
  - `type`: نوع الحساب
  - `charges_enabled`: هل يمكن استقبال المدفوعات
  - `payouts_enabled`: هل يمكن سحب الأموال
  - `details_submitted`: هل تم إكمال بيانات الحساب
- `message`: رسالة توضيحية

**Response Error (400):**
- `valid`: false
- `error`: رسالة الخطأ
- `message`: رسالة توضيحية للخطأ

**ملاحظات:**
- هذا الـ endpoint للتحقق فقط، لا يضيف الحساب
- يمكن للـ ACC استخدامه للتحقق من صحة حسابه قبل إضافته
- Group Admin يستخدمه للتحقق قبل إضافة الحساب للـ ACC

---

### 2. إدارة Stripe Account (Group Admin)

**ملاحظة:** حالياً ACC يمكنه إضافة Stripe Account من خلال تحديث الملف الشخصي.
#### 2.1. إضافة/تحديث Stripe Account للـ ACC

**Endpoint:** `PUT /admin/accs/{id}/stripe-account`

**الوصف:**
يسمح لـ Group Admin بإضافة أو تحديث Stripe Account ID لـ ACC معين.

**المصادقة:**
يتطلب Bearer Token (Group Admin فقط)

**Request Body:**
- `stripe_account_id` (مطلوب): معرف حساب Stripe Connect (يجب أن يبدأ بـ `acct_`)
- `verify` (اختياري): true/false - هل تريد التحقق من الحساب قبل الإضافة (افتراضي: true)

**Response Success (200):**
- `message`: "Stripe account added successfully" أو "Stripe account updated successfully"
- `acc`: معلومات ACC المحدثة
  - `id`: معرف ACC
  - `stripe_account_id`: معرف حساب Stripe
  - `stripe_account_configured`: true/false
  - `commission_percentage`: نسبة العمولة

**Response Error (400):**
- `message`: رسالة الخطأ
- `error`: تفاصيل الخطأ (إذا كان الحساب غير صحيح)

**Response Error (404):**
- `message`: "ACC not found"

**Response Error (422):**
- `message`: "Validation failed"
- `errors`: أخطاء التحقق

**ملاحظات:**
- Group Admin فقط يمكنه استخدام هذا الـ endpoint
- يتم التحقق من صحة الحساب قبل الإضافة
- إذا كان الحساب غير صحيح، يتم رفض الطلب

#### 2.2. حذف Stripe Account

**Endpoint:** `DELETE /admin/accs/{id}/stripe-account`

**الوصف:**
يحذف Stripe Account ID من ACC (لا يحذف الحساب من Stripe، فقط يزيل الربط).

**المصادقة:**
يتطلب Bearer Token (Group Admin فقط)

**Response Success (200):**
- `message`: "Stripe account removed successfully"
- `acc`: معلومات ACC المحدثة

**Response Error (404):**
- `message`: "ACC not found"

---

### 3. عرض معلومات Stripe Account

#### 3.1. ACC Profile (يشمل Stripe Info)

**Endpoint:** `GET /acc/profile`

**الوصف:**
يعرض ملف ACC الشخصي بما في ذلك معلومات Stripe Account.

**Response Fields:**
- `stripe_account_id`: معرف حساب Stripe (إذا كان موجود)
- `stripe_account_configured`: true/false - هل تم إعداد الحساب

---

## أنواع المدفوعات المدعومة

### 1. Code Purchases (شراء رموز الشهادات)

**الوصف:**
عند شراء Training Center لرموز شهادات من ACC.

**التقسيم:**
- يتم استخدام Destination Charges إذا كان ACC لديه `stripe_account_id`
- يتم حساب العمولة بناءً على `commission_percentage` في ACC
- ACC يستلم المبلغ بعد خصم العمولة تلقائياً

### 2. Instructor Authorization (تفويض المدرب)

**الوصف:**
عند تفويض Training Center لمدرب من خلال ACC.

**التقسيم:**
- يتم استخدام Destination Charges إذا كان ACC لديه `stripe_account_id`
- يتم حساب العمولة بناءً على `commission_percentage` في Authorization
- ACC يستلم المبلغ بعد خصم العمولة تلقائياً

### 3. Course Purchases (شراء الدورات)

**الوصف:**
عند شراء دورات من ACC.

**التقسيم:**
- يتم استخدام Destination Charges إذا كان ACC لديه `stripe_account_id`
- يتم حساب العمولة بناءً على `commission_percentage` في ACC
- ACC يستلم المبلغ بعد خصم العمولة تلقائياً

---

## السلوك الافتراضي (Fallback)

### عندما لا يوجد Stripe Account

إذا كان ACC لا يملك `stripe_account_id`:

1. **الدفع العادي:**
   - يتم استخدام الدفع العادي (Standard Payment)
   - المبلغ الكامل يذهب لحساب المنصة

2. **تسجيل العمولة:**
   - يتم تسجيل العمولة في سجل المعاملات (Transaction Ledger)
   - يتم تسجيل العمولة في Commission Ledger

3. **التسوية اليدوية:**
   - Group Admin يقوم بتسوية العمولة يدوياً لاحقاً
   - يمكن استخدام Monthly Settlements

### عندما تكون نسبة العمولة صفر

إذا كانت `commission_percentage` = 0:

- ACC يستلم المبلغ الكامل
- Group Admin لا يستلم عمولة
- لا يزال يتم استخدام Destination Charges (إذا كان هناك Stripe Account)

---

## المزايا

### ✅ التقسيم التلقائي
- الأموال تُقسّم تلقائياً دون تدخل يدوي
- لا حاجة لتحويلات إضافية

### ✅ الوقت الفعلي (Real-time)
- الأموال متاحة فوراً
- ACC يستلم أمواله مباشرة في حسابه

### ✅ الأمان
- Stripe يتعامل مع جميع عمليات الأمان
- لا يتم تخزين بيانات البطاقات على المنصة

### ✅ الشفافية
- كل معاملة واضحة في Stripe Dashboard
- يمكن تتبع جميع المدفوعات

### ✅ المرونة
- يمكن تغيير نسبة العمولة لكل ACC
- يمكن إضافة/إزالة Stripe Account في أي وقت

---

## القيود والاعتبارات

### 1. متطلبات Stripe Connect

- ACC يجب أن يكون لديه حساب Stripe Connect نشط
- الحساب يجب أن يكون مكتمل البيانات (details_submitted = true)
- الحساب يجب أن يكون قادراً على استقبال المدفوعات (charges_enabled = true)

### 2. رسوم Stripe

- Stripe يفرض رسوم على كل معاملة
- الرسوم تُخصم من المبلغ قبل التقسيم
- يجب أخذ هذا في الاعتبار عند حساب العمولة

### 3. العملات المدعومة

- يعتمد على العملات المدعومة في Stripe Connect
- يجب التأكد من دعم العملة المطلوبة

### 4. التسويات

- في حالة Fallback (لا يوجد Stripe Account)، يتم التعامل مع العمولة يدوياً
- يمكن استخدام Monthly Settlements للتسوية الشهرية

---

## سيناريوهات الاستخدام

### السيناريو 1: ACC جديد

1. ACC يسجل في المنصة
2. Group Admin يوافق على ACC
3. ACC يقوم بإعداد حساب Stripe Connect
4. ACC يقدم معرف الحساب لـ Group Admin
5. Group Admin يتحقق من الحساب ويضيفه
6. ACC يبدأ في استقبال المدفوعات تلقائياً

### السيناريو 2: تحديث نسبة العمولة

1. Group Admin يقرر تغيير نسبة العمولة لـ ACC
2. Group Admin يحدث `commission_percentage` في قاعدة البيانات
3. جميع المدفوعات المستقبلية تستخدم النسبة الجديدة
4. المدفوعات السابقة لا تتأثر

### السيناريو 3: إزالة Stripe Account

1. Group Admin يقرر إزالة Stripe Account من ACC
2. Group Admin يستخدم DELETE endpoint
3. ACC لا يستطيع استقبال المدفوعات التلقائية
4. المدفوعات تذهب للمنصة (Fallback)
5. يتم التعامل مع العمولة يدوياً

---

## استكشاف الأخطاء

### المشكلة: ACC لا يستلم المدفوعات

**الأسباب المحتملة:**
- `stripe_account_id` غير موجود أو غير صحيح
- حساب Stripe Connect غير نشط
- `charges_enabled` = false في Stripe
- `details_submitted` = false في Stripe

**الحل:**
- التحقق من وجود `stripe_account_id` في قاعدة البيانات
- استخدام verify endpoint للتحقق من صحة الحساب
- التأكد من إكمال بيانات الحساب في Stripe Dashboard

### المشكلة: العمولة غير صحيحة

**الأسباب المحتملة:**
- `commission_percentage` غير مضبوط بشكل صحيح
- الحساب يتم قبل تحديث النسبة

**الحل:**
- التحقق من قيمة `commission_percentage` في قاعدة البيانات
- التأكد من استخدام النسبة الصحيحة في حساب العمولة

### المشكلة: خطأ في Payment Intent

**الأسباب المحتملة:**
- Stripe Account ID غير صحيح
- حساب Stripe غير متصل بالمنصة
- مشاكل في إعدادات Stripe Connect

**الحل:**
- التحقق من صحة Account ID
- استخدام verify endpoint
- مراجعة إعدادات Stripe Connect في Stripe Dashboard

---

## أفضل الممارسات

### 1. التحقق قبل الإضافة

- دائماً تحقق من صحة Stripe Account قبل إضافته
- استخدم verify endpoint للتأكد

### 2. مراقبة المدفوعات

- راقب المدفوعات في Stripe Dashboard
- تحقق من وصول الأموال للجهات الصحيحة

### 3. تحديث النسب

- راجع نسب العمولة بانتظام
- تأكد من دقتها قبل بدء المدفوعات

### 4. التوثيق

- وثّق جميع التغييرات في Stripe Accounts
- احتفظ بسجل للتغييرات

---

