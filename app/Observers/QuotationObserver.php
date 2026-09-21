<?php

namespace App\Observers;

use App\Models\Quotation;
use App\Support\QuotationLogRecorder;

class QuotationObserver
{
    public function created(Quotation $quotation): void
    {
        QuotationLogRecorder::created($quotation);
    }

    public function updated(Quotation $quotation): void
    {
        QuotationLogRecorder::updated($quotation);
    }
}
