<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - {{ $payment->payment_number }}</title>
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
        .inv-title {
            text-align: right;
        }
        .inv-title h2 {
            margin: 0;
            font-size: 28px;
            color: #38a169;
            text-transform: uppercase;
        }
        .inv-title p {
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
        .info-block b { font-size: 16px; color: #2d3748; }

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
            width: 380px;
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
        
        .status-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            padding: 4px 10px;
            border-radius: 4px;
            font-family: ui-monospace, monospace;
            background: #f0fff4; color: #38a169;
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
        <button onclick="window.print()" style="padding: 10px 20px; background: #3182ce; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
            Print Document
        </button>
    </div>

    <div class="header">
        <div class="company-info">
            <h1>{{ $company['name'] }}</h1>
            <p>{{ $company['address'] }}</p>
            <p>Phone: {{ $company['phone'] }} | Email: {{ $company['email'] }}</p>
            <p>Web: {{ $company['website'] }}</p>
            <p><b>TAX ID:</b> {{ $company['tax_id'] ?? 'N/A' }}</p>
        </div>
        <div class="inv-title">
            <h2>Payment Receipt</h2>
            <p class="font-mono">{{ $payment->payment_number }}</p>
            <p>Date: {{ optional($payment->payment_date)->format('M d, Y') }}</p>
            <br>
            <span class="status-badge">RECEIVED</span>
        </div>
    </div>

    <div class="details-grid">
        <div class="info-block">
            <div class="section-label">Received From</div>
            <p><b>{{ $payment->customer->name }}</b></p>
            <p class="font-mono" style="color: #718096; font-size: 12px;">{{ $payment->customer->customer_code }}</p>
            <p>{{ $payment->customer->billing_address ?? 'Billing Address not specified (N/A)' }}</p>
            <p><b>TIN:</b> {{ $payment->customer->tax_number ?? 'N/A' }}</p>
        </div>
        <div class="info-block">
            <div class="section-label">Payment Details</div>
            <p><b>Method:</b> {{ $payment->payment_method ?? 'N/A' }}</p>
            <p><b>Reference #:</b> <span class="font-mono">{{ $payment->reference_number ?? '—' }}</span></p>
            <p><b>Account Ref:</b> <span class="font-mono">AR-{{ str_pad($payment->customer_id, 4, '0', STR_PAD_LEFT) }}</span></p>
            @if($payment->notes)
                <p><b>Notes:</b> {{ $payment->notes }}</p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="50%">Allocated To Invoice #</th>
                <th width="30%" class="text-right">Allocation Date</th>
                <th width="20%" class="text-right">Amount Applied</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payment->allocations as $alloc)
                <tr>
                    <td>
                        <b class="font-mono">{{ $alloc->invoice->invoice_number }}</b>
                    </td>
                    <td class="text-right">{{ $alloc->created_at->format('M d, Y') }}</td>
                    <td class="text-right font-mono">PHP {{ number_format((float) $alloc->amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center; color: #a0aec0; padding: 20px;">
                        <i>No invoices allocated against this payment.</i>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>Unallocated Credit:</span>
            <span class="font-mono" style="color: #d69e2e;">PHP {{ number_format((float) $payment->unallocated_amount, 2) }}</span>
        </div>
        <div class="total-row grand-total" style="color: #276749;">
            <span>Total Received:</span>
            <span class="font-mono">PHP {{ number_format((float) $payment->amount, 2) }}</span>
        </div>
    </div>

    <div class="signature-sections">
        <div class="sig-box">
            <b>Processed By</b><br>
            {{ auth()->user()->name ?? '_______________________' }}<br>
            <span style="font-size: 11px;">(Date: {{ now()->format('M d, Y') }})</span>
        </div>
        <div class="sig-box">
            <b>Customer / Bearer</b><br>
            _______________________<br>
            <span style="font-size: 11px;">(Name, Date, and Signature)</span>
        </div>
    </div>

    <div class="footer">
        <p>This is a computer-generated Payment Receipt. Thank you for your business.</p>
        <p>Generated by {{ auth()->user()->name ?? 'System' }} on {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
