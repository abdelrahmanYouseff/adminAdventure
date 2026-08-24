<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CommissionsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'تاريخ الطلب',
            'رقم الطلب',
            'عدد الألعاب',
            'إجمالي سعر الطلب',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['order_date'] ?? '—',
            $row['order_number'] ?? '—',
            (int) ($row['games_count'] ?? 0),
            number_format((float) ($row['total_amount'] ?? 0), 2, '.', ''),
        ];
    }
}
