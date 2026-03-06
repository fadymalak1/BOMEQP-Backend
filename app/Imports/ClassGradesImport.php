<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

class ClassGradesImport implements ToArray
{
    /**
     * Simply return the parsed array data as-is.
     *
     * @param array<int, array<int, mixed>> $array
     * @return array<int, array<int, mixed>>
     */
    public function array(array $array): array
    {
        return $array;
    }
}

