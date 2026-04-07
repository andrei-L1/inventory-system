<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movement - {{ $trx->reference_number }}</title>
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
        .trx-title {
            text-align: right;
        }
        .trx-title h2 {
            margin: 0;
            font-size: 28px;
            color: #2d3748;
            text-transform: uppercase;
        }
        .trx-title p {
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
        .type-badge {
            background-color: #ebf8ff;
            color: #2b6cb0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #2d3748; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Print Document</button>
    </div>

    <div class="header">
        <div class="company-info">
            <h1>{{ $company['name'] }}</h1>
            <p>{{ $company['address'] }}</p>
            <p>Phone: {{ $company['phone'] }} | Email: {{ $company['email'] }}</p>
        </div>
        <div class="trx-title">
            <div class="type-badge">{{ $trx->type->name }}</div>
            <h2>{{ strtoupper($trx->type->name) }} VOUCHER</h2>
            <p># {{ $trx->reference_number }}</p>
            <p>Date: {{ $trx->transaction_date->format('M d, Y') }}</p>
        </div>
    </div>

    <div class="details-grid">
        <div class="info-block">
            <div class="section-label">Movement Logistics</div>
            <p><b>From:</b> {{ $trx->fromLocation->name ?? 'External Vendor / Global Source' }}</p>
            <p><b>To:</b> {{ $trx->toLocation->name ?? 'External Customer / Disposal' }}</p>
            @if($trx->vendor)
                <p><b>Vendor:</b> {{ $trx->vendor->name }} ({{ $trx->vendor->vendor_code }})</p>
            @endif
            @if($trx->customer)
                <p><b>Customer:</b> {{ $trx->customer->name }}</p>
            @endif
        </div>
        <div class="info-block">
            <div class="section-label">System Metadata</div>
            <p><b>Status:</b> {{ strtoupper($trx->status->name ?? 'POSTED') }}</p>
            @if($trx->purchase_order_id)<p><b>Source PO:</b> {{ $trx->purchaseOrder->po_number }}</p>@endif
            @if($trx->sales_order_id)<p><b>Related SO:</b> {{ $trx->salesOrder->so_number }}</p>@endif
            <p><b>Processed By:</b> {{ $trx->createdBy->name ?? 'System' }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="20%">SKU / MPN</th>
                <th width="55%">Product Description</th>
                <th width="20%" class="text-right">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach($trx->lines as $index => $line)
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
                        <b>{{ $line->formatted_quantity }}</b>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($trx->notes)
        <div style="margin-top: 20px;">
            <div class="section-label">Movement Notes</div>
            <p style="font-size: 13px; color: #4a5568;">{{ $trx->notes }}</p>
        </div>
    @endif

    <div class="signature-sections">
        <div class="sig-box">
            <b>Dispatcher / Storekeeper</b><br>
            _______________________<br>
            <span style="font-size: 11px;">(Signature and Date)</span>
        </div>
        <div class="sig-box">
            <b>Receiver / Supervisor</b><br>
            _______________________<br>
            <span style="font-size: 11px;">(Signature and Date)</span>
        </div>
    </div>

    <div class="footer">
        <p>This is a computer-generated Stock Movement Voucher and is valid for inventory reconciliation purposes.</p>
        <p>Generated by {{ auth()->user()->name ?? 'System' }} on {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
