# File Upload Troubleshooting Guide

## حل مشكلة خطأ 503 Service Unavailable عند رفع الملفات

### المشكلة / Problem
عند محاولة رفع ملف CV أو شهادة، يظهر خطأ 503 Service Unavailable.

When trying to upload a CV file or certificate, a 503 Service Unavailable error appears.

---

## الأسباب المحتملة / Possible Causes

### 1. حجم الملف كبير جداً / File Size Too Large
- الملف أكبر من الحد المسموح به في إعدادات PHP
- الحد الأقصى: 10MB

### 2. إعدادات PHP غير كافية / PHP Settings Insufficient
- `upload_max_filesize` صغير جداً
- `post_max_size` صغير جداً
- `max_execution_time` صغير جداً
- `memory_limit` صغير جداً

### 3. مشكلة في صلاحيات الملفات / File Permissions Issue
- مجلد التخزين غير قابل للكتابة
- صلاحيات المجلد غير صحيحة

### 4. مشكلة في مساحة التخزين / Storage Space Issue
- مساحة التخزين غير كافية
- القرص ممتلئ

### 5. مشكلة في إعدادات الخادم / Server Configuration Issue
- Nginx/Apache timeout صغير
- PHP-FPM timeout صغير

---

## الحلول / Solutions

### الحل 1: التحقق من إعدادات PHP / Check PHP Settings

**للتحقق من الإعدادات الحالية:**
```bash
php -i | grep upload_max_filesize
php -i | grep post_max_size
php -i | grep max_execution_time
php -i | grep memory_limit
```

**الإعدادات المطلوبة:**
- `upload_max_filesize = 10M`
- `post_max_size = 12M` (يجب أن يكون أكبر من upload_max_filesize)
- `max_execution_time = 300` (5 دقائق)
- `max_input_time = 300`
- `memory_limit = 256M`

**تحديث إعدادات PHP:**

**في php.ini:**
```ini
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

**في .htaccess (تم إضافتها بالفعل):**
```apache
<IfModule mod_php.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 12M
    php_value max_execution_time 300
    php_value max_input_time 300
    php_value memory_limit 256M
</IfModule>
```

---

### الحل 2: التحقق من صلاحيات المجلدات / Check Directory Permissions

**التحقق من صلاحيات مجلد التخزين:**
```bash
ls -la storage/app/public/instructors/
```

**الصلاحيات المطلوبة:**
- المجلد: `755` أو `775`
- الملفات: `644` أو `664`

**تحديث الصلاحيات:**
```bash
chmod -R 755 storage/app/public/instructors/
chown -R www-data:www-data storage/app/public/instructors/
```

**للـ Windows (Laragon):**
- تأكد من أن المجلد `storage/app/public/instructors/` موجود
- تأكد من أن المستخدم لديه صلاحيات الكتابة

---

### الحل 3: التحقق من مساحة التخزين / Check Storage Space

**التحقق من المساحة المتاحة:**
```bash
df -h
```

**للـ Windows:**
- تحقق من مساحة القرص C:\
- تأكد من وجود مساحة كافية (على الأقل 100MB)

---

### الحل 4: إعدادات Nginx (إذا كان مستخدماً) / Nginx Settings

**في ملف إعدادات Nginx:**
```nginx
client_max_body_size 12M;
client_body_timeout 300s;
fastcgi_read_timeout 300s;
```

---

### الحل 5: إعدادات PHP-FPM (إذا كان مستخدماً) / PHP-FPM Settings

**في ملف php-fpm.conf:**
```ini
request_terminate_timeout = 300s
```

**في ملف pool configuration:**
```ini
php_admin_value[upload_max_filesize] = 10M
php_admin_value[post_max_size] = 12M
php_admin_value[max_execution_time] = 300
php_admin_value[memory_limit] = 256M
```

---

### الحل 6: إعدادات Apache (إذا كان مستخدماً) / Apache Settings

**في ملف httpd.conf أو apache2.conf:**
```apache
Timeout 300
```

---

## التحقق من الإصلاحات / Verifying Fixes

### 1. التحقق من إعدادات PHP
بعد تحديث الإعدادات، أعد تشغيل الخادم:
```bash
# Apache
sudo service apache2 restart

# Nginx + PHP-FPM
sudo service nginx restart
sudo service php-fpm restart

