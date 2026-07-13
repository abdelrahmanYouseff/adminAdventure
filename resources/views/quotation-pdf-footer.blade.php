@php
    /** @var \App\Support\QuotationPdfData $data */
@endphp
<table width="100%" cellpadding="0" cellspacing="0" style="font-family: dejavusans, sans-serif; font-size: 6.5pt; color: #1a1a1a; border-top: 1px solid #333; padding-top: 5px;">
    <tr>
        <td width="18%" valign="middle" align="left">
            @if($data->hasLogo())
                <img src="{{ $data->logoPath() }}" alt="Adventure World" height="26" style="max-width: 70px;">
            @endif
        </td>
        <td width="54%" valign="middle" align="center" style="font-weight: bold; font-size: 7pt; line-height: 1.4;">
            {{ $data->companyLegalNameEn() }}
            <div dir="rtl" style="font-family: xbriyaz, dejavusans, sans-serif; font-size: 6.5pt; font-weight: normal; margin-top: 2px;">
                {{ $data->companyLegalNameAr() }}
            </div>
        </td>
        <td width="28%" valign="middle" align="right" style="font-size: 6.5pt; line-height: 1.5;">
            <span style="font-weight: bold;">Proposal No:</span><br>
            <span style="font-weight: bold;">{{ $data->quotationNumber() }}</span>
        </td>
    </tr>
</table>
