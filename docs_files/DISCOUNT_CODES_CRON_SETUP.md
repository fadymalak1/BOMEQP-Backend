# إعداد Cron Job للتحقق من حالة أكواد الخصم

## الخطوات:

### 1. الكود جاهز بالفعل ✅

- Command موجود: `app/Console/Commands/CheckDiscountCodesStatus.php`
- Schedule موجود في: `routes/console.php`
- Command يعمل يومياً تلقائياً

### 2. إضافة Cron Job في cPanel

#### الطريقة (تشغيل Command مباشرة - الأكثر موثوقية):

1. **افتح cPanel**
2. **اذهب إلى "Cron Jobs"**
3. **اختر "Standard (cPanel v1.0)"**
4. **أضف Cron Job جديد:**

   **الإعدادات (للتشغيل يومياً مرة واحدة في الساعة 12 صباحاً):**
   - **Minute:** `0`
   - **Hour:** `0`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`

   **أو للتشغيل كل ساعة:**
   - **Minute:** `0`
   - **Hour:** `*`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`

   **Command (استخدم مسارك الفعلي):**
   ```bash
   cd /home/y2klh31gr5ug/public_html/v1 && /usr/local/bin/php artisan discount-codes:check-status >> /dev/null 2>&1
   ```
   
   **ملاحظة:** 
   - استبدل `/home/y2klh31gr5ug/public_html/v1` بمسار مشروعك الفعلي
   - إذا `/usr/local/bin/php` لا يعمل، جرب `/usr/bin/php` أو `php` فقط

5. **احفظ**

هذا الـ cron job سيعمل **يومياً** ويحدث حالة أكواد الخصم تلقائياً.

---

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

1. **مسار PHP**: تأكد من استخدام المسار الصحيح لـ PHP. جرّب:
   - `/usr/local/bin/php` (الأكثر شيوعاً في cPanel)
   - `/usr/bin/php`
   - `php` فقط (إذا كان في PATH)

2. **مسار المشروع**: استبدل `/home/y2klh31gr5ug/public_html/v1` بمسار مشروعك الفعلي

3. **التوقيت**: Command يعمل حسب التوقيت الذي تحدده (يومياً، كل ساعة، إلخ)

4. **الـ Logs**: جميع التغييرات تُسجل في `storage/logs/laravel.log`

5. **للتأكد من مسار PHP**: أنشئ cron job مؤقت للتجربة:
   ```bash
   cd /home/y2klh31gr5ug/public_html/v1 && /usr/local/bin/php -v
   ```
   إذا ظهرت إصدار PHP، المسار صحيح.

---

## مثال كامل للـ Cron Job في cPanel (للتشغيل يومياً):

```
0 0 * * * cd /home/y2klh31gr5ug/public_html/v1 && /usr/local/bin/php artisan discount-codes:check-status >> /dev/null 2>&1
```

## مثال للتشغيل كل ساعة:

```
0 * * * * cd /home/y2klh31gr5ug/public_html/v1 && /usr/local/bin/php artisan discount-codes:check-status >> /dev/null 2>&1
```

