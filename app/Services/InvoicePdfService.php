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
            'margin_bottom' => 22,
            'margin_header' => 4,
            'margin_footer' => 8,
            'default_font' => 'dejavusans',
            'directionality' => 'ltr',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
        ]);

        $mpdf->SetTitle('Tax Invoice '.$data->invoiceNumber());

        $footerHtml = View::make('invoice-pdf-footer', ['data' => $data])->render();
        $mpdf->SetHTMLFooter($footerHtml);

        $html = View::make('invoice-pdf', ['data' => $data])->render();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
