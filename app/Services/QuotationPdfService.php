<?php

namespace App\Services;

use App\Support\QuotationPdfData;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class QuotationPdfService
{
    /**
     * Progressively tighter layouts, tried in order until the quotation fits
     * on a single page.
     */
    private const SCALES = [1.0, 0.94, 0.88, 0.82, 0.76, 0.7, 0.64, 0.58];

    public function render(QuotationPdfData $data): string
    {
        $smallest = null;

        foreach (self::SCALES as $scale) {
            [$content, $pages] = $this->build($data, $scale);
            $smallest = $content;

            if ($pages <= 1) {
                return $content;
            }
        }

        return $smallest;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function build(QuotationPdfData $data, float $scale): array
    {
        $tempDir = storage_path('app/mpdf-tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $bottomMargin = round(16 * $scale, 2);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => round(14 * $scale, 2),
            'margin_right' => round(14 * $scale, 2),
            'margin_top' => round(12 * $scale, 2),
            'margin_bottom' => $bottomMargin,
            'margin_header' => 4,
            'margin_footer' => round(6 * $scale, 2),
            'default_font' => 'dejavusans',
            'directionality' => 'ltr',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
        ]);

        $mpdf->SetTitle('Quotation '.$data->quotationNumber());

        $mpdf->SetHTMLFooter(View::make('quotation-pdf-footer', [
            'data' => $data,
            'scale' => $scale,
        ])->render());

        $mpdf->WriteHTML(View::make('quotation-pdf', [
            'data' => $data,
            'scale' => $scale,
            'bottomMargin' => $bottomMargin,
        ])->render());

        return [$mpdf->Output('', Destination::STRING_RETURN), $mpdf->page];
    }
}
