<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Arial', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #1a1a1a;
            background: #fff;
        }

                .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            margin-bottom: 40px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
        }

        .company-info h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .company-tagline {
            color: #6b7280;
            font-size: 14px;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h2 {
            font-size: 36px;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 5px;
        }

        .invoice-number {
            color: #6b7280;
            font-size: 16px;
        }

                .billing-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .billing-section {
            background: #f8fafc;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .billing-section h3 {
            color: #1e293b;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #2563eb;
        }

        .info-item {
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .info-label {
            font-weight: 500;
            color: #64748b;
            min-width: 120px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: #1e293b;
            font-weight: 500;
            text-align: right;
            flex: 1;
        }

                .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-paid {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .status-cancelled {
            background: #fecaca;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .status-overdue {
            background: #fed7aa;
            color: #c2410c;
            border: 1px solid #fdba74;
        }

                .invoice-table-section {
            margin: 40px 0;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2563eb;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .items-table th {
            background: #2563eb;
            color: white;
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .items-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            background: white;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .items-table tr:nth-child(even) td {
            background: #f8fafc;
        }

                .totals-section {
            background: #f8fafc;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-top: 30px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 16px;
        }

        .total-label {
            color: #64748b;
            font-weight: 500;
        }

        .total-value {
            color: #1e293b;
            font-weight: 600;
        }

        .total-final {
            border-top: 2px solid #2563eb;
            padding-top: 20px;
            margin-top: 20px;
            font-size: 22px;
            font-weight: 700;
        }

        .total-final .total-label {
            color: #2563eb;
        }

        .total-final .total-value {
            color: #2563eb;
            font-size: 24px;
        }

        .footer {
            margin-top: 60px;
            padding: 30px;
            background: #f1f5f9;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .footer-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .footer-text {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .footer-contact {
            color: #475569;
            font-size: 13px;
            border-top: 1px solid #cbd5e1;
            padding-top: 20px;
            margin-top: 20px;
        }

                .notes-section {
            background: #fefce8;
            border: 1px solid #facc15;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
        }

        .notes-section h4 {
            color: #92400e;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
        }

        .notes-section p {
            color: #a16207;
            line-height: 1.6;
            font-size: 14px;
        }

        .amount-large {
            font-size: 18px;
            font-weight: 600;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .mt-4 {
            margin-top: 16px;
        }

        .mb-4 {
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
        <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <div class="logo">AW</div>
                <div class="company-info">
                    <h1>Adventure World</h1>
                    <div class="company-tagline">Adventure & Entertainment Platform</div>
                </div>
            </div>
            <div class="invoice-title">
                <h2>INVOICE</h2>
                <div class="invoice-number">#{{ $invoice->invoice_number }}</div>
            </div>
        </div>

                <!-- Billing Information -->
        <div class="billing-details">
            <div class="billing-section">
                <h3>Invoice Details</h3>
                <div class="info-item">
                    <span class="info-label">Invoice Number</span>
                    <span class="info-value">{{ $invoice->invoice_number }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Issue Date</span>
                    <span class="info-value">{{ $invoice->created_at->format('M d, Y') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Due Date</span>
                    <span class="info-value">{{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'Not specified' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="status-badge status-{{ $invoice->status }}">
                            @switch($invoice->status)
                                @case('paid') PAID @break
                                @case('pending') PENDING @break
                                @case('cancelled') CANCELLED @break
                                @case('overdue') OVERDUE @break
                                @default {{ strtoupper($invoice->status) }}
                            @endswitch
                        </span>
                    </span>
                </div>
            </div>

            <div class="billing-section">
                <h3>Bill To</h3>
                <div class="info-item">
                    <span class="info-label">Customer Name</span>
                    <span class="info-value">{{ $invoice->user->full_name ?? $invoice->user->name ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email Address</span>
                    <span class="info-value">{{ $invoice->user->email ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone Number</span>
                    <span class="info-value">{{ $invoice->user->phone ?? 'N/A' }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Method</span>
                    <span class="info-value">
                        @switch($invoice->payment_method)
                            @case('noon') Noon Pay @break
                            @case('cash') Cash @break
                            @case('bank_transfer') Bank Transfer @break
                            @default {{ $invoice->payment_method ?? 'N/A' }}
                        @endswitch
                    </span>
                </div>
            </div>
        </div>

                <!-- Invoice Items -->
        <div class="invoice-table-section">
            <h3 class="section-title">Invoice Items</h3>

            @if($invoice->rental && $invoice->rental->product)
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 50%">Description</th>
                            <th style="width: 15%" class="text-center">Qty</th>
                            <th style="width: 20%" class="text-right">Unit Price</th>
                            <th style="width: 15%" class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight: 600; font-size: 16px; margin-bottom: 5px;">
                                    {{ $invoice->rental->product->product_name }}
                                </div>
                                <div style="color: #64748b; font-size: 13px;">
                                    {{ $invoice->rental->product->description ?? 'Adventure World Service' }}
                                </div>
                            </td>
                            <td class="text-center">1</td>
                            <td class="text-right amount-large">SAR {{ number_format($invoice->amount, 2) }}</td>
                            <td class="text-right amount-large">SAR {{ number_format($invoice->amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            @else
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 50%">Description</th>
                            <th style="width: 15%" class="text-center">Qty</th>
                            <th style="width: 20%" class="text-right">Unit Price</th>
                            <th style="width: 15%" class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div style="font-weight: 600; font-size: 16px; margin-bottom: 5px;">
                                    General Service
                                </div>
                                <div style="color: #64748b; font-size: 13px;">
                                    Adventure World Platform Service
                                </div>
                            </td>
                            <td class="text-center">1</td>
                            <td class="text-right amount-large">SAR {{ number_format($invoice->amount, 2) }}</td>
                            <td class="text-right amount-large">SAR {{ number_format($invoice->amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Totals Section -->
        <div class="totals-section">
            <div class="total-row">
                <span class="total-label">Subtotal:</span>
                <span class="total-value">SAR {{ number_format($invoice->amount, 2) }}</span>
            </div>
            <div class="total-row">
                <span class="total-label">VAT (15%):</span>
                <span class="total-value">SAR {{ number_format($invoice->amount * 0.15, 2) }}</span>
            </div>
            <div class="total-row total-final">
                <span class="total-label">Total Amount:</span>
                <span class="total-value">SAR {{ number_format($invoice->amount * 1.15, 2) }}</span>
            </div>
        </div>

                <!-- Notes Section -->
        @if($invoice->notes ?? false)
        <div class="notes-section">
            <h4>📝 Notes</h4>
            <p>{{ $invoice->notes }}</p>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div class="footer-title">Thank you for choosing Adventure World!</div>
            <div class="footer-text">
                We appreciate your business and look forward to serving you again.
            </div>
            <div class="footer-contact">
                <strong>Adventure World</strong> - Adventure & Entertainment Platform<br>
                Email: contact@adventureworld.com | Phone: +966 50 123 4567<br>
                Website: www.adventureworld.com
                <div class="mt-4" style="font-size: 11px; color: #94a3b8;">
                    This invoice was automatically generated on {{ now()->format('M d, Y \a\t H:i:s') }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
