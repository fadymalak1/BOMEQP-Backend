# إعداد Cron Job للتحقق من انتهاء صلاحية الشهادات

## نظرة عامة

يتم التحقق من انتهاء صلاحية الشهادات تلقائياً يومياً عبر Cron Job. عندما ينتهي تاريخ صلاحية الشهادة (`expiry_date`)، يتم تحديث حالة الشهادة تلقائياً من `valid` إلى `expired`.

## كيفية عمل النظام

### 1. Artisan Command

تم إنشاء أمر Artisan جديد: `certificates:check-expired`

**الوظيفة:**
- يبحث عن جميع الشهادات التي:
  - حالتها `valid`
  - لديها `expiry_date` غير null
  - `expiry_date` في الماضي (أقل من تاريخ اليوم)
- يقوم بتحديث حالة هذه الشهادات إلى `expired`

### 2. Schedule (Laravel Scheduler)

تم إضافة الأمر إلى Laravel Scheduler في `routes/console.php`:
```php
Schedule::command('certificates:check-expired')->daily();
```

### 3. التحقق التلقائي في API

عند التحقق من الشهادة عبر endpoint `/certificates/verify/{code}`:
- يتم التحقق من `expiry_date` تلقائياً
- إذا انتهت الصلاحية، يتم تحديث الحالة إلى `expired` فوراً
- يتم إرجاع رسالة خطأ تشير إلى انتهاء الصلاحية

## إعداد Cron Job في السيرفر

### الطريقة 1: استخدام Laravel Scheduler (الموصى بها)

Laravel يوفر طريقة أفضل من cron مباشرة وهي استخدام Laravel Scheduler.

#### الخطوة 1: إضافة Cron Job واحد فقط

أضف هذا السطر في crontab الخاص بك:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

هذا الـ cron job سيعمل كل دقيقة وLaravel scheduler سيتولى تشغيل الأوامر المبرمجة (يومياً في هذه الحالة).

### الطريقة 2: تشغيل الأمر مباشرة (للـ Production)

#### على cPanel:

1. **اذهب إلى Cron Jobs في cPanel**
2. **أضف Cron Job جديد:**
   - **Minute:** `0` (في منتصف الليل)
   - **Hour:** `0`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`
   - **Command:**
     ```bash
     cd /home/username/public_html && /usr/local/bin/php artisan certificates:check-expired >> /dev/null 2>&1
     ```

#### على Linux Server (SSH):

1. **افتح crontab:**
   ```bash
   crontab -e
   ```

2. **أضف السطر التالي:**
   ```bash
   # Check expired certificates daily at midnight
   0 0 * * * cd /var/www/your-project && php artisan certificates:check-expired >> /dev/null 2>&1
   ```

   أو إذا كنت تستخدم Laravel Scheduler (الموصى بها):
   ```bash
   # Laravel Scheduler - runs every minute
   * * * * * cd /var/www/your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

### الطريقة 3: استخدام Supervisor (للـ Production)

Supervisor يحافظ على Worker يعمل بشكل مستمر ويعيد تشغيله تلقائياً إذا توقف.

#### التثبيت (Ubuntu/Debian):

```bash
sudo apt-get install supervisor
```

#### إنشاء ملف الإعداد:

أنشئ ملف `/etc/supervisor/conf.d/laravel-scheduler.conf`:

```ini
[program:laravel-scheduler]
process_name=%(program_name)s
command=php /path-to-your-project/artisan schedule:work
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path-to-your-project/storage/logs/scheduler.log
stopwaitsecs=3600
```

#### تشغيل Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-scheduler
```

### الطريقة 4: استخدام systemd (للـ Linux Servers)

أنشئ ملف `/etc/systemd/system/laravel-scheduler.service`:

```ini
[Unit]
Description=Laravel Scheduler
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path-to-your-project/artisan schedule:work

[Install]
WantedBy=multi-user.target
```

ثم:

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-scheduler
sudo systemctl start laravel-scheduler
```

## للتحقق من أن Cron Job يعمل

### 1. تحقق من الـ Logs:

```bash
tail -f storage/logs/laravel.log
```

ابحث عن رسائل مثل:
```
Certificate expiration check completed
Updated X certificate(s) to expired status
```

### 2. تشغيل الأمر يدوياً للاختبار:

```bash
php artisan certificates:check-expired
```

يجب أن ترى رسالة مثل:
```
Checking for expired certificates...
Completed. Updated X certificate(s) to expired status.
```

### 3. التحقق من الشهادات المنتهية:

```sql
SELECT * FROM certificates WHERE status = 'expired' AND expiry_date < CURDATE();
```

## أوقات التشغيل الموصى بها

- **يومياً في منتصف الليل (00:00)**: الأفضل للتحقق من الشهادات المنتهية
- **يومياً في الساعة 2 صباحاً**: بديل جيد لتجنب أوقات الذروة
- **كل 6 ساعات**: إذا كنت تريد تحديثات أكثر تكراراً

## ملاحظات مهمة

1. **تأكد من أن `QUEUE_CONNECTION` في ملف `.env` مضبوط بشكل صحيح**
2. **تأكد من أن مسار المشروع صحيح في cron command**
3. **للـ Production، استخدم Laravel Scheduler بدلاً من cron مباشر**
4. **راقب الـ logs بانتظام للتحقق من الأخطاء**
5. **الشهادات التي لا تحتوي على `expiry_date` لن تتأثر بهذا الأمر**

## حل المشاكل الشائعة

### المشكلة: الـ Cron Job لا يعمل

**الحل:**
- تحقق من أن مسار المشروع صحيح
- تحقق من أن مسار PHP صحيح (`which php`)
- تحقق من صلاحيات الملفات
- تحقق من الـ logs في `storage/logs/laravel.log`

### المشكلة: الشهادات لا يتم تحديثها

**الحل:**
- تأكد من أن `expiry_date` موجود وليس null
- تأكد من أن `status` الحالي هو `valid`
- شغّل الأمر يدوياً للتحقق: `php artisan certificates:check-expired`
- تحقق من الـ logs للأخطاء

### المشكلة: Cron Job يعمل لكن لا يحدث شيء

**الحل:**
- تحقق من أن هناك شهادات منتهية فعلاً
- تحقق من أن `expiry_date` في الماضي
- تحقق من أن `status` هو `valid` وليس `expired` أو `revoked`

## الأمان والأداء

1. **الأمان:**
   - الأمر يتحقق فقط من الشهادات التي حالتها `valid`
   - لا يمكن تحديث الشهادات الملغاة (`revoked`)
   - يتم تسجيل جميع العمليات في الـ logs

2. **الأداء:**
   - الأمر فعال ويستخدم استعلام واحد فقط
   - يعمل على الشهادات التي تحتاج فقط للتحديث
   - لا يؤثر على الأداء العام للنظام

## API Integration

عند استخدام endpoint التحقق `/certificates/verify/{code}`:
- يتم التحقق من `expiry_date` تلقائياً
- إذا انتهت الصلاحية، يتم تحديث الحالة فوراً
- لا حاجة لانتظار cron job

## الخلاصة

- يتم التحقق من انتهاء صلاحية الشهادات تلقائياً يومياً
- يمكن إعداد cron job باستخدام Laravel Scheduler (الموصى بها)
- الأمر: `certificates:check-expired`
- يتم تحديث الشهادات المنتهية تلقائياً إلى حالة `expired`
- التحقق التلقائي متاح أيضاً في API endpoints

