<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\Category;
use App\Models\CertificateTemplate;
use Illuminate\Database\Seeder;

class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $accs = ACC::where('status', 'active')->get();
        $categories = Category::all();

        foreach ($accs as $acc) {
            foreach ($categories as $category) {
                CertificateTemplate::create([
                    'acc_id' => $acc->id,
                    'category_id' => $category->id,
                    'name' => $category->name . ' Certificate Template',
                    'template_html' => '<div class="certificate"><h1>Certificate of Completion</h1><p>This certifies that {{trainee_name}} has successfully completed {{course_name}}</p></div>',
                    'template_variables' => ['trainee_name', 'course_name', 'issue_date', 'certificate_number'],
                    'background_image_url' => 'https://example.com/templates/certificate-bg.png',
                    'logo_positions' => ['top_center' => ['x' => 50, 'y' => 10]],
                    'signature_positions' => ['bottom_left' => ['x' => 10, 'y' => 90], 'bottom_right' => ['x' => 90, 'y' => 90]],
                    'status' => 'active',
                ]);
            }
        }
    }
}

