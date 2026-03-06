<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CategoryTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['', '', '', '', 'active'],
        ];
    }

    public function headings(): array
    {
        return ['name', 'name_ar', 'description', 'icon_url', 'status'];
    }
}
