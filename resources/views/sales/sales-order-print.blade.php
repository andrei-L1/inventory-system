<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $salesOrder->so_number }} - {{ $company['name'] }}</title>
    <style>
        @page { size: A4; margin: 20mm; }
        body { font-family: 'Inter', system-ui, sans-serif; color: #1a1a1a; line-height: 1.5; margin: 0; padding: 0; font-size: 11pt; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .company-info h1 { margin: 0; color: #000; font-size: 24pt; letter-spacing: -0.02em; }
        .document-type { text-align: right; }
        .document-type h2 { margin: 0; color: #666; font-size: 18pt; text-transform: uppercase; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 30px; }
        .section-title { font-size: 9pt; font-weight: 700; text-transform: uppercase; color: #666; margin-bottom: 8px; border-bottom: 1px solid #eee; }
        .content-box { p { margin: 2px 0; } }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #f8f9fa; text-align: left; padding: 10px; font-size: 9pt; text-transform: uppercase; border-bottom: 2px solid #dee2e6; }
        td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        .text-right { text-align: right; }
        .total-section { float: right; width: 300px; margin-top: 20px; }
        .total-row { display: flex; justify-content: space-between; padding: 5px 0; }
        .total-row.grand-total { border-top: 2px solid #000; font-weight: 700; font-size: 12pt; margin-top: 10px; padding-top: 10px; }
        .footer { margin-top: 50px; font-size: 9pt; color: #666; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
        .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 100px; margin-top: 60px; }
        .signature-box { border-top: 1px solid #000; text-align: center; padding-top: 10px; font-size: 9pt; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 8pt; font-weight: 600; text-transform: uppercase; background: #eee; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <h1>{{ $company['name'] }}</h1>
            <p>{{ $company['address'] }}<br>{{ $company['phone'] }} | {{ $company['email'] }}</p>
        </div>
        <div class="document-type">
            <h2>{{ in_array($salesOrder->status->name, ['shipped', 'partially_shipped']) ? 'Packing Slip' : 'Picking List' }}</h2>
            <p><strong>Order #:</strong> {{ $salesOrder->so_number }}<br>
               <strong>Date:</strong> {{ \Carbon\Carbon::parse($salesOrder->order_date)->format('M d, Y') }}</p>
        </div>
    </div>

    <div class="meta-grid">
        <div class="content-box">
            <div class="section-title">Customer / Ship To</div>
            <p><strong>{{ $salesOrder->customer->name }}</strong></p>
            <p>{{ $salesOrder->customer->address ?? 'No address provided' }}</p>
            <p>Email: {{ $salesOrder->customer->email }}</p>
        </div>
        <div class="content-box">
            <div class="section-title">Order Logistics</div>
            <p><strong>Status:</strong> <span class="badge">{{ str_replace('_', ' ', $salesOrder->status->name) }}</span></p>
            <p><strong>Expected Shipping:</strong> {{ $salesOrder->expected_shipping_date ? \Carbon\Carbon::parse($salesOrder->expected_shipping_date)->format('M d, Y') : 'Not scheduled' }}</p>
            @if($salesOrder->carrier)
                <p><strong>Carrier:</strong> {{ $salesOrder->carrier }}</p>
                <p><strong>Tracking:</strong> {{ $salesOrder->tracking_number }}</p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Location</th>
                <th class="text-right">Ordered</th>
                <th class="text-right">Picked</th>
                <th class="text-right">Packed</th>
                <th class="text-right">Shipped</th>
                <th>Units</th>
            </tr>
        </thead>
        <tbody>
            @foreach($salesOrder->lines as $line)
                <tr>
                    <td>
                        <strong>{{ $line->product->sku }}</strong><br>
                        <span style="font-size: 9pt; color: #666;">{{ $line->product->name }}</span>
                    </td>
                    <td>{{ $line->location->name }}</td>
                    <td class="text-right">{{ number_format($line->ordered_qty, 2) }}</td>
                    <td class="text-right">{{ number_format($line->picked_qty, 2) }}</td>
                    <td class="text-right">{{ number_format($line->packed_qty, 2) }}</td>
                    <td class="text-right">{{ number_format($line->shipped_qty, 2) }}</td>
                    <td>{{ $line->uom->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signature-grid">
        <div class="signature-box">
            Warehouse / Prepared By<br>
            <small>{{ auth()->user()->name }} (Nexus Authorized)</small>
        </div>
        <div class="signature-box">
            Customer / Received By<br>
            <small>Date: ____ / ____ / ________</small>
        </div>
    </div>

    <div class="footer">
        <p>This is a system-generated document from <strong>{{ config('app.name') }}</strong>. All stock movements are audited for accuracy.</p>
        <p>© {{ date('Y') }} Nexus Logistics. Proprietary & Confidential.</p>
    </div>
</body>
</html>
