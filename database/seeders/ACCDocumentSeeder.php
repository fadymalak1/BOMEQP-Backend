<?php

namespace Database\Seeders;

use App\Models\ACC;
use App\Models\ACCDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

class ACCDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'group_admin')->first();
        $accs = ACC::all();

        foreach ($accs as $acc) {
            $documentTypes = ['license', 'registration', 'certificate', 'other'];

            foreach ($documentTypes as $type) {
                ACCDocument::create([
                    'acc_id' => $acc->id,
                    'document_type' => $type,
                    'document_url' => 'https://example.com/documents/' . $acc->id . '/' . $type . '.pdf',
                    'uploaded_at' => now()->subDays(rand(30, 180)),
                    'verified' => $acc->status === 'active',
                    'verified_by' => $acc->status === 'active' ? $admin->id : null,
                    'verified_at' => $acc->status === 'active' ? now()->subDays(rand(1, 30)) : null,
                ]);
            }
        }
    }
}

