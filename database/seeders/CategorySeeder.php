<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'group_admin')->first();

        $categories = [
            [
                'name' => 'Safety & Health',
                'name_ar' => 'السلامة والصحة',
                'description' => 'Safety and health training courses',
                'icon_url' => 'https://example.com/icons/safety.png',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Technical Skills',
                'name_ar' => 'المهارات التقنية',
                'description' => 'Technical and vocational training courses',
                'icon_url' => 'https://example.com/icons/technical.png',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Management',
                'name_ar' => 'الإدارة',
                'description' => 'Management and leadership courses',
                'icon_url' => 'https://example.com/icons/management.png',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Quality Assurance',
                'name_ar' => 'ضمان الجودة',
                'description' => 'Quality assurance and control courses',
                'icon_url' => 'https://example.com/icons/quality.png',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Environmental',
                'name_ar' => 'البيئة',
                'description' => 'Environmental management courses',
                'icon_url' => 'https://example.com/icons/environmental.png',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}

