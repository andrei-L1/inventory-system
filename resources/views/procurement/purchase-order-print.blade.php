<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - {{ $po->po_number }}</title>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #1a202c;
            line-height: 1.5;
            margin: 0;
            padding: 40px;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
        .header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-info h1 {
            margin: 0;
            font-size: 24px;
            color: #2d3748;
            letter-spacing: -0.025em;
        }
        .company-info p {
            margin: 4px 0;
            font-size: 13px;
            color: #718096;
        }
        .po-title {
            text-align: right;
        }
        .po-title h2 {
            margin: 0;
            font-size: 28px;
            color: #3182ce;
            text-transform: uppercase;
        }
        .po-title p {
            margin: 4px 0;
            font-weight: 600;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        .section-label {
            font-size: 12px;
            font-weight: 700;
            color: #a0aec0;
            text-transform: uppercase;
            margin-bottom: 8px;
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 4px;
        }
        .info-block p {
            margin: 4px 0;
            font-size: 14px;
        }
        .vendor-info b { font-size: 16px; color: #2d3748; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th {
            background-color: #f7fafc;
            color: #4a5568;
            font-size: 12px;
            text-transform: uppercase;
            text-align: left;
            padding: 12px 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        td {
            padding: 12px 8px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            vertical-align: top;
        }
        .text-right { text-align: right; }
        .font-mono { font-family: ui-monospace, monospace; }
        
        .totals {
            margin-left: auto;
            width: 300px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .total-row.grand-total {
            border-top: 2px solid #2d3748;
            border-bottom: none;
            font-weight: 800;
            font-size: 18px;
            margin-top: 10px;
        }

        .footer {
            margin-top: 60px;
            font-size: 12px;
            color: #a0aec0;
            text-align: center;
        }
        .signature-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 100px;
            margin-top: 80px;
        }
        .sig-box {
            border-top: 1px solid #718096;
            padding-top: 8px;
            text-align: center;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #3182ce; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Print Document</button>
    </div>

    <div class="header">
        <div class="company-info">
            <h1>{{ $company['name'] }}</h1>
            <p>{{ $company['address'] }}</p>
            <p>Phone: {{ $company['phone'] }} | Email: {{ $company['email'] }}</p>
            <p>Web: {{ $company['website'] }}</p>
        </div>
        <div class="po-title">
            <h2>Purchase Order</h2>
            <p># {{ $po->po_number }}</p>
            <p>Date: {{ optional($po->order_date)->format('M d, Y') }}</p>
        </div>
    </div>

    <div class="details-grid">
        <div class="info-block">
            <div class="section-label">Vendor Information</div>
            <div class="vendor-info">
                <p><b>{{ $po->vendor->name }}</b></p>
                @if($po->vendor->address)<p>{{ $po->vendor->address }}</p>@endif
                <p>Code: {{ $po->vendor->vendor_code }}</p>
                @if($po->vendor->email)<p>Email: {{ $po->vendor->email }}</p>@endif
                @if($po->vendor->phone)<p>Phone: {{ $po->vendor->phone }}</p>@endif
            </div>
        </div>
        <div class="info-block">
            <div class="section-label">Shipping Details</div>
            <p><b>Status:</b> {{ strtoupper($po->status->name) }}</p>
            @if($po->expected_delivery_date)
                <p><b>Expected Delivery:</b> {{ optional($po->expected_delivery_date)->format('M d, Y') }}</p>
            @endif
            @if($po->carrier)
                <p><b>Carrier:</b> {{ $po->carrier }}</p>
            @endif
            @if($po->tracking_number)
                <p><b>Tracking:</b> {{ $po->tracking_number }}</p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="15%">SKU / MPN</th>
                <th width="40%">Product Description</th>
                <th width="15%" class="text-right">Ordered Quantity</th>
                <th width="12%" class="text-right">Unit Cost</th>
                <th width="13%" class="text-right">Total Line Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->lines as $index => $line)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="font-mono">
                        <span style="display: block; font-weight: bold; color: #2d3748;">{{ $line->product->sku }}</span>
                        @if($line->product->product_code)
                            <span style="display: block; font-size: 10px; color: #718096; margin-top: 2px;">MPN: {{ $line->product->product_code }}</span>
                        @endif
                    </td>
                    <td>
                        <b>{{ $line->product->name }}</b>
                        @if($line->notes)<br><small style="color: #718096 italic;">{{ $line->notes }}</small>@endif
                    </td>
                    <td class="text-right">
                        {{ $line->formatted_ordered_qty }}
                    </td>
                    <td class="text-right">{{ $po->currency }} {{ number_format($line->unit_cost, 2) }}</td>
                    <td class="text-right">{{ $po->currency }} {{ number_format($line->total_cost, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span>{{ $po->currency }} {{ number_format($po->total_amount, 2) }}</span>
        </div>
        <div class="total-row grand-total">
            <span>Total Order Value:</span>
            <span>{{ $po->currency }} {{ number_format($po->total_amount, 2) }}</span>
        </div>
    </div>

    @if($po->notes)
        <div style="margin-top: 40px;">
            <div class="section-label">Order Notes</div>
            <p style="font-size: 12px; color: #4a5568;">{{ $po->notes }}</p>
        </div>
    @endif

    <div class="signature-sections">
        <div class="sig-box">
            <b>Authorized Signature</b><br>
            {{ $po->approver->name ?? '_______________________' }}<br>
            <span style="font-size: 11px;">(Date: {{ $po->approved_at ? $po->approved_at->format('M d, Y') : '____/____/____' }})</span>
        </div>
        <div class="sig-box">
            <b>Vendor Acknowledgment</b><br>
            Received and accepted by<br>
            <span style="font-size: 11px;">(Name and Date)</span>
        </div>
    </div>

    <div class="footer">
        <p>This is a computer-generated Purchase Order and is valid without a physical signature if electronically authorized.</p>
        <p>Generated by {{ auth()->user()->name ?? 'System' }} on {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
