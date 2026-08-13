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

        $bottomMargin = round(18 * $scale, 2);
        $isArabic = $data->isArabic();
        $generatedAt = now()->format('d-m-Y g:ia');

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => round(14 * $scale, 2),
            'margin_right' => round(14 * $scale, 2),
            'margin_top' => round(12 * $scale, 2),
            'margin_bottom' => $bottomMargin,
            'margin_header' => 4,
            'margin_footer' => round(9 * $scale, 2),
            'default_font' => 'dejavusans',
            'directionality' => $isArabic ? 'rtl' : 'ltr',
            'tempDir' => $tempDir,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
        ]);

        $title = $isArabic
            ? 'عرض سعر '.$data->quotationNumber()
            : 'Quotation '.$data->quotationNumber();
        $mpdf->SetTitle($title);

        $footerView = $isArabic ? 'quotation-pdf-footer-ar' : 'quotation-pdf-footer';
        $bodyView = $isArabic ? 'quotation-pdf-ar' : 'quotation-pdf';

        $mpdf->SetHTMLFooter(View::make($footerView, [
            'data' => $data,
            'scale' => $scale,
            'generatedAt' => $generatedAt,
        ])->render());

        $mpdf->WriteHTML(View::make($bodyView, [
            'data' => $data,
            'scale' => $scale,
            'bottomMargin' => $bottomMargin,
        ])->render());

        return [$mpdf->Output('', Destination::STRING_RETURN), $mpdf->page];
    }
}
