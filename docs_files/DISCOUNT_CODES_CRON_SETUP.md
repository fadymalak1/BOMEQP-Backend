# إعداد Cron Job للتحقق من حالة أكواد الخصم

## الخطوات:

### 1. الكود جاهز بالفعل ✅

- Command موجود: `app/Console/Commands/CheckDiscountCodesStatus.php`
- Schedule موجود في: `routes/console.php`
- Command يعمل يومياً تلقائياً

### 2. إضافة Cron Job في cPanel

#### الطريقة (الموصى بها - Laravel Scheduler):

1. **افتح cPanel**
2. **اذهب إلى "Cron Jobs"**
3. **اختر "Standard (cPanel v1.0)"**
4. **أضف Cron Job جديد:**

   **الإعدادات:**
   - **Minute:** `*`
   - **Hour:** `*`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`

   **Command:**
   ```bash
   cd /home/username/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
   
   **ملاحظة:** استبدل `/home/username/public_html` بمسار مشروعك الفعلي على السيرفر

5. **احفظ**

هذا الـ cron job سيعمل **كل دقيقة** وLaravel Scheduler سيتولى تشغيل الأوامر المبرمجة (بما في ذلك `discount-codes:check-status` الذي يعمل يومياً).

---

#### الطريقة البديلة (تشغيل Command مباشرة):

إذا أردت تشغيل Command مباشرة بدون Laravel Scheduler:

1. **افتح cPanel**
2. **اذهب إلى "Cron Jobs"**
3. **اختر "Standard (cPanel v1.0)"**
4. **أضف Cron Job جديد:**

   **الإعدادات:**
   - **Minute:** `0` (في بداية كل ساعة)
   - **Hour:** `*` (كل ساعة)
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`

   **أو للتشغيل يومياً مرة واحدة:**

   - **Minute:** `0`
   - **Hour:** `0` (الساعة 12 صباحاً)
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`

   **Command:**
   ```bash
   cd /home/username/public_html && /usr/local/bin/php artisan discount-codes:check-status >> /dev/null 2>&1
   ```

5. **احفظ**

---

## كيفية معرفة مسار المشروع:

### في cPanel:

1. اذهب إلى **File Manager**
2. ابحث عن ملف `artisan` في مجلد المشروع
3. المسار الكامل يظهر في أعلى الصفحة

مثال: `/home/username/public_html/artisan` يعني المسار هو `/home/username/public_html`

### كيفية معرفة مسار PHP:

في معظم خوادم cPanel، المسار يكون:
- `/usr/local/bin/php` أو
- `/usr/bin/php` أو
- `php` فقط (إذا كان في PATH)

للتأكد، يمكنك إنشاء cron job مؤقت لاختبار:
```bash
cd /home/username/public_html && /usr/local/bin/php -v
```

---

## الاختبار:

### 1. تشغيل Command يدوياً:

اتصل بـ SSH (إن أمكن) أو استخدم Terminal في cPanel:

```bash
cd /path-to-your-project
php artisan discount-codes:check-status
```

### 2. التحقق من الـ Logs:

راجع ملف `storage/logs/laravel.log` للتحقق من أن Command يعمل:

```bash
tail -f storage/logs/laravel.log | grep "discount"
```

### 3. التحقق من قاعدة البيانات:

راجع جدول `discount_codes` للتحقق من تحديث الحالات:

```sql
SELECT id, code, status, end_date, used_quantity, total_quantity 
FROM discount_codes 
WHERE status IN ('expired', 'depleted')
ORDER BY updated_at DESC;
```

---

## ملاحظات مهمة:

1. **Laravel Scheduler أفضل**: استخدم `schedule:run` لأنه يدير جميع الأوامر المبرمجة (بما في ذلك `subscriptions:check-expired` و `discount-codes:check-status`)

2. **مسار PHP**: تأكد من استخدام المسار الصحيح لـ PHP (`/usr/local/bin/php` عادة)

3. **مسار المشروع**: استبدل `/home/username/public_html` بمسار مشروعك الفعلي

4. **التوقيت**: Command مضبوط ليعمل **يومياً** تلقائياً عند استخدام Laravel Scheduler

5. **الـ Logs**: جميع التغييرات تُسجل في `storage/logs/laravel.log`

---

## مثال كامل للـ Cron Job في cPanel:

```
* * * * * cd /home/username/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

هذا سيعمل كل دقيقة ويشغل Laravel Scheduler، والذي بدوره سيشغل الأوامر المبرمجة.

