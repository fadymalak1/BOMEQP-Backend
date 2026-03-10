<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\CertificatePricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CourseImport implements ToCollection, WithHeadingRow
{
    protected int $createdCount = 0;

    protected int $updatedCount = 0;

    protected array $errors = [];

    /**
     * @param  int  $accId
     * @param  int  $userId
     * @param  array<string,array{id:int}>  $subCategoryNameToMeta
     */
    public function __construct(
        protected int $accId,
        protected int $userId,
        protected array $subCategoryNameToMeta,
    ) {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $subCategoryName = trim((string) ($row['sub_category'] ?? ''));
                if ($subCategoryName === '') {
                    $this->errors[] = "Row {$rowNumber}: Sub category is required.";
                    continue;
                }

                $subMeta = $this->subCategoryNameToMeta[$subCategoryName] ?? null;
                if ($subMeta === null) {
                    $this->errors[] = "Row {$rowNumber}: Sub category '{$subCategoryName}' not found. Use exact name from dropdown.";
                    continue;
                }

                $subCategoryId = $subMeta['id'];

                $code = trim((string) ($row['code'] ?? ''));
                if ($code === '') {
                    $this->errors[] = "Row {$rowNumber}: Code is required.";
                    continue;
                }

                $description = trim((string) ($row['description'] ?? ''));
                $durationHours = (int) ($row['duration_hours'] ?? 0);
                if ($durationHours <= 0) {
                    $durationHours = 1;
                }

                $maxCapacity = (int) ($row['max_capacity'] ?? 0);
                if ($maxCapacity <= 0) {
                    $maxCapacity = 1;
                }

                $assessorRaw = strtolower(trim((string) ($row['assessor_required'] ?? '')));
                $assessorRequired = in_array($assessorRaw, ['yes', 'y', 'true', '1'], true);

                $levelRaw = trim((string) ($row['level'] ?? ''));
                $level = strtolower($levelRaw);
                if (!in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
                    $this->errors[] = "Row {$rowNumber}: Invalid level '{$levelRaw}'. Allowed: Beginner, Intermediate, Advanced.";
                    continue;
                }

                $statusRaw = trim((string) ($row['status'] ?? ''));
                $status = strtolower($statusRaw);
                if ($status === '') {
                    $status = 'active';
                }
                if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
                    $this->errors[] = "Row {$rowNumber}: Invalid status '{$statusRaw}'. Allowed: Active, Inactive, Archived.";
                    continue;
                }

                $basePriceRaw = trim((string) ($row['base_price'] ?? ''));
                $basePrice = $basePriceRaw === '' ? null : (float) $basePriceRaw;

                $currencyRaw = trim((string) ($row['currency'] ?? ''));
                $currency = $currencyRaw === '' ? null : strtoupper($currencyRaw);

                if ($basePrice !== null && $basePrice < 0) {
                    $this->errors[] = "Row {$rowNumber}: base_price cannot be negative.";
                    continue;
                }

                if ($basePrice !== null && ($currency === null || strlen($currency) !== 3)) {
                    $this->errors[] = "Row {$rowNumber}: currency (3-letter code) is required when base_price is provided.";
                    continue;
                }

                DB::beginTransaction();

                $existing = Course::where('acc_id', $this->accId)
                    ->where('code', $code)
                    ->first();

                $data = [
                    'sub_category_id' => $subCategoryId,
                    'acc_id' => $this->accId,
                    'name' => $name,
                    'name_ar' => null,
                    'code' => $code,
                    'description' => $description,
                    'duration_hours' => $durationHours,
                    'max_capacity' => $maxCapacity,
                    'assessor_required' => $assessorRequired,
                    'level' => $level,
                    'status' => $status,
                ];

                if ($existing) {
                    $existing->update($data);
                    $course = $existing;
                    $this->updatedCount++;
                } else {
                    $course = Course::create($data);
                    $this->createdCount++;
                }

                if ($basePrice !== null && $currency !== null) {
                    $this->createOrUpdatePricing($course->id, $basePrice, $currency);
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }
    }

    protected function createOrUpdatePricing(int $courseId, float $basePrice, string $currency): void
    {
        $existingPricing = CertificatePricing::where('course_id', $courseId)
            ->where('acc_id', $this->accId)
            ->latest('created_at')
            ->first();

        if ($existingPricing) {
            $existingPricing->update([
                'base_price' => $basePrice,
                'currency' => $currency,
            ]);
        } else {
            CertificatePricing::create([
                'acc_id' => $this->accId,
                'course_id' => $courseId,
                'base_price' => $basePrice,
                'currency' => $currency,
                'group_commission_percentage' => 0,
                'training_center_commission_percentage' => 0,
                'instructor_commission_percentage' => 0,
                'effective_from' => now()->format('Y-m-d'),
                'effective_to' => null,
            ]);
        }
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

