@php
    /** @var \App\Support\QuotationPdfData $data */
    /** @var float $scale */
    $scale = $scale ?? 1.0;
    $pt = fn (float $size) => round($size * $scale, 2).'pt';
    $generatedAt = $generatedAt ?? now()->format('d-m-Y ga');
@endphp
<table width="100%" cellpadding="0" cellspacing="0" style="font-family: dejavusans, sans-serif; font-size: {{ $pt(6.5) }}; color: #1a1a1a; border-top: 1px solid #333; padding-top: {{ $pt(3) }};">
    <tr>
        <td width="50%" valign="middle" align="left" style="font-size: {{ $pt(6) }}; color: #666;" dir="ltr">
            System generated at {{ $generatedAt }}
        </td>
        <td width="50%" valign="middle" align="right" style="font-size: {{ $pt(6.5) }}; line-height: 1.4;">
            <span dir="rtl" style="font-weight: bold; font-family: xbriyaz, dejavusans, sans-serif;">{{ $data->biLabel('Quotation No', 'رقم العرض') }}:</span>
            <span style="font-weight: bold;">{{ $data->quotationNumber() }}</span>
        </td>
    </tr>
</table>
