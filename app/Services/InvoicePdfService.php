<?php

namespace App\Services;

use App\Support\InvoicePdfData;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class InvoicePdfService
{
    public function render(InvoicePdfData $data): string
    {
        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'default_font' => 'xbriyaz',
            'directionality' => 'rtl',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        $mpdf->SetTitle('فاتورة '.$data->invoiceNumber());

        $html = View::make('invoice-pdf', ['data' => $data])->render();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
