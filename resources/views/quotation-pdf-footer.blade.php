@php
    /** @var \App\Support\QuotationPdfData $data */
    /** @var float $scale */
    $scale = $scale ?? 1.0;
    $pt = fn (float $size) => round($size * $scale, 2).'pt';
@endphp
<table width="100%" cellpadding="0" cellspacing="0" style="font-family: dejavusans, sans-serif; font-size: {{ $pt(6.5) }}; color: #1a1a1a; border-top: 1px solid #333; padding-top: {{ $pt(3) }};">
    <tr>
        <td width="18%" valign="middle" align="left">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="{{ $data->logoAlt() }}" height="{{ round(36 * $scale) }}" style="max-width: {{ round(100 * $scale) }}px;">
            @endif
        </td>
        <td width="54%" valign="middle" align="center" style="font-weight: bold; font-size: {{ $pt(7) }}; line-height: 1.3;">
            {{ $data->companyLegalNameEn() }}
            <div dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif; font-size: {{ $pt(6.5) }}; font-weight: normal;">
                {{ $data->companyLegalNameAr() }}
            </div>
        </td>
        <td width="28%" valign="middle" align="right" style="font-size: {{ $pt(6.5) }}; line-height: 1.4;">
            <span style="font-weight: bold;">Quotation No:</span><br>
            <span style="font-weight: bold;">{{ $data->quotationNumber() }}</span>
        </td>
    </tr>
</table>
