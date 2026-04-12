<?php

namespace App\Http\Controllers\Api\Finance;

use App\Helpers\FinancialMath;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer', 'salesOrder'])->latest();

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->paginate($request->get('limit', 15)));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'salesOrder', 'lines.product']);
        // Serialize dates as plain Y-m-d strings for the document viewer
        $data = $invoice->toArray();
        $data['invoice_date'] = $invoice->invoice_date?->format('Y-m-d');
        $data['due_date'] = $invoice->due_date?->format('Y-m-d');

        return response()->json($data);
    }

    public function print(Invoice $invoice)
    {
        $invoice->load(['customer', 'salesOrder', 'lines.product']);

        $company = [
            'name' => config('app.company_name', 'Nexus Logistics Corp.'),
            'address' => config('app.company_address', '123 Corporate Ave, Matrix City'),
            'phone' => config('app.company_phone', '+1 (800) 000-0000'),
            'email' => config('app.company_email', 'accounting@nexuscorp.com'),
            'website' => config('app.company_website', 'www.nexuscorp.com'),
            'tax_id' => config('app.company_tax_id'), // Dynamic tax ID or N/A
        ];

        return view('finance.invoice-print', compact('invoice', 'company'));
    }

    /**
     * Generate an invoice from a Sales Order.
     * Supports partial invoicing by providing specific line IDs and quantities.
     */
    public function storeFromSalesOrder(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $request->validate([
            'lines' => 'required|array',
            'lines.*.so_line_id' => 'required|exists:sales_order_lines,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
        ]);

        try {
            $invoice = DB::transaction(function () use ($request, $salesOrder) {
                $invoice = Invoice::create([
                    'invoice_number' => 'INV-'.now()->format('Ymd-Hi').'-'.rand(10, 99),
                    'customer_id' => $salesOrder->customer_id,
                    'sales_order_id' => $salesOrder->id,
                    'invoice_date' => $request->invoice_date,
                    'due_date' => $request->due_date,
                    'status' => Invoice::STATUS_DRAFT,
                    'type' => Invoice::TYPE_INVOICE,
                ]);

                $lineTotals = [];
                foreach ($request->lines as $item) {
                    $soLine = SalesOrderLine::findOrFail($item['so_line_id']);
                    $qty = (string) $item['quantity'];

                    // Hard Validation: Cannot invoice more than was physically shipped minus already invoiced.
                    $uninvoicedQty = $soLine->uninvoiced_qty;
                    if (FinancialMath::gt($qty, $uninvoicedQty)) {
                        if (FinancialMath::isZero($uninvoicedQty)) {
                            abort(422, "Product '{$soLine->product->name}' is already fully invoiced for all shipped quantities.");
                        }
                        abort(422, "Cannot invoice {$qty} for product '{$soLine->product->name}'. Only {$uninvoicedQty} items remain uninvoiced.");
                    }

                    // Precise calculation using FinancialMath source of truth
                    $subtotal = FinancialMath::soLineSubtotal(
                        $qty,
                        (string) $soLine->unit_price,
                        (string) $soLine->discount_rate,
                        (string) $soLine->tax_rate
                    );

                    $taxAmount = FinancialMath::soLineTax(
                        $qty,
                        (string) $soLine->unit_price,
                        (string) $soLine->discount_rate,
                        (string) $soLine->tax_rate
                    );

                    $discountAmount = FinancialMath::soLineDiscount(
                        $qty,
                        (string) $soLine->unit_price,
                        (string) $soLine->discount_rate
                    );

                    $invoice->lines()->create([
                        'sales_order_line_id' => $soLine->id,
                        'product_id' => $soLine->product_id,
                        'quantity' => $qty,
                        'unit_price' => $soLine->unit_price,
                        'tax_rate' => $soLine->tax_rate,
                        'tax_amount' => $taxAmount,
                        'discount_rate' => $soLine->discount_rate,
                        'discount_amount' => $discountAmount,
                        'subtotal' => $subtotal,
                    ]);

                    $lineTotals[] = $subtotal;
                }

                // Final Header Rounding (The "Dust" Slayer)
                // Rounds the accumulated high-precision sum to exactly 2 decimal places.
                $invoice->update(['total_amount' => FinancialMath::headerTotal($lineTotals)]);

                return $invoice;
            });

            return response()->json([
                'message' => 'Invoice created successfully.',
                'invoice' => $invoice->load('lines'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function post(Invoice $invoice): JsonResponse
    {
        if (! $invoice->isDraft()) {
            return response()->json(['message' => 'Only draft invoices can be posted.'], 400);
        }

        $invoice->update(['status' => Invoice::STATUS_OPEN]);

        return response()->json([
            'message' => 'Invoice posted successfully.',
            'invoice' => $invoice,
        ]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        if (! $invoice->isDraft()) {
            return response()->json(['message' => 'Only draft invoices can be deleted.'], 400);
        }

        $invoice->delete();

        return response()->json(null, 204);
    }

    /**
     * Void an OPEN invoice.
     * This zeroes out expected AR balance but keeps the invoice for auditing.
     */
    public function void(Invoice $invoice): JsonResponse
    {
        if (! $invoice->isOpen()) {
            return response()->json(['message' => 'Only OPEN invoices can be voided.'], 400);
        }

        if (FinancialMath::gt((string) $invoice->paid_amount, '0')) {
            return response()->json(['message' => 'Cannot void an invoice that has payments allocated. Remove payments first.'], 422);
        }

        $invoice->update([
            'status' => Invoice::STATUS_VOID,
            'notes' => trim(($invoice->notes ?? '').' [VOIDED on '.now()->toDateString().']'),
            // We do NOT technically alter total_amount because it represents the original invoice value,
            // but for statements, VOIDED invoices are ignored anyway.
        ]);

        return response()->json([
            'message' => 'Invoice voided successfully.',
            'invoice' => $invoice,
        ]);
    }
}
