<?php

namespace App\Exports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubCategoryTemplateExport implements FromArray, WithHeadings, WithEvents
{
    protected array $categoryNames = [];

    protected string $format = 'xlsx';

    /**
     * @param  string|null  $format  'xlsx' or 'csv'
     * @param  array<int>|null  $accessibleCategoryIds  When set (e.g. for acc_admin), only these categories appear in the dropdown. When null (e.g. group_admin), all categories.
     */
    public function __construct(?string $format = 'xlsx', ?array $accessibleCategoryIds = null)
    {
        $this->format = strtolower($format ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';
        if ($accessibleCategoryIds === null) {
            $this->categoryNames = Category::orderBy('name')->pluck('name')->toArray();
        } else {
            $this->categoryNames = $accessibleCategoryIds === []
                ? []
                : Category::whereIn('id', $accessibleCategoryIds)->orderBy('name')->pluck('name')->toArray();
        }
    }

    public function array(): array
    {
        $firstCategory = $this->categoryNames[0] ?? '';

        return [[$firstCategory, '', '']];
    }

    public function headings(): array
    {
        return ['category', 'name', 'description'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if ($this->format === 'csv' || empty($this->categoryNames)) {
                    return;
                }
                $this->addCategoryDropdown($event->sheet->getDelegate());
            },
        ];
    }

    protected function addCategoryDropdown(Worksheet $sheet): void
    {
        $hasComma = collect($this->categoryNames)->contains(fn ($n) => str_contains((string) $n, ','));
        $listLength = strlen(implode(',', $this->categoryNames));

        if (!$hasComma && $listLength <= 250) {
            $this->addDropdownViaFormula($sheet);
        } else {
            $this->addDropdownViaHiddenSheet($sheet);
        }
    }

    protected function addDropdownViaFormula(Worksheet $sheet): void
    {
        $list = implode(',', array_map(fn ($n) => str_replace('"', '""', (string) $n), $this->categoryNames));
        $validation = $sheet->getCell('A2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"' . $list . '"');

        for ($row = 2; $row <= 500; $row++) {
            $sheet->getCell('A' . $row)->setDataValidation(clone $validation);
        }
    }

    protected function addDropdownViaHiddenSheet(Worksheet $sheet): void
    {
        $workbook = $sheet->getParent();
        $categoriesSheet = new Worksheet($workbook, 'Categories');
        $workbook->addSheet($categoriesSheet, 0);
        $categoriesSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        foreach ($this->categoryNames as $i => $name) {
            $categoriesSheet->setCellValue('A' . ($i + 1), $name);
        }
        $lastRow = count($this->categoryNames) ?: 1;
        $formula = 'Categories!$A$1:$A$' . $lastRow;

        $workbook->setActiveSheetIndex(1);

        $validation = $sheet->getCell('A2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula);

        for ($row = 2; $row <= 500; $row++) {
            $sheet->getCell('A' . $row)->setDataValidation(clone $validation);
        }
    }
}