# Laragon (Windows)
# أعد تشغيل Laragon
```

### 2. اختبار رفع ملف صغير أولاً
- ابدأ بملف صغير (مثلاً 1MB)
- إذا نجح، جرب ملف أكبر تدريجياً

### 3. التحقق من السجلات
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Apache error log
tail -f /var/log/apache2/error.log

# Nginx error log
tail -f /var/log/nginx/error.log

# PHP-FPM error log
tail -f /var/log/php-fpm/error.log
```

---

## الإصلاحات المطبقة في الكود / Code Fixes Applied

### 1. التحقق من حجم الملف قبل الرفع
- يتم التحقق من حجم الملف قبل معالجته
- رسالة خطأ واضحة إذا كان الملف كبيراً جداً

### 2. التحقق من مساحة التخزين
- يتم التحقق من المساحة المتاحة قبل الرفع
- رسالة خطأ إذا كانت المساحة غير كافية

### 3. التحقق من صلاحيات المجلد
- يتم التحقق من أن المجلد قابل للكتابة
- رسالة خطأ واضحة إذا كانت الصلاحيات غير صحيحة

### 4. معالجة أفضل للأخطاء
- معالجة PostTooLargeException
- رسائل خطأ واضحة مع error codes
- تسجيل تفصيلي للأخطاء

### 5. زيادة Timeout
- يتم تعيين execution time limit إلى 300 ثانية
- يتم تعيين memory limit إلى 256M

---

## رسائل الخطأ الجديدة / New Error Messages

### 1. File Too Large
```json
{
  "message": "CV file size exceeds the maximum allowed size of 10MB",
  "error": "File too large",
  "error_code": "file_too_large",
  "file_size": 15728640,
  "max_size": 10485760
}
```

### 2. Insufficient Storage Space
```json
{
  "message": "Insufficient disk space to upload file",
  "error": "Disk space insufficient",
  "error_code": "insufficient_space"
}
```

### 3. Directory Not Writable
```json
{
  "message": "Storage directory is not writable. Please contact administrator.",
  "error": "Directory not writable",
  "error_code": "directory_not_writable"
}
```

### 4. Request Too Large
```json
{
  "message": "Request size exceeds maximum allowed size of 12MB",
  "error": "Request too large",
  "error_code": "request_too_large",
  "content_length": 15728640
}
```

---

## خطوات التحقق السريع / Quick Verification Steps

1. **تحقق من حجم الملف:**
   - يجب أن يكون أقل من 10MB

2. **تحقق من إعدادات PHP:**
   - `upload_max_filesize >= 10M`
   - `post_max_size >= 12M`

3. **تحقق من الصلاحيات:**
   - `storage/app/public/instructors/` قابل للكتابة

4. **تحقق من المساحة:**
   - مساحة كافية على القرص

5. **تحقق من السجلات:**
   - راجع `storage/logs/laravel.log` للأخطاء

---

## للخوادم المشتركة / For Shared Hosting

إذا كنت تستخدم shared hosting:

1. **تحقق من cPanel:**
   - اذهب إلى PHP Settings في cPanel
   - قم بتحديث الإعدادات المذكورة أعلاه

2. **تحقق من .htaccess:**
   - تأكد من أن الإعدادات موجودة في `.htaccess`
   - بعض الخوادم لا تسمح بتعديل إعدادات PHP من .htaccess

3. **اتصل بالدعم الفني:**
   - إذا لم تستطع تعديل الإعدادات، اتصل بالدعم الفني
   - اطلب منهم زيادة الحدود المذكورة أعلاه

---

## ملاحظات إضافية / Additional Notes

1. **حجم الملف الموصى به:**
   - CV: أقل من 5MB (لأداء أفضل)
   - Certificates: أقل من 5MB لكل ملف

2. **عدد الملفات:**
   - يمكن رفع ملف CV واحد
   - يمكن رفع عدة ملفات شهادات في نفس الطلب

3. **نوع الملف:**
   - PDF فقط
   - أي نوع آخر سيتم رفضه

---

## الدعم / Support

إذا استمرت المشكلة بعد تطبيق جميع الحلول:
1. راجع السجلات (`storage/logs/laravel.log`)
2. تحقق من إعدادات الخادم
3. اتصل بالدعم الفني للخادم

---

**آخر تحديث / Last Updated:** 2026-01-03  
**الإصدار / Version:** 1.0.0

