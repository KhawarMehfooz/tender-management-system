<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Generic Excel export shared by every M12 report: fed a plain array of rows (each already
 * shaped for on-screen display, e.g. the same arrays a report's Blade view iterates) plus the
 * column headings to use, rather than one bespoke export class per report type.
 */
class ArrayExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  list<string>  $headings
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $headings,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }
}
