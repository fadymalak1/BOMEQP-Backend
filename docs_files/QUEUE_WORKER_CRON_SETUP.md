# إعداد Laravel Queue Worker على السيرفر باستخدام Cron Job

## الطريقة 1: استخدام Laravel Scheduler (الموصى بها)

Laravel يوفر طريقة أفضل من cron مباشرة وهي استخدام Laravel Scheduler.

### الخطوة 1: إضافة Schedule Command

في ملف `routes/console.php` أو `app/Console/Kernel.php`، أضف:

```php
Schedule::command('queue:work --stop-when-empty')->everyMinute();
```

أو للحفاظ على Worker يعمل بشكل مستمر (بدلاً من cron):

```php
// هذا سيعمل بشكل مستمر
Schedule::command('queue:work --daemon')->everyMinute();
```

### الخطوة 2: إضافة Cron Job واحد فقط

أضف هذا السطر في crontab الخاص بك:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

هذا الـ cron job سيعمل كل دقيقة وLaravel scheduler سيتولى تشغيل الأوامر المبرمجة.

## الطريقة 2: تشغيل Queue Worker مباشرة (للـ Production)

### على cPanel:

1. **اذهب إلى Cron Jobs في cPanel**
2. **أضف Cron Job جديد:**
   - **Minute:** `*` (كل دقيقة)
   - **Hour:** `*`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`
   - **Command:**
     ```bash
     cd /home/username/public_html && /usr/local/bin/php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
     ```

### على Linux Server (SSH):

1. **افتح crontab:**
   ```bash
   crontab -e
   ```

2. **أضف السطر التالي:**
   ```bash
   * * * * * cd /var/www/your-project && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
   ```

### خيارات الأمر `queue:work`:

- `--stop-when-empty`: يتوقف بعد معالجة جميع الـ jobs المتاحة
- `--tries=3`: عدد المحاولات قبل فشل الـ job
- `--timeout=60`: timeout بالثواني (افتراضي 60)
- `--memory=128`: الحد الأقصى للذاكرة بالميجابايت

## الطريقة 3: استخدام Supervisor (الأفضل للإنتاج)

Supervisor يحافظ على Worker يعمل بشكل مستمر ويعيد تشغيله تلقائياً إذا توقف.

### التثبيت (Ubuntu/Debian):

```bash
sudo apt-get install supervisor
```

### إنشاء ملف الإعداد:

أنشئ ملف `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path-to-your-project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path-to-your-project/storage/logs/worker.log
stopwaitsecs=3600
```

### تشغيل Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

## الطريقة 4: استخدام systemd (للـ Linux Servers)

أنشئ ملف `/etc/systemd/system/laravel-worker.service`:

```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path-to-your-project/artisan queue:work --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
```

ثم:

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-worker
sudo systemctl start laravel-worker
```

## للتحقق من أن Queue Worker يعمل:

### 1. تحقق من الـ Jobs في قاعدة البيانات:

```sql
SELECT * FROM jobs;
```

### 2. تحقق من الـ Logs:

```bash
tail -f storage/logs/laravel.log
```

### 3. تحقق من Failed Jobs:

```sql
SELECT * FROM failed_jobs;
```

أو:

```bash
php artisan queue:failed
```

## ملاحظات مهمة:

1. **تأكد من أن `QUEUE_CONNECTION=database` في ملف `.env`**
2. **تأكد من تشغيل migrations لإنشاء جداول `jobs` و `failed_jobs`**
3. **للـ Production، استخدم Supervisor أو systemd بدلاً من cron**
4. **راقب الـ logs بانتظام للتحقق من الأخطاء**

## حل المشاكل الشائعة:

### المشكلة: الـ Jobs لا تعمل

**الحل:**
- تحقق من أن `QUEUE_CONNECTION` في `.env` مضبوط على `database`
- تحقق من وجود الـ jobs في جدول `jobs`
- تحقق من الـ logs في `storage/logs/laravel.log`

### المشكلة: Worker يتوقف

**الحل:**
- استخدم Supervisor أو systemd للحفاظ على Worker يعمل
- أضف `--max-time=3600` لتجنب memory leaks

### المشكلة: Jobs تفشل

**الحل:**
- تحقق من `failed_jobs` table
- راجع الـ exception في `failed_jobs`
- تأكد من أن جميع الـ dependencies متوفرة

## للـ cPanel (الطريقة البسيطة):

1. اذهب إلى **Cron Jobs** في cPanel
2. اختر **Standard (cPanel v1.0)**
3. أضف:
   ```
   * * * * * cd /home/username/public_html && /usr/local/bin/php artisan queue:work --stop-when-empty --tries=3
   ```
4. احفظ

**ملاحظة:** استبدل `/home/username/public_html` بمسار مشروعك الفعلي.

