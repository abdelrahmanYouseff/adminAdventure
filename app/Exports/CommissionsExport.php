<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CommissionsExport implements FromCollection, WithHeadings, WithColumnWidths, WithStyles, WithEvents
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly string $monthLabel = '',
    ) {}

    public function collection(): Collection
    {
        $mapped = $this->rows
            ->values()
            ->map(fn (array $row, int $index) => $this->mapRow($row, $index + 1));

        $mapped->push([
            '',
            $this->totalsLabel(),
            '',
            '',
            '',
            (int) $this->rows->sum('games_count'),
            round((float) $this->rows->sum('total_amount'), 2),
            round((float) $this->rows->sum('commission'), 2),
        ]);

        return $mapped;
    }

    public function headings(): array
    {
        return [
            'م',
            'تاريخ الفاتورة',
            'رقم الفاتورة',
            'اسم العميل',
            'اسم المنتجات',
            'عدد الألعاب',
            'إجمالي الفاتورة',
            'العمولة',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 16,
            'C' => 18,
            'D' => 24,
            'E' => 28,
            'F' => 14,
            'G' => 18,
            'H' => 14,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = max(1, $sheet->getHighestRow());

                $sheet->setRightToLeft(true);
                $sheet->getStyle('A1:H'.$highestRow)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                $sheet->getStyle('E2:E'.$highestRow)->getAlignment()->setWrapText(true);

                for ($row = 2; $row <= $highestRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(22);
                }

                $sheet->getStyle('A1:H1')->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('EEF2FF');

                $sheet->getStyle('A'.$highestRow.':H'.$highestRow)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FEF3C7'],
                    ],
                ]);

                $sheet->getStyle('A1:H'.$highestRow)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: int, 1: string, 2: string, 3: string, 4: string, 5: int, 6: float, 7: float}
     */
    private function mapRow(array $row, int $index): array
    {
        $customer = trim((string) ($row['customer_name'] ?? ''));

        return [
            $index,
            (string) ($row['order_date'] ?? '—'),
            (string) ($row['invoice_number'] ?? '—'),
            $customer !== '' ? $customer : '—',
            $this->compactProducts($row),
            (int) ($row['games_count'] ?? 0),
            round((float) ($row['total_amount'] ?? 0), 2),
            round((float) ($row['commission'] ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function compactProducts(array $row): string
    {
        $names = $row['product_names'] ?? [];
        if (! is_array($names) || $names === []) {
            $label = trim((string) ($row['products_label'] ?? ''));

            return $label !== '' && $label !== '—' ? $label : '—';
        }

        $names = array_values(array_filter(array_map(
            fn ($name) => trim((string) $name),
            $names,
        )));

        if ($names === []) {
            return '—';
        }

        $visible = array_slice($names, 0, 2);
        $remaining = count($names) - count($visible);

        if ($remaining > 0) {
            $visible[] = '+'.$remaining;
        }

        return implode('، ', $visible);
    }

    private function totalsLabel(): string
    {
        $label = trim($this->monthLabel);

        return $label !== '' ? 'توتل '.$label : 'توتل الشهر';
    }
}
