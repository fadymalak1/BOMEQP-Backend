<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/auth/register', [App\Http\Controllers\API\AuthController::class, 'register']);
Route::post('/auth/login', [App\Http\Controllers\API\AuthController::class, 'login']);
Route::post('/auth/forgot-password', [App\Http\Controllers\API\AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [App\Http\Controllers\API\AuthController::class, 'resetPassword']);
Route::get('/auth/verify-email/{token}', [App\Http\Controllers\API\AuthController::class, 'verifyEmail']);
Route::get('/certificates/verify/{code}', [App\Http\Controllers\API\CertificateController::class, 'verify']);

// Countries and Cities (Public endpoints for dropdowns)
Route::get('/countries', [App\Http\Controllers\API\CountryController::class, 'index']);
Route::get('/cities', [App\Http\Controllers\API\CityController::class, 'index']);

// Stripe webhook (public, but verified by signature)
Route::post('/stripe/webhook', [App\Http\Controllers\API\StripeController::class, 'handleWebhook']);

// Public file access routes (specific routes first)
Route::get('/storage/instructors/cv/{filename}', [App\Http\Controllers\API\FileController::class, 'instructorCv']);
Route::get('/storage/instructors/certificates/{filename}', [App\Http\Controllers\API\FileController::class, 'instructorCertificate']);
// General storage route (must be last to avoid conflicts)
Route::get('/storage/{path}', [App\Http\Controllers\API\FileController::class, 'serveFile'])->where('path', '.+');

    // Protected routes
    Route::middleware(['auth:sanctum'])->group(function () {
        // Auth routes
        Route::post('/auth/logout', [App\Http\Controllers\API\AuthController::class, 'logout']);
        Route::get('/auth/profile', [App\Http\Controllers\API\AuthController::class, 'profile']);
        Route::put('/auth/profile', [App\Http\Controllers\API\AuthController::class, 'updateProfile']);
        Route::put('/auth/change-password', [App\Http\Controllers\API\AuthController::class, 'changePassword']);

        // Stripe Payment routes (available to all authenticated users)
        Route::prefix('stripe')->group(function () {
            Route::get('/config', [App\Http\Controllers\API\StripeController::class, 'getConfig']);
            Route::post('/payment-intent', [App\Http\Controllers\API\StripeController::class, 'createPaymentIntent']);
            Route::post('/confirm', [App\Http\Controllers\API\StripeController::class, 'confirmPayment']);
            Route::post('/refund', [App\Http\Controllers\API\StripeController::class, 'refund']);
        });

        // Notifications routes (available to all authenticated users)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [App\Http\Controllers\API\NotificationController::class, 'index']);
            Route::get('/unread-count', [App\Http\Controllers\API\NotificationController::class, 'unreadCount']);
            Route::post('/mark-all-read', [App\Http\Controllers\API\NotificationController::class, 'markAllAsRead']);
            Route::delete('/read', [App\Http\Controllers\API\NotificationController::class, 'deleteRead']);
            Route::get('/{id}', [App\Http\Controllers\API\NotificationController::class, 'show']);
            Route::put('/{id}/read', [App\Http\Controllers\API\NotificationController::class, 'markAsRead']);
            Route::put('/{id}/unread', [App\Http\Controllers\API\NotificationController::class, 'markAsUnread']);
            Route::delete('/{id}', [App\Http\Controllers\API\NotificationController::class, 'destroy']);
        });

        // Discount codes by ACC ID (available to all authenticated users - training centers need this)
        Route::get('/acc/{id}/discount-codes', [App\Http\Controllers\API\ACC\DiscountCodeController::class, 'getByAccId'])->where('id', '[0-9]+');

    // Shared routes for both group_admin and acc_admin
    Route::prefix('admin')->middleware(['role:group_admin,acc_admin'])->group(function () {
        // Sub-categories (read access for ACC admins)
        Route::get('/sub-categories', [App\Http\Controllers\API\Admin\SubCategoryController::class, 'index']);
        Route::get('/sub-categories/{id}', [App\Http\Controllers\API\Admin\SubCategoryController::class, 'show']);
    });

    // Group Admin routes
    Route::prefix('admin')->middleware(['role:group_admin'])->group(function () {
        // Dashboard
        Route::get('/dashboard', [App\Http\Controllers\API\Admin\DashboardController::class, 'index']);
        
        // ACC Management
        Route::get('/accs/applications', [App\Http\Controllers\API\Admin\ACCController::class, 'applications']);
        Route::get('/accs/applications/{id}', [App\Http\Controllers\API\Admin\ACCController::class, 'showApplication']);
        Route::put('/accs/applications/{id}/approve', [App\Http\Controllers\API\Admin\ACCController::class, 'approve']);
        Route::put('/accs/applications/{id}/reject', [App\Http\Controllers\API\Admin\ACCController::class, 'reject']);
        Route::post('/accs/{id}/create-space', [App\Http\Controllers\API\Admin\ACCController::class, 'createSpace']);
        Route::post('/accs/{id}/generate-credentials', [App\Http\Controllers\API\Admin\ACCController::class, 'generateCredentials']);
        Route::get('/accs', [App\Http\Controllers\API\Admin\ACCController::class, 'index']);
        Route::put('/accs/{id}/commission-percentage', [App\Http\Controllers\API\Admin\ACCController::class, 'setCommissionPercentage']);
        Route::get('/accs/{id}', [App\Http\Controllers\API\Admin\ACCController::class, 'show']);
        Route::get('/accs/{id}/transactions', [App\Http\Controllers\API\Admin\ACCController::class, 'transactions']);

        // Categories & Courses (full CRUD only for group_admin)
        Route::apiResource('categories', App\Http\Controllers\API\Admin\CategoryController::class);
        Route::post('/sub-categories', [App\Http\Controllers\API\Admin\SubCategoryController::class, 'store']);
        Route::put('/sub-categories/{id}', [App\Http\Controllers\API\Admin\SubCategoryController::class, 'update']);
        Route::delete('/sub-categories/{id}', [App\Http\Controllers\API\Admin\SubCategoryController::class, 'destroy']);
        
        // Assign category to ACC
        Route::get('/accs/{id}/categories', [App\Http\Controllers\API\Admin\ACCController::class, 'getAssignedCategories']);
        Route::post('/accs/{id}/assign-category', [App\Http\Controllers\API\Admin\ACCController::class, 'assignCategory']);
        Route::delete('/accs/{id}/remove-category', [App\Http\Controllers\API\Admin\ACCController::class, 'removeCategory']);
        
        // Update ACC data
        Route::put('/accs/{id}', [App\Http\Controllers\API\Admin\ACCController::class, 'update']);
        
        // Training Centers Management
        Route::get('/training-centers/applications', [App\Http\Controllers\API\Admin\TrainingCenterController::class, 'applications']);
        Route::put('/training-centers/applications/{id}/approve', [App\Http\Controllers\API\Admin\TrainingCenterController::class, 'approve']);
        Route::put('/training-centers/applications/{id}/reject', [App\Http\Controllers\API\Admin\TrainingCenterController::class, 'reject']);
        Route::get('/training-centers', [App\Http\Controllers\API\Admin\TrainingCenterController::class, 'index']);
        Route::get('/training-centers/{id}', [App\Http\Controllers\API\Admin\TrainingCenterController::class, 'show']);
        Route::put('/training-centers/{id}', [App\Http\Controllers\API\Admin\TrainingCenterController::class, 'update']);
        Route::apiResource('classes', App\Http\Controllers\API\Admin\ClassController::class);

        // Financial & Reporting
        Route::get('/financial/dashboard', [App\Http\Controllers\API\Admin\FinancialController::class, 'dashboard']);
        Route::get('/financial/transactions', [App\Http\Controllers\API\Admin\FinancialController::class, 'transactions']);
        Route::get('/financial/settlements', [App\Http\Controllers\API\Admin\FinancialController::class, 'settlements']);
        Route::post('/financial/settlements/{id}/request-payment', [App\Http\Controllers\API\Admin\FinancialController::class, 'requestPayment']);
        Route::get('/reports/revenue', [App\Http\Controllers\API\Admin\ReportController::class, 'revenue']);
        Route::get('/reports/accs', [App\Http\Controllers\API\Admin\ReportController::class, 'accs']);
        Route::get('/reports/training-centers', [App\Http\Controllers\API\Admin\ReportController::class, 'trainingCenters']);
        Route::get('/reports/certificates', [App\Http\Controllers\API\Admin\ReportController::class, 'certificates']);

        // Stripe Settings Management
        Route::prefix('stripe-settings')->group(function () {
            Route::get('/', [App\Http\Controllers\API\StripeSettingController::class, 'index']);
            Route::get('/active', [App\Http\Controllers\API\StripeSettingController::class, 'getActive']);
            Route::post('/', [App\Http\Controllers\API\StripeSettingController::class, 'store']);
            Route::put('/{id}', [App\Http\Controllers\API\StripeSettingController::class, 'update']);
            Route::delete('/{id}', [App\Http\Controllers\API\StripeSettingController::class, 'destroy']);
        });

        // Instructors Management
        Route::get('/instructors', [App\Http\Controllers\API\Admin\InstructorController::class, 'index']);
        Route::get('/instructors/{id}', [App\Http\Controllers\API\Admin\InstructorController::class, 'show']);
        Route::put('/instructors/{id}', [App\Http\Controllers\API\Admin\InstructorController::class, 'update']);
        
        // Instructor Authorization Commission Management
        Route::get('/instructor-authorizations/pending-commission', [App\Http\Controllers\API\Admin\InstructorController::class, 'pendingCommissionRequests']);
        Route::put('/instructor-authorizations/{id}/set-commission', [App\Http\Controllers\API\Admin\InstructorController::class, 'setInstructorCommission']);

        // Courses Management
        Route::get('/courses', [App\Http\Controllers\API\Admin\CourseController::class, 'index']);
        Route::get('/courses/{id}', [App\Http\Controllers\API\Admin\CourseController::class, 'show']);

        // Code Batches - Manual Payment Management
        Route::get('/code-batches/pending-payments', [App\Http\Controllers\API\Admin\CodeController::class, 'pendingPayments']);
        Route::put('/code-batches/{id}/approve-payment', [App\Http\Controllers\API\Admin\CodeController::class, 'approvePayment']);
        Route::put('/code-batches/{id}/reject-payment', [App\Http\Controllers\API\Admin\CodeController::class, 'rejectPayment']);
    });

    // ACC routes
    Route::prefix('acc')->middleware(['role:acc_admin'])->group(function () {
        // Profile Management
        Route::get('/profile', [App\Http\Controllers\API\ACC\ProfileController::class, 'show']);
        Route::post('/profile', [App\Http\Controllers\API\ACC\ProfileController::class, 'update']); // POST for file uploads
        Route::put('/profile', [App\Http\Controllers\API\ACC\ProfileController::class, 'update']); // PUT for backward compatibility
        Route::post('/profile/verify-stripe-account', [App\Http\Controllers\API\ACC\ProfileController::class, 'verifyStripeAccount']);
        
        Route::get('/dashboard', [App\Http\Controllers\API\ACC\DashboardController::class, 'index']);
        Route::get('/subscription', [App\Http\Controllers\API\ACC\SubscriptionController::class, 'show']);
        // Payment intent endpoints must come before payment endpoints
        Route::post('/subscription/payment-intent', [App\Http\Controllers\API\ACC\SubscriptionController::class, 'createPaymentIntent']);
        Route::post('/subscription/renew-payment-intent', [App\Http\Controllers\API\ACC\SubscriptionController::class, 'createRenewalPaymentIntent']);
        Route::post('/subscription/payment', [App\Http\Controllers\API\ACC\SubscriptionController::class, 'payment']);
        Route::put('/subscription/renew', [App\Http\Controllers\API\ACC\SubscriptionController::class, 'renew']);

        // Training Centers
        Route::get('/training-centers/requests', [App\Http\Controllers\API\ACC\TrainingCenterController::class, 'requests']);
        Route::put('/training-centers/requests/{id}/approve', [App\Http\Controllers\API\ACC\TrainingCenterController::class, 'approve']);
        Route::put('/training-centers/requests/{id}/reject', [App\Http\Controllers\API\ACC\TrainingCenterController::class, 'reject']);
        Route::put('/training-centers/requests/{id}/return', [App\Http\Controllers\API\ACC\TrainingCenterController::class, 'return']);
        Route::get('/training-centers', [App\Http\Controllers\API\ACC\TrainingCenterController::class, 'index']);

        // Instructors
        Route::get('/instructors/requests', [App\Http\Controllers\API\ACC\InstructorController::class, 'requests']);
        Route::put('/instructors/requests/{id}/approve', [App\Http\Controllers\API\ACC\InstructorController::class, 'approve']);
        Route::put('/instructors/requests/{id}/reject', [App\Http\Controllers\API\ACC\InstructorController::class, 'reject']);
        Route::put('/instructors/requests/{id}/return', [App\Http\Controllers\API\ACC\InstructorController::class, 'return']);
        Route::get('/instructors', [App\Http\Controllers\API\ACC\InstructorController::class, 'index']);

        // Courses
        Route::apiResource('courses', App\Http\Controllers\API\ACC\CourseController::class);
        Route::post('/courses/{id}/pricing', [App\Http\Controllers\API\ACC\CourseController::class, 'setPricing']);
        Route::put('/courses/{id}/pricing', [App\Http\Controllers\API\ACC\CourseController::class, 'updatePricing']);

        // Certificate Templates
        Route::apiResource('certificate-templates', App\Http\Controllers\API\ACC\CertificateTemplateController::class);
        Route::post('/certificate-templates/{id}/preview', [App\Http\Controllers\API\ACC\CertificateTemplateController::class, 'preview']);

        // Discount Codes
        // Specific routes must come before apiResource to avoid route conflicts
        Route::post('/discount-codes/validate', [App\Http\Controllers\API\ACC\DiscountCodeController::class, 'validate']);
        // Route to get discount codes by ACC ID (must be before apiResource to avoid conflicts)
        Route::get('/{id}/discount-codes', [App\Http\Controllers\API\ACC\DiscountCodeController::class, 'getByAccId'])->where('id', '[0-9]+');
        Route::apiResource('discount-codes', App\Http\Controllers\API\ACC\DiscountCodeController::class);

        // Materials
        Route::apiResource('materials', App\Http\Controllers\API\ACC\MaterialController::class);

        // Certificates & Classes
        Route::get('/certificates', [App\Http\Controllers\API\ACC\CertificateController::class, 'index']);
        Route::get('/classes', [App\Http\Controllers\API\ACC\ClassController::class, 'index']);
        Route::get('/classes/{id}', [App\Http\Controllers\API\ACC\ClassController::class, 'show']);

        // Financial
        Route::get('/financial/transactions', [App\Http\Controllers\API\ACC\FinancialController::class, 'transactions']);
        Route::get('/financial/settlements', [App\Http\Controllers\API\ACC\FinancialController::class, 'settlements']);

        // Code Batches - Manual Payment Management
        Route::get('/code-batches/pending-payments', [App\Http\Controllers\API\ACC\CodeController::class, 'pendingPayments']);
        Route::put('/code-batches/{id}/approve-payment', [App\Http\Controllers\API\ACC\CodeController::class, 'approvePayment']);
        Route::put('/code-batches/{id}/reject-payment', [App\Http\Controllers\API\ACC\CodeController::class, 'rejectPayment']);

        // Categories Management (ACC can create their own categories)
        Route::get('/categories', [App\Http\Controllers\API\ACC\CategoryController::class, 'index']);
        Route::get('/categories/{id}', [App\Http\Controllers\API\ACC\CategoryController::class, 'show']);
        Route::post('/categories', [App\Http\Controllers\API\ACC\CategoryController::class, 'store']);
        Route::put('/categories/{id}', [App\Http\Controllers\API\ACC\CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [App\Http\Controllers\API\ACC\CategoryController::class, 'destroy']);
        
        // Sub Categories Management
        Route::get('/sub-categories', [App\Http\Controllers\API\ACC\CategoryController::class, 'indexSubCategories']);
        Route::get('/sub-categories/{id}', [App\Http\Controllers\API\ACC\CategoryController::class, 'showSubCategory']);
        Route::post('/sub-categories', [App\Http\Controllers\API\ACC\CategoryController::class, 'storeSubCategory']);
        Route::put('/sub-categories/{id}', [App\Http\Controllers\API\ACC\CategoryController::class, 'updateSubCategory']);
        Route::delete('/sub-categories/{id}', [App\Http\Controllers\API\ACC\CategoryController::class, 'destroySubCategory']);
    });

    // Training Center routes
    Route::prefix('training-center')->middleware(['role:training_center_admin'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\API\TrainingCenter\DashboardController::class, 'index']);
        
        // Profile
        Route::get('/profile', [App\Http\Controllers\API\TrainingCenter\ProfileController::class, 'show']);
        Route::post('/profile', [App\Http\Controllers\API\TrainingCenter\ProfileController::class, 'update']); // POST for file uploads
        Route::put('/profile', [App\Http\Controllers\API\TrainingCenter\ProfileController::class, 'update']); // PUT for backward compatibility
        
        Route::get('/accs', [App\Http\Controllers\API\TrainingCenter\ACCController::class, 'index']);
        Route::post('/accs/{id}/request-authorization', [App\Http\Controllers\API\TrainingCenter\ACCController::class, 'requestAuthorization']);
        Route::get('/authorizations', [App\Http\Controllers\API\TrainingCenter\ACCController::class, 'authorizations']);
        
        // ACC Courses and Sub-categories for instructor authorization
        Route::get('/accs/{accId}/courses', [App\Http\Controllers\API\TrainingCenter\InstructorController::class, 'getAccCourses']);
        Route::get('/accs/{accId}/sub-categories', [App\Http\Controllers\API\TrainingCenter\InstructorController::class, 'getAccSubCategories']);

        // Instructors
        // Specific routes must come before apiResource to avoid route conflicts
        Route::get('/instructors/authorizations', [App\Http\Controllers\API\TrainingCenter\InstructorController::class, 'authorizations']);
        Route::post('/instructors/authorizations/{id}/payment-intent', [App\Http\Controllers\API\TrainingCenter\InstructorController::class, 'createAuthorizationPaymentIntent']);
        Route::post('/instructors/authorizations/{id}/pay', [App\Http\Controllers\API\TrainingCenter\InstructorController::class, 'payAuthorization']);
        Route::post('/instructors/{id}/request-authorization', [App\Http\Controllers\API\TrainingCenter\InstructorController::class, 'requestAuthorization']);
        // POST route for updates (supports file uploads with multipart/form-data)
        Route::post('/instructors/{id}', [App\Http\Controllers\API\TrainingCenter\InstructorController::class, 'update']);
        // Standard RESTful routes (PUT still works but POST is recommended for file uploads)
        Route::apiResource('instructors', App\Http\Controllers\API\TrainingCenter\InstructorController::class);

        // Trainees
        // POST route for updates (supports file uploads with multipart/form-data)
        Route::post('/trainees/{id}', [App\Http\Controllers\API\TrainingCenter\TraineeController::class, 'update']);
        // Standard RESTful routes (PUT still works but POST is recommended for file uploads)
        Route::apiResource('trainees', App\Http\Controllers\API\TrainingCenter\TraineeController::class);

        // Certificate Codes
        // Certificate Codes - Payment intent must come before purchase
        Route::post('/codes/create-payment-intent', [App\Http\Controllers\API\TrainingCenter\CodeController::class, 'createPaymentIntent']);
        Route::post('/codes/payment-intent', [App\Http\Controllers\API\TrainingCenter\CodeController::class, 'createPaymentIntent']); // Alias for backward compatibility
        Route::post('/codes/purchase', [App\Http\Controllers\API\TrainingCenter\CodeController::class, 'purchase']);
        Route::get('/codes/inventory', [App\Http\Controllers\API\TrainingCenter\CodeController::class, 'inventory']);
        Route::get('/codes/batches', [App\Http\Controllers\API\TrainingCenter\CodeController::class, 'batches']);

        // Financial
        Route::get('/financial/transactions', [App\Http\Controllers\API\TrainingCenter\WalletController::class, 'transactions']);

        // Courses (from approved ACCs)
        Route::get('/courses', [App\Http\Controllers\API\TrainingCenter\CourseController::class, 'index']);
        Route::get('/courses/{id}', [App\Http\Controllers\API\TrainingCenter\CourseController::class, 'show']);

        // Discount Codes (view available discount codes for an ACC)
        Route::get('/accs/{id}/discount-codes', [App\Http\Controllers\API\ACC\DiscountCodeController::class, 'getByAccId']);

        // Classes
        Route::apiResource('classes', App\Http\Controllers\API\TrainingCenter\ClassController::class);
        Route::put('/classes/{id}/complete', [App\Http\Controllers\API\TrainingCenter\ClassController::class, 'complete']);

        // Certificates
        Route::post('/certificates/generate', [App\Http\Controllers\API\TrainingCenter\CertificateController::class, 'generate']);
        Route::get('/certificates', [App\Http\Controllers\API\TrainingCenter\CertificateController::class, 'index']);
        Route::get('/certificates/{id}', [App\Http\Controllers\API\TrainingCenter\CertificateController::class, 'show']);

        // Marketplace
        Route::get('/marketplace/materials', [App\Http\Controllers\API\TrainingCenter\MarketplaceController::class, 'materials']);
        Route::get('/marketplace/materials/{id}', [App\Http\Controllers\API\TrainingCenter\MarketplaceController::class, 'showMaterial']);
        Route::post('/marketplace/purchase', [App\Http\Controllers\API\TrainingCenter\MarketplaceController::class, 'purchase']);
        Route::get('/library', [App\Http\Controllers\API\TrainingCenter\MarketplaceController::class, 'library']);
    });

    // Instructor routes
    Route::prefix('instructor')->middleware(['role:instructor'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\API\Instructor\DashboardController::class, 'index']);
        
        // Profile
        Route::get('/profile', [App\Http\Controllers\API\Instructor\ProfileController::class, 'show']);
        Route::post('/profile', [App\Http\Controllers\API\Instructor\ProfileController::class, 'update']); // POST for file uploads
        Route::put('/profile', [App\Http\Controllers\API\Instructor\ProfileController::class, 'update']); // PUT for backward compatibility
        
        // Classes
        Route::get('/classes', [App\Http\Controllers\API\Instructor\ClassController::class, 'index']);
        Route::get('/classes/{id}', [App\Http\Controllers\API\Instructor\ClassController::class, 'show']);
        Route::put('/classes/{id}/mark-complete', [App\Http\Controllers\API\Instructor\ClassController::class, 'markComplete']);
        
        // Training Centers
        Route::get('/training-centers', [App\Http\Controllers\API\Instructor\TrainingCenterController::class, 'index']);
        
        // ACCs
        Route::get('/accs', [App\Http\Controllers\API\Instructor\ACCController::class, 'index']);
        
        // Materials
        Route::get('/materials', [App\Http\Controllers\API\Instructor\MaterialController::class, 'index']);
        
        // Earnings
        Route::get('/earnings', [App\Http\Controllers\API\Instructor\EarningController::class, 'index']);
    });
});

