<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CourseTemplateExport implements FromArray, WithHeadings, WithEvents
{
    protected string $format = 'xlsx';

    /**
     * @param  string|null  $format  'xlsx' or 'csv'
     * @param  array<string>  $subCategoryNames
     * @param  array<string>  $currencies
     */
    public function __construct(
        ?string $format = 'xlsx',
        protected array $subCategoryNames = [],
        protected array $currencies = [],
    ) {
        $this->format = strtolower($format ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';
    }

    public function array(): array
    {
        $firstSubCategory = $this->subCategoryNames[0] ?? '';
        $defaultLevel = 'Beginner';
        $defaultStatus = 'Active';
        $defaultCurrency = $this->currencies[0] ?? 'USD';

        return [[
            $firstSubCategory,   // sub_category
            '',                  // name
            '',                  // code
            '',                  // description
            8,                   // duration_hours
            20,                  // max_capacity
            'No',                // assessor_required
            $defaultLevel,       // level
            $defaultStatus,      // status
            0,                   // base_price
            $defaultCurrency,    // currency
        ]];
    }

    public function headings(): array
    {
        return [
            'sub_category',
            'name',
            'code',
            'description',
            'duration_hours',
            'max_capacity',
            'assessor_required',
            'level',
            'status',
            'base_price',
            'currency',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if ($this->format === 'csv') {
                    return;
                }

                $sheet = $event->sheet->getDelegate();

                if (!empty($this->subCategoryNames)) {
                    $this->addDropdownFromList($sheet, 'A', $this->subCategoryNames, 'SubCategories');
                }

                $this->addFixedDropdown($sheet, 'G', ['Yes', 'No']); // assessor_required
                $this->addFixedDropdown($sheet, 'H', ['Beginner', 'Intermediate', 'Advanced']); // level
                $this->addFixedDropdown($sheet, 'I', ['Active', 'Inactive']); // status

                if (!empty($this->currencies)) {
                    $this->addDropdownFromList($sheet, 'K', $this->currencies, 'Currencies');
                }
            },
        ];
    }

    /**
     * Add a dropdown list to a column using either a direct formula or a hidden sheet,
     * depending on commas in values and total length.
     *
     * @param  Worksheet  $sheet
     * @param  string  $column
     * @param  array<string>  $values
     * @param  string  $hiddenSheetTitle
     * @param  int  $startRow
     * @param  int  $endRow
     */
    protected function addDropdownFromList(
        Worksheet $sheet,
        string $column,
        array $values,
        string $hiddenSheetTitle,
        int $startRow = 2,
        int $endRow = 500,
    ): void {
        $hasComma = collect($values)->contains(fn ($v) => str_contains((string) $v, ','));
        $listLength = strlen(implode(',', $values));

        if (!$hasComma && $listLength <= 250) {
            $this->addDropdownViaFormula($sheet, $column, $values, $startRow, $endRow);
        } else {
            $this->addDropdownViaHiddenSheet($sheet, $column, $values, $hiddenSheetTitle, $startRow, $endRow);
        }
    }

    /**
     * Add a simple list validation via formula string (no hidden sheet).
     *
     * @param  Worksheet  $sheet
     * @param  string  $column
     * @param  array<string>  $values
     * @param  int  $startRow
     * @param  int  $endRow
     */
    protected function addDropdownViaFormula(
        Worksheet $sheet,
        string $column,
        array $values,
        int $startRow,
        int $endRow,
    ): void {
        $list = implode(',', array_map(
            fn ($v) => str_replace('"', '""', (string) $v),
            $values
        ));

        $cellCoordinate = $column . $startRow;
        $validation = $sheet->getCell($cellCoordinate)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"' . $list . '"');

        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->getCell($column . $row)->setDataValidation(clone $validation);
        }
    }

    /**
     * Add list validation using a hidden sheet to store values.
     *
     * @param  Worksheet  $sheet
     * @param  string  $column
     * @param  array<string>  $values
     * @param  string  $hiddenSheetTitle
     * @param  int  $startRow
     * @param  int  $endRow
     */
    protected function addDropdownViaHiddenSheet(
        Worksheet $sheet,
        string $column,
        array $values,
        string $hiddenSheetTitle,
        int $startRow,
        int $endRow,
    ): void {
        $workbook = $sheet->getParent();
        $listSheet = new Worksheet($workbook, $hiddenSheetTitle);
        $workbook->addSheet($listSheet, 0);
        $listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        foreach ($values as $i => $value) {
            $listSheet->setCellValue('A' . ($i + 1), $value);
        }

        $lastRow = count($values) ?: 1;
        $formula = $hiddenSheetTitle . '!$A$1:$A$' . $lastRow;

        $workbook->setActiveSheetIndex(1);

        $cellCoordinate = $column . $startRow;
        $validation = $sheet->getCell($cellCoordinate)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1($formula);

        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->getCell($column . $row)->setDataValidation(clone $validation);
        }
    }

    /**
     * Add a fixed, small dropdown list using a simple formula (no hidden sheet).
     *
     * @param  Worksheet  $sheet
     * @param  string  $column
     * @param  array<string>  $values
     * @param  int  $startRow
     * @param  int  $endRow
     */
    protected function addFixedDropdown(
        Worksheet $sheet,
        string $column,
        array $values,
        int $startRow = 2,
        int $endRow = 500,
    ): void {
        if (empty($values)) {
            return;
        }

        $list = implode(',', array_map(
            fn ($v) => str_replace('"', '""', (string) $v),
            $values
        ));

        $cellCoordinate = $column . $startRow;
        $validation = $sheet->getCell($cellCoordinate)->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"' . $list . '"');

        for ($row = $startRow; $row <= $endRow; $row++) {
            $sheet->getCell($column . $row)->setDataValidation(clone $validation);
        }
    }
}

