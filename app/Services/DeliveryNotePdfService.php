<?php

namespace App\Services;

use App\Support\DeliveryNotePdfData;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class DeliveryNotePdfService
{
    public function render(DeliveryNotePdfData $data): string
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
            'margin_header' => 4,
            'margin_footer' => 4,
            'default_font' => 'dejavusans',
            'directionality' => 'ltr',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
        ]);

        $mpdf->SetTitle('إذن تسليم '.$data->referenceNumber());

        $html = View::make('delivery-note-pdf', ['data' => $data])->render();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
