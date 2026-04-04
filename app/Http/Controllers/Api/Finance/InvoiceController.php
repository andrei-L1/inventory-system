<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceLine;
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
        return response()->json($invoice->load(['customer', 'salesOrder', 'lines.product']));
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

                $totalAmount = 0;
                foreach ($request->lines as $item) {
                    $soLine = SalesOrderLine::findOrFail($item['so_line_id']);
                    $qty = (float) $item['quantity'];

                    // Validation: cannot invoice more than was shipped? 
                    // Actually, business rules vary, but usually you invoice what was shipped.
                    // For now, we'll allow it but maybe add a warning later.

                    $subtotal = $qty * (float) $soLine->unit_price;
                    $totalAmount += $subtotal;

                    $invoice->lines()->create([
                        'sales_order_line_id' => $soLine->id,
                        'product_id' => $soLine->product_id,
                        'quantity' => $qty,
                        'unit_price' => $soLine->unit_price,
                        'subtotal' => $subtotal,
                    ]);
                }

                $invoice->update(['total_amount' => $totalAmount]);

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
}
