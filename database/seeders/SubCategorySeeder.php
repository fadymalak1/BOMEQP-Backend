<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubCategorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'group_admin')->first();
        $categories = Category::all();

        $subCategories = [
            // Safety & Health
            [
                'category_id' => $categories->where('name', 'Safety & Health')->first()->id,
                'name' => 'Occupational Safety',
                'name_ar' => 'السلامة المهنية',
                'description' => 'Workplace safety and hazard prevention',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
            [
                'category_id' => $categories->where('name', 'Safety & Health')->first()->id,
                'name' => 'First Aid',
                'name_ar' => 'الإسعافات الأولية',
                'description' => 'First aid and emergency response',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
            // Technical Skills
            [
                'category_id' => $categories->where('name', 'Technical Skills')->first()->id,
                'name' => 'Electrical',
                'name_ar' => 'الكهرباء',
                'description' => 'Electrical systems and maintenance',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
            [
                'category_id' => $categories->where('name', 'Technical Skills')->first()->id,
                'name' => 'Mechanical',
                'name_ar' => 'الميكانيكا',
                'description' => 'Mechanical systems and repair',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
            // Management
            [
                'category_id' => $categories->where('name', 'Management')->first()->id,
                'name' => 'Project Management',
                'name_ar' => 'إدارة المشاريع',
                'description' => 'Project planning and execution',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
            [
                'category_id' => $categories->where('name', 'Management')->first()->id,
                'name' => 'Leadership',
                'name_ar' => 'القيادة',
                'description' => 'Leadership and team management',
                'status' => 'active',
                'created_by' => $admin->id,
            ],
        ];

        foreach ($subCategories as $subCategory) {
            SubCategory::create($subCategory);
        }
    }
}

