<?php

namespace App\Imports;

use App\Models\Category;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CategoryImport implements ToCollection, WithHeadingRow
{
    protected int $createdCount = 0;

    protected int $updatedCount = 0;

    protected array $errors = [];

    public function __construct(
        protected int $createdBy
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // 1-based + header row

            try {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $existing = Category::where('name', $name)->first();

                $data = [
                    'name' => $name,
                    'name_ar' => null,
                    'description' => trim((string) ($row['description'] ?? '')),
                    'icon_url' => null,
                    'status' => 'active',
                    'created_by' => $this->createdBy,
                ];

                if ($existing) {
                    $existing->update($data);
                    $this->updatedCount++;
                } else {
                    Category::create($data);
                    $this->createdCount++;
                }
            } catch (\Throwable $e) {
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
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
