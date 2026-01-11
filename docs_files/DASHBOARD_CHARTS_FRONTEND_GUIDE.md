# Dashboard Charts - Frontend Developer Guide

## Overview
تم إضافة بيانات الرسوم البيانية (Charts) إلى جميع Dashboards في النظام. هذا الدليل يشرح التغييرات التي تمت وماذا يجب على المطور Frontend عمله لإضافة الرسوم البيانية إلى Dashboards.

---

## What Changed

تم إضافة حقل جديد باسم `charts` إلى استجابة جميع Dashboard APIs. هذا الحقل يحتوي على بيانات الرسوم البيانية الجاهزة للاستخدام مباشرة في مكتبات الرسوم البيانية مثل Chart.js, Recharts, أو أي مكتبة أخرى تفضلها.

---

## Dashboard Changes Summary

### 1. Group Admin Dashboard
- ✅ تم إضافة بيانات Revenue Chart (الإيرادات على مدار آخر 6 أشهر)
- ✅ تم إضافة بيانات Entity Distribution Chart (توزيع الكيانات)

### 2. Training Center Dashboard
- ✅ تم إضافة بيانات Classes Over Time Chart (عدد الصفوف على مدار آخر 6 أشهر)
- ✅ تم إضافة بيانات Classes Status Distribution Chart (توزيع الصفوف حسب الحالة)

### 3. ACC Admin Dashboard
- ✅ تم إضافة بيانات Revenue Chart (الإيرادات على مدار آخر 6 أشهر)
- ✅ تم إضافة بيانات Certificates Chart (عدد الشهادات على مدار آخر 6 أشهر)

### 4. Instructor Dashboard
- ✅ تم إضافة بيانات Earnings Chart (الأرباح على مدار آخر 6 أشهر)
- ✅ تم إضافة بيانات Classes Status Distribution Chart (توزيع الصفوف حسب الحالة)

---

## Group Admin Dashboard

### API Endpoint
`GET /admin/dashboard`

### New Data Structure
يحتوي Dashboard الآن على حقل جديد `charts` يحتوي على:

#### Revenue Over Time Chart
بيانات الإيرادات على مدار آخر 6 أشهر. كل عنصر يحتوي على:
- `month`: الشهر بصيغة YYYY-MM (مثل: "2024-01")
- `month_name`: اسم الشهر بصيغة "Jan 2024"
- `revenue`: قيمة الإيرادات للشهر (رقم عشري)

**نوع الرسم البياني المناسب**: Line Chart أو Bar Chart

**الاستخدام المقترح**: عرض الإيرادات على مدار آخر 6 أشهر لمعرفة الاتجاه العام للإيرادات

#### Entity Distribution Chart
توزيع الكيانات في النظام. كل عنصر يحتوي على:
- `label`: اسم الكيان (Accreditation Bodies, Training Centers, Instructors, Trainees)
- `value`: عدد الكيان

**نوع الرسم البياني المناسب**: Pie Chart أو Doughnut Chart

**الاستخدام المقترح**: عرض توزيع الكيانات المختلفة في النظام

---

## Training Center Dashboard

### API Endpoint
`GET /training-center/dashboard`

### New Data Structure
يحتوي Dashboard الآن على حقل جديد `charts` يحتوي على:

#### Classes Over Time Chart
بيانات عدد الصفوف على مدار آخر 6 أشهر. كل عنصر يحتوي على:
- `month`: الشهر بصيغة YYYY-MM
- `month_name`: اسم الشهر بصيغة "Jan 2024"
- `count`: عدد الصفوف المُنشأة في هذا الشهر

**نوع الرسم البياني المناسب**: Line Chart أو Bar Chart

**الاستخدام المقترح**: عرض عدد الصفوف المُنشأة على مدار آخر 6 أشهر لمعرفة النمو

#### Classes Status Distribution Chart
توزيع الصفوف حسب الحالة. كل عنصر يحتوي على:
- `label`: حالة الصف (Scheduled, In Progress, Completed, Cancelled)
- `value`: عدد الصفوف بهذه الحالة

**نوع الرسم البياني المناسب**: Pie Chart أو Doughnut Chart

**الاستخدام المقترح**: عرض توزيع الصفوف حسب الحالة لفهم حالة الصفوف بشكل سريع

---

## ACC Admin Dashboard

### API Endpoint
`GET /acc/dashboard`

### New Data Structure
يحتوي Dashboard الآن على حقل جديد `charts` يحتوي على:

#### Revenue Over Time Chart
بيانات الإيرادات على مدار آخر 6 أشهر. كل عنصر يحتوي على:
- `month`: الشهر بصيغة YYYY-MM
- `month_name`: اسم الشهر بصيغة "Jan 2024"
- `revenue`: قيمة الإيرادات للشهر (رقم عشري)

**نوع الرسم البياني المناسب**: Line Chart أو Bar Chart

**الاستخدام المقترح**: عرض الإيرادات على مدار آخر 6 أشهر

#### Certificates Over Time Chart
بيانات عدد الشهادات المُنشأة على مدار آخر 6 أشهر. كل عنصر يحتوي على:
- `month`: الشهر بصيغة YYYY-MM
- `month_name`: اسم الشهر بصيغة "Jan 2024"
- `count`: عدد الشهادات المُنشأة في هذا الشهر

**نوع الرسم البياني المناسب**: Line Chart أو Bar Chart

