<?php

namespace App\Exports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

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

                $this->addDropdownViaHiddenSheet($sheet);
            },
        ];
    }

    protected function addDropdownViaHiddenSheet($sheet): void
    {
        $workbook = $sheet->getParent();
        $categoriesSheet = $workbook->addSheet(new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($workbook, 'Categories'));
        $categoriesSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

        foreach ($this->categoryNames as $i => $name) {
            $categoriesSheet->setCellValue('A' . ($i + 1), $name);
        }
        $lastRow = count($this->categoryNames) ?: 1;
        $formula = 'Categories!$A$1:$A$' . $lastRow;

        $validation = $sheet->getCell('A2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula);

        $validation->setSqref('A2:A1000');
    }
}
