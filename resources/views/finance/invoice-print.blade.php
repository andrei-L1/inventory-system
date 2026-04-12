<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->type === 'CREDIT_NOTE' ? 'Credit Note' : 'Tax Invoice' }} - {{ $invoice->invoice_number }}</title>
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
            color: #3182ce;
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
            width: 320px;
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
        .total-row.balance-row {
            border-top: 2px solid #2d3748;
            border-bottom: none;
            font-weight: 800;
            font-size: 20px;
            margin-top: 4px;
        }
        .paid-stamp {
            display: inline-block;
            border: 3px solid #38a169;
            color: #38a169;
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 0.2em;
            padding: 6px 18px;
            text-transform: uppercase;
            transform: rotate(-5deg);
            margin-top: 10px;
            opacity: 0.8;
        }
        .void-stamp {
            display: inline-block;
            border: 3px solid #e53e3e;
            color: #e53e3e;
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 0.2em;
            padding: 6px 18px;
            text-transform: uppercase;
            transform: rotate(-5deg);
            margin-top: 10px;
            opacity: 0.8;
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
        }
        .status-draft  { background: #edf2f7; color: #718096; }
        .status-open   { background: #ebf8ff; color: #3182ce; }
        .status-paid   { background: #f0fff4; color: #38a169; }
        .status-void   { background: #fff5f5; color: #e53e3e; }

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
            <h2>{{ $invoice->type === 'CREDIT_NOTE' ? 'Credit Note' : 'Tax Invoice' }}</h2>
            <p class="font-mono"># {{ $invoice->invoice_number }}</p>
            <p>Date: {{ optional($invoice->invoice_date)->format('M d, Y') }}</p>
            @if($invoice->due_date)
                <p>Due: {{ optional($invoice->due_date)->format('M d, Y') }}</p>
            @endif
            <br>
            <span class="status-badge status-{{ strtolower($invoice->status) }}">{{ $invoice->status }}</span>
        </div>
    </div>

    <div class="details-grid">
        <div class="info-block">
            <div class="section-label">Bill To</div>
            <p><b>{{ $invoice->customer->name }}</b></p>
            <p class="font-mono" style="color: #718096; font-size: 12px;">{{ $invoice->customer->customer_code }}</p>
            <p>{{ $invoice->customer->billing_address ?? 'Billing Address not specified (N/A)' }}</p>
            @if($invoice->customer->email)
                <p>Email: {{ $invoice->customer->email }}</p>
            @endif
            <p><b>TIN:</b> {{ $invoice->customer->tax_number ?? 'N/A' }}</p>
        </div>
        <div class="info-block">
            <div class="section-label">Invoice Details</div>
            @if($invoice->salesOrder)
                <p><b>Sales Order:</b> <span class="font-mono">{{ $invoice->salesOrder->so_number }}</span></p>
            @endif
            <p><b>Invoice Date:</b> {{ optional($invoice->invoice_date)->format('M d, Y') }}</p>
            <p><b>Due Date:</b> {{ optional($invoice->due_date)->format('M d, Y') ?? 'Upon Receipt' }}</p>
            <p><b>Account Ref:</b> <span class="font-mono">AR-{{ str_pad($invoice->customer_id, 4, '0', STR_PAD_LEFT) }}</span></p>
            @if($invoice->notes)
                <p><b>Notes:</b> {{ $invoice->notes }}</p>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="45%">Item Description</th>
                <th width="15%" class="text-right">Qty</th>
                <th width="17%" class="text-right">Unit Price</th>
                <th width="18%" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $index => $line)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <b>{{ $line->product->name }}</b>
                        @if($line->product->sku)
                            <br><small class="font-mono" style="color: #718096;">SKU: {{ $line->product->sku }}</small>
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ number_format((float) $line->quantity, 2) }}</td>
                    <td class="text-right font-mono">PHP {{ number_format((float) $line->unit_price, 2) }}</td>
                    <td class="text-right font-mono">PHP {{ number_format((float) $line->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal:</span>
            <span class="font-mono">PHP {{ number_format((float) $invoice->total_amount, 2) }}</span>
        </div>
        <div class="total-row">
            <span>Tax:</span>
            <span class="font-mono">PHP 0.00</span>
        </div>
        <div class="total-row grand-total">
            <span>Total Charged:</span>
            <span class="font-mono">PHP {{ number_format((float) $invoice->total_amount, 2) }}</span>
        </div>
        <div class="total-row" style="margin-top: 12px;">
            <span>Amount Paid:</span>
            <span class="font-mono" style="color: #38a169;">- PHP {{ number_format((float) $invoice->paid_amount, 2) }}</span>
        </div>
        <div class="total-row balance-row">
            <span>Balance Due:</span>
            <span class="font-mono">PHP {{ number_format((float) $invoice->balance_due, 2) }}</span>
        </div>
        @if($invoice->status === 'PAID')
            <div style="text-align: right; margin-top: 10px;">
                <span class="paid-stamp">Paid in Full</span>
            </div>
        @elseif($invoice->status === 'VOID')
            <div style="text-align: right; margin-top: 10px;">
                <span class="void-stamp">Void</span>
            </div>
        @endif
    </div>

    <div class="signature-sections">
        <div class="sig-box">
            <b>Prepared By</b><br>
            {{ auth()->user()->name ?? '_______________________' }}<br>
            <span style="font-size: 11px;">(Date: {{ now()->format('M d, Y') }})</span>
        </div>
        <div class="sig-box">
            <b>Received By</b><br>
            _______________________<br>
            <span style="font-size: 11px;">(Name, Date, and Signature)</span>
        </div>
    </div>

    <div class="footer">
        <p>This is a computer-generated Tax Invoice. Thank you for your business.</p>
        <p>Generated by {{ auth()->user()->name ?? 'System' }} on {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
