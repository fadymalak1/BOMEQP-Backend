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

    public function __construct()
    {
        $this->categoryNames = Category::orderBy('name')->pluck('name')->toArray();
    }

    public function array(): array
    {
        $firstCategory = $this->categoryNames[0] ?? '';

        return [
            [$firstCategory, '', ''],
        ];
    }

    public function headings(): array
    {
        return ['category', 'name', 'description'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                if (empty($this->categoryNames)) {
                    return;
                }

                $this->addCategoryDropdown($sheet);
            },
        ];
    }

    protected function addCategoryDropdown(Worksheet $sheet): void
    {
        $workbook = $sheet->getParent();

        // Use formula-based list when possible (most reliable for dropdown display)
        $hasCommaInNames = collect($this->categoryNames)->contains(fn ($n) => str_contains($n, ','));
        $categoryList = implode(',', array_map(fn ($n) => str_replace('"', '""', $n), $this->categoryNames));

        if (!$hasCommaInNames && strlen($categoryList) <= 250) {
            $this->addDropdownViaFormula($sheet, $categoryList);
            return;
        }

        // Fallback: hidden sheet for long lists or names with commas
        $this->addDropdownViaHiddenSheet($workbook, $sheet);
    }

    protected function addDropdownViaFormula(Worksheet $sheet, string $categoryList): void
    {
        $validation = $sheet->getCell('A2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"' . $categoryList . '"');

        // Apply validation to each cell in range (more reliable than setSqref)
        for ($row = 2; $row <= 500; $row++) {
            $cellValidation = clone $validation;
            $sheet->getCell('A' . $row)->setDataValidation($cellValidation);
        }
    }

    protected function addDropdownViaHiddenSheet($workbook, Worksheet $sheet): void
    {
        // Add Categories sheet at index 0 so reference works reliably
        $categoriesSheet = new Worksheet($workbook, 'Categories');
        $workbook->addSheet($categoriesSheet, 0);
        $categoriesSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        foreach ($this->categoryNames as $i => $name) {
            $categoriesSheet->setCellValue('A' . ($i + 1), $name);
        }
        $lastRow = count($this->categoryNames) ?: 1;
        $formula = 'Categories!$A$1:$A$' . $lastRow;

        // Ensure data sheet stays active when file opens (it moved to index 1)
        $workbook->setActiveSheetIndex(1);

        $validation = $sheet->getCell('A2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula);

        for ($row = 2; $row <= 500; $row++) {
            $cellValidation = clone $validation;
            $sheet->getCell('A' . $row)->setDataValidation($cellValidation);
        }
    }
}
