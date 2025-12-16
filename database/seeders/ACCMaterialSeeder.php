<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\ACCMaterial;
use App\Models\Course;
use Illuminate\Database\Seeder;

class ACCMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $accs = ACC::where('status', 'active')->get();

        foreach ($accs as $acc) {
            $courses = Course::where('acc_id', $acc->id)->get();

            foreach ($courses as $course) {
                $materialTypes = ['pdf', 'video', 'presentation', 'package'];

                foreach ($materialTypes as $type) {
                    ACCMaterial::create([
                        'acc_id' => $acc->id,
                        'course_id' => $course->id,
                        'material_type' => $type,
                        'name' => ucfirst($type) . ' for ' . $course->name,
                        'description' => 'Educational material for ' . $course->name,
                        'price' => rand(10, 100),
                        'file_url' => 'https://example.com/materials/' . $course->id . '/' . $type . '.pdf',
                        'preview_url' => 'https://example.com/materials/' . $course->id . '/' . $type . '-preview.pdf',
                        'status' => 'active',
                    ]);
                }
            }
        }
    }
}

