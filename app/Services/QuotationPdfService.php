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
        try {
            $smallest = null;

            foreach (self::SCALES as $scale) {
                [$content, $pages] = $this->build($data, $scale);
                $smallest = $content;

                if ($pages <= 1) {
                    return $content;
                }
            }

            return (string) $smallest;
        } finally {
            \App\Support\MediaStorage::cleanupTempFiles();
        }
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

        $content = $mpdf->Output('', Destination::STRING_RETURN);

        return [$this->normalizeLinkRects($content), $mpdf->page];
    }

    /**
     * mPDF writes Link /Rect as [x y x+w y-h] (y2 < y1). Some PDF viewers
     * ignore those hit-boxes, so normalize opposite corners to ll/ur order.
     */
    private function normalizeLinkRects(string $pdf): string
    {
        return (string) preg_replace_callback(
            '/(\/Subtype\s*\/Link\s*\/Rect\s*\[)([^\]]+)(\])/',
            static function (array $matches): string {
                $parts = preg_split('/\s+/', trim($matches[2])) ?: [];
                if (count($parts) !== 4) {
                    return $matches[0];
                }

                [$x1, $y1, $x2, $y2] = array_map('floatval', $parts);

                return sprintf(
                    '%s%.3F %.3F %.3F %.3F%s',
                    $matches[1],
                    min($x1, $x2),
                    min($y1, $y2),
                    max($x1, $x2),
                    max($y1, $y2),
                    $matches[3],
                );
            },
            $pdf,
        );
    }
}
