<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\SubCategory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SubCategoryImport implements ToCollection, WithHeadingRow
{
    protected int $createdCount = 0;

    protected int $updatedCount = 0;

    protected array $errors = [];

    protected ?array $categoryNameToId = null;

    public function __construct(
        protected int $createdBy
    ) {}

    public function collection(Collection $rows): void
    {
        $this->loadCategoryMap();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $categoryName = trim((string) ($row['category'] ?? $row['category_name'] ?? ''));
                if ($categoryName === '') {
                    $this->errors[] = "Row {$rowNumber}: Category is required.";
                    continue;
                }

                $categoryId = $this->categoryNameToId[$categoryName] ?? null;
                if ($categoryId === null) {
                    $this->errors[] = "Row {$rowNumber}: Category '{$categoryName}' not found. Use exact name from dropdown.";
                    continue;
                }

                $existing = SubCategory::where('category_id', $categoryId)->where('name', $name)->first();

                $data = [
                    'category_id' => $categoryId,
                    'name' => $name,
                    'name_ar' => trim((string) ($row['name_ar'] ?? '')),
                    'description' => trim((string) ($row['description'] ?? '')),
                    'status' => $this->normalizeStatus($row['status'] ?? 'active'),
                    'created_by' => $this->createdBy,
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->updatedCount++;
                } else {
                    SubCategory::create($data);
                    $this->createdCount++;
                }
            } catch (\Throwable $e) {
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }
    }

    protected function loadCategoryMap(): void
    {
        if ($this->categoryNameToId !== null) {
            return;
        }
        $this->categoryNameToId = Category::pluck('id', 'name')->toArray();
    }

    protected function normalizeStatus(mixed $value): string
    {
        $s = strtolower(trim((string) $value));
        return in_array($s, ['active', 'inactive'], true) ? $s : 'active';
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
