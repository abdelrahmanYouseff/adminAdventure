<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation {{ $quotation->quotation_number }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
            direction: ltr;
            text-align: left;
            font-size: 14px;
            line-height: 1.6;
        }

        h1, h2, h3, h4 {
            font-family: 'Arial', sans-serif;
            font-weight: bold;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-info {
            text-align: center;
            margin-bottom: 30px;
        }
        .text-right {
            text-align: left;
        }
        .text-left {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .quotation-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .customer-info, .quotation-info {
            flex: 1;
        }
        .customer-info {
            text-align: right;
        }
        .quotation-info {
            text-align: left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .text-right {
            text-align: left;
        }
        .text-center {
            text-align: center;
        }
        .summary {
            margin-left: auto;
            width: 300px;
        }
        .summary table {
            margin-bottom: 0;
        }
        .summary th {
            background-color: transparent;
            border: none;
            padding: 8px 12px;
        }
        .summary td {
            border: none;
            padding: 8px 12px;
            text-align: left;
        }
        .total-row {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 16px;
        }
        .notes {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft { background-color: #e9ecef; color: #495057; }
        .status-sent { background-color: #d1ecf1; color: #0c5460; }
        .status-accepted { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-expired { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="header">
        <h1>QUOTATION</h1>
        <div class="company-info">
            <h2>Adventure World</h2>
            <p>Your Adventure Partner</p>
            <p>Email: info@adventureworld.com | Phone: +1 (555) 123-4567</p>
        </div>
    </div>

        <div class="quotation-details">
        <div class="customer-info">
            <h3>Bill To:</h3>
            <p><strong>{{ $quotation->customer_name }}</strong></p>
            @if($quotation->customer_email)
                <p>Email: {{ $quotation->customer_email }}</p>
            @endif
            @if($quotation->customer_phone)
                <p>Phone: {{ $quotation->customer_phone }}</p>
            @endif
            @if($quotation->customer_address)
                <p>Address: {{ $quotation->customer_address }}</p>
            @endif
        </div>
        <div class="quotation-info">
            <p><strong>Quotation #:</strong> {{ $quotation->quotation_number }}</p>
            <p><strong>Date:</strong> {{ $quotation->created_at->format('M d, Y') }}</p>
            <p><strong>Valid Until:</strong> {{ \Carbon\Carbon::parse($quotation->valid_until)->format('M d, Y') }}</p>
            <p><strong>Status:</strong>
                <span class="status-badge status-{{ $quotation->status }}">
                    @if($quotation->status == 'draft')
                        Draft
                    @elseif($quotation->status == 'sent')
                        Sent
                    @elseif($quotation->status == 'accepted')
                        Accepted
                    @elseif($quotation->status == 'rejected')
                        Rejected
                    @elseif($quotation->status == 'expired')
                        Expired
                    @else
                        {{ ucfirst($quotation->status) }}
                    @endif
                </span>
            </p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Description</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $item)
            <tr>
                <td><strong>{{ $item->product_name }}</strong></td>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right"><strong>${{ number_format($item->total_price, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <table>
            <tr>
                <th>Subtotal:</th>
                <td>${{ number_format($quotation->subtotal, 2) }}</td>
            </tr>
            <tr>
                <th>Tax (15%):</th>
                <td>${{ number_format($quotation->tax_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <th>Total:</th>
                <td>${{ number_format($quotation->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($quotation->notes)
    <div class="notes">
        <h4>Notes:</h4>
        <p>{{ $quotation->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>This quotation is valid until {{ \Carbon\Carbon::parse($quotation->valid_until)->format('M d, Y') }}</p>
        <p>For any inquiries, please contact us at info@adventureworld.com</p>
    </div>
</body>
</html>
