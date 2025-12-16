<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\Course;
use App\Models\SubCategory;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $accs = ACC::where('status', 'active')->get();
        $subCategories = SubCategory::all();

        $courseNames = [
            ['name' => 'Occupational Safety Fundamentals', 'name_ar' => 'أساسيات السلامة المهنية', 'code' => 'OSF-101'],
            ['name' => 'Advanced First Aid', 'name_ar' => 'الإسعافات الأولية المتقدمة', 'code' => 'AFA-201'],
            ['name' => 'Electrical Safety', 'name_ar' => 'السلامة الكهربائية', 'code' => 'ELS-301'],
            ['name' => 'Mechanical Systems Maintenance', 'name_ar' => 'صيانة الأنظمة الميكانيكية', 'code' => 'MSM-401'],
            ['name' => 'Project Management Professional', 'name_ar' => 'إدارة المشاريع الاحترافية', 'code' => 'PMP-501'],
            ['name' => 'Leadership Excellence', 'name_ar' => 'التميز في القيادة', 'code' => 'LEX-601'],
        ];

        foreach ($accs as $acc) {
            foreach ($courseNames as $index => $courseData) {
                $subCategory = $subCategories->random();
                
                Course::create([
                    'sub_category_id' => $subCategory->id,
                    'acc_id' => $acc->id,
                    'name' => $courseData['name'],
                    'name_ar' => $courseData['name_ar'],
                    'code' => $acc->id . '-' . $courseData['code'],
                    'description' => 'Comprehensive training course covering all aspects of ' . $courseData['name'],
                    'duration_hours' => rand(20, 80),
                    'level' => ['beginner', 'intermediate', 'advanced'][rand(0, 2)],
                    'status' => 'active',
                ]);
            }
        }
    }
}

