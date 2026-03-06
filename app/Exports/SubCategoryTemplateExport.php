<?php

namespace App\Exports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubCategoryTemplateExport extends DefaultValueBinder implements FromArray, WithHeadings, WithCustomValueBinder, WithEvents
{
    protected array $categoryNames = [];

    public function __construct()
    {
        $this->categoryNames = Category::orderBy('name')->pluck('name')->toArray();
    }

    public function array(): array
    {
        $firstCategory = $this->categoryNames[0] ?? '';

        if (empty($this->categoryNames)) {
            return [['', '', '']];
        }

        $hasComma = collect($this->categoryNames)->contains(fn ($n) => str_contains((string) $n, ','));
        $listLength = strlen(implode(',', $this->categoryNames));

        // Pass array so bindValue creates dropdown (only when list fits Excel's ~255 char limit and no commas)
        if (!$hasComma && $listLength <= 250) {
            return [[$this->categoryNames, '', '']];
        }

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
                if (empty($this->categoryNames)) {
                    return;
                }
                $hasComma = collect($this->categoryNames)->contains(fn ($n) => str_contains((string) $n, ','));
                $listLength = strlen(implode(',', $this->categoryNames));
                if ($hasComma || $listLength > 250) {
                    $this->addDropdownViaHiddenSheet($event->sheet->getDelegate());
                }
            },
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        if (is_array($value)) {
            $list = implode(',', array_map(fn ($n) => str_replace('"', '""', (string) $n), $value));
            $validation = $cell->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"' . $list . '"');

            $sheet = $cell->getWorksheet();
            for ($row = 2; $row <= 500; $row++) {
                $sheet->getCell('A' . $row)->setDataValidation(clone $validation);
            }

            $value = $value[0] ?? '';
        }

        return parent::bindValue($cell, $value);
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
