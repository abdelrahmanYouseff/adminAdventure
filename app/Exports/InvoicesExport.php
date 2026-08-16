<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoicesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly Collection $invoices,
    ) {}

    public function collection(): Collection
    {
        return $this->invoices;
    }

    public function headings(): array
    {
        return [
            'رقم الفاتورة',
            'اسم العميل',
            'المبلغ',
            'العملة',
            'الحالة',
            'طريقة الدفع',
            'البراند',
            'تاريخ الإنشاء',
        ];
    }

    /**
     * @param  Invoice  $invoice
     */
    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $this->customerName($invoice),
            $invoice->amount,
            'SAR',
            $invoice->status === 'paid' ? 'مدفوعة' : ucfirst((string) $invoice->status),
            $invoice->payment_method ?: '—',
            $invoice->brand?->name ?: '—',
            optional($invoice->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function customerName(Invoice $invoice): string
    {
        $name = trim((string) (
            $invoice->order?->customer_name
            ?: $invoice->user?->customer_name
            ?: $invoice->user?->name
            ?: ''
        ));

        return $name !== '' ? $name : '—';
    }
}