**الاستخدام المقترح**: عرض عدد الشهادات المُنشأة على مدار آخر 6 أشهر لمعرفة النمو

---

## Instructor Dashboard

### API Endpoint
`GET /instructor/dashboard`

### New Data Structure
يحتوي Dashboard الآن على حقل جديد `charts` يحتوي على:

#### Earnings Over Time Chart
بيانات الأرباح على مدار آخر 6 أشهر. كل عنصر يحتوي على:
- `month`: الشهر بصيغة YYYY-MM
- `month_name`: اسم الشهر بصيغة "Jan 2024"
- `earnings`: قيمة الأرباح للشهر (رقم عشري)

**نوع الرسم البياني المناسب**: Line Chart أو Bar Chart

**الاستخدام المقترح**: عرض الأرباح على مدار آخر 6 أشهر لمعرفة الاتجاه العام

#### Classes Status Distribution Chart
توزيع الصفوف حسب الحالة. كل عنصر يحتوي على:
- `label`: حالة الصف (Total, Upcoming, In Progress, Completed)
- `value`: عدد الصفوف بهذه الحالة

**نوع الرسم البياني المناسب**: Pie Chart أو Doughnut Chart

**الاستخدام المقترح**: عرض توزيع الصفوف حسب الحالة

---

## What You Need to Do

### 1. Update Type Definitions (TypeScript)
إذا كنت تستخدم TypeScript، قم بتحديث type definitions للـ Dashboard responses لإضافة حقل `charts`.

### 2. Update Dashboard Components
قم بتحديث مكونات Dashboard في التطبيق لإضافة الرسوم البيانية. يمكنك استخدام أي مكتبة رسوم بيانية تفضلها مثل:
- Chart.js
- Recharts
- ApexCharts
- Victory Charts
- أو أي مكتبة أخرى

### 3. Design Considerations
عند تصميم الرسوم البيانية:

- **الألوان**: استخدم ألوان متناسقة مع تصميم التطبيق
- **الحجم**: تأكد من أن الرسوم البيانية responsive وتعمل على جميع أحجام الشاشات
- **التفاعل**: أضف hover effects أو tooltips لعرض القيم التفصيلية
- **الموضع**: ضع الرسوم البيانية في أماكن منطقية في Dashboard (مثل: أسفل cards الإحصائيات)

### 4. Chart Types Recommendations

#### Line Charts / Bar Charts
- استخدم Line Chart للإيرادات والأرباح (لإظهار الاتجاه)
- استخدم Bar Chart لعدد الصفوف والشهادات (للمقارنة بين الأشهر)

#### Pie Charts / Doughnut Charts
- استخدم Pie Chart أو Doughnut Chart للتوزيعات (Status Distribution, Entity Distribution)
- أضف legends لعرض التسميات
- أضف values على القطاعات أو في tooltip

### 5. Data Formatting
البيانات جاهزة للاستخدام مباشرة، لكن قد تحتاج إلى:
- تنسيق الأرقام (إضافة فاصلات للآلاف)
- تنسيق العملة (إضافة رمز العملة)
- تنسيق التواريخ (إذا كنت تريد عرض تنسيق مختلف)

---

## Implementation Steps

### Step 1: Check API Response
أولاً، تحقق من أن API يعيد حقل `charts` في الاستجابة.

### Step 2: Add Chart Library
قم بتثبيت مكتبة الرسوم البيانية التي تفضلها إذا لم تكن مثبتة بالفعل.

### Step 3: Create Chart Components
أنشئ مكونات منفصلة للرسوم البيانية (مثل: `RevenueChart`, `StatusDistributionChart`, إلخ)

### Step 4: Integrate Charts into Dashboards
قم بدمج مكونات الرسوم البيانية في صفحات Dashboard المناسبة.

### Step 5: Test
اختبر الرسوم البيانية على جميع Dashboards وتأكد من:
- عرض البيانات بشكل صحيح
- الرسوم البيانية responsive
- الأداء جيد

---

## Example Dashboard Layout Suggestions

### Group Admin Dashboard
- Revenue Over Time Chart: ضعها أسفل revenue cards
- Entity Distribution Chart: ضعها في جانب أو أسفل statistics cards

### Training Center Dashboard
- Classes Over Time Chart: ضعها أسفل classes card
- Classes Status Distribution Chart: ضعها بجانب Classes Over Time Chart

### ACC Admin Dashboard
- Revenue Over Time Chart: ضعها أسفل revenue cards
- Certificates Over Time Chart: ضعها أسفل certificates card

### Instructor Dashboard
- Earnings Over Time Chart: ضعها أسفل earnings cards
- Classes Status Distribution Chart: ضعها بجانب statistics cards

---

## Notes

- **البيانات محدثة تلقائياً**: البيانات في الرسوم البيانية يتم حسابها من قاعدة البيانات في الوقت الفعلي، لذا ستكون دائماً محدثة
- **آخر 6 أشهر**: جميع الرسوم البيانية الزمنية (Over Time) تعرض بيانات آخر 6 أشهر
- **التوافق مع الكود الموجود**: التغييرات متوافقة مع الكود الموجود ولا تؤثر على البيانات الأخرى في Dashboard
- **الأداء**: البيانات محسوبة بكفاءة ولن تؤثر على أداء Dashboard

---

## Questions or Issues

إذا كان لديك أي استفسارات أو مشاكل في التكامل مع الرسوم البيانية، يرجى التواصل مع فريق Backend.

