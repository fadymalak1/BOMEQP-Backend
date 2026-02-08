<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed in order to respect dependencies
        $this->call([
            AdminUserSeeder::class,
            UserSeeder::class,
            OrganizationAccountsSeeder::class,
            CategorySeeder::class,
            SubCategorySeeder::class,
            ACCSeeder::class,
            ACCSubscriptionSeeder::class,
            ACCDocumentSeeder::class,
            TrainingCenterSeeder::class,
            TrainingCenterWalletSeeder::class,
            InstructorSeeder::class,
            CourseSeeder::class,
            ClassModelSeeder::class,
            CertificateTemplateSeeder::class,
            CertificatePricingSeeder::class,
            TrainingCenterAccAuthorizationSeeder::class,
            InstructorAccAuthorizationSeeder::class,
            InstructorCourseAuthorizationSeeder::class,
            DiscountCodeSeeder::class,
            CodeBatchSeeder::class,
            CertificateCodeSeeder::class,
            CertificateSeeder::class,
            TransactionSeeder::class,
            CommissionLedgerSeeder::class,
            MonthlySettlementSeeder::class,
            ACCMaterialSeeder::class,
            TrainingCenterPurchaseSeeder::class,
            TrainingClassSeeder::class,
            ClassCompletionSeeder::class,
            StripeSettingSeeder::class,
        ]);
    }
}
