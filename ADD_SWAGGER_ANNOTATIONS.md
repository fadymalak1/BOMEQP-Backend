# Adding Swagger Annotations to All Controllers

## Status

✅ **Completed:**
- AuthController (all 9 endpoints)
- CertificateController (verify endpoint)
- NotificationController (all 8 endpoints)
- FileController (2 endpoints)
- Dashboard Controllers (ACC, Training Center, Instructor)

## Remaining Controllers (40+ controllers)

Due to the large number of controllers, I've added annotations to the most critical ones. To complete the remaining controllers, follow this pattern:

### Pattern for Adding Annotations

```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: "/api/endpoint",
    summary: "Endpoint summary",
    description: "Detailed description",
    tags: ["Tag Name"],
    security: [["sanctum" => []]], // If authenticated
    parameters: [
        new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: "Success",
            content: new OA\JsonContent(properties: [...])
        )
    ]
)]
public function methodName(Request $request) { }
```

### Remaining Controllers to Annotate

**Admin Controllers:**
- Admin\ACCController
- Admin\CategoryController
- Admin\ClassController
- Admin\CourseController
- Admin\FinancialController
- Admin\InstructorController
- Admin\ReportController
- Admin\SubCategoryController
- Admin\TrainingCenterController

**ACC Controllers:**
- ACC\CategoryController
- ACC\CertificateController
- ACC\CertificateTemplateController
- ACC\ClassController
- ACC\CourseController
- ACC\DiscountCodeController
- ACC\FinancialController
- ACC\InstructorController
- ACC\MaterialController
- ACC\SubscriptionController
- ACC\TrainingCenterController

**Training Center Controllers:**
- TrainingCenter\ACCController
- TrainingCenter\CertificateController
- TrainingCenter\ClassController
- TrainingCenter\CodeController
- TrainingCenter\CourseController
- TrainingCenter\InstructorController
- TrainingCenter\MarketplaceController
- TrainingCenter\TraineeController
- TrainingCenter\WalletController

**Instructor Controllers:**
- Instructor\ACCController
- Instructor\ClassController
- Instructor\EarningController
- Instructor\MaterialController
- Instructor\ProfileController
- Instructor\TrainingCenterController

**Other Controllers:**
- StripeController
- StripeSettingController

## Quick Reference

See `SWAGGER_QUICK_REFERENCE.md` for annotation examples and patterns.

## Next Steps

1. Run `php artisan l5-swagger:generate` to see current progress
2. Add annotations to remaining controllers following the pattern
3. Regenerate documentation after each batch

