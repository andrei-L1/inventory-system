<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Helpers\FinancialMath;
use App\Http\Controllers\Controller;
use App\Models\InventoryCostLayer;
use App\Models\LandedCost;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LandedCostController extends Controller
{
    // ─── Index ─────────────────────────────────────────────────────────────────

    /**
     * List all landed costs for a given Purchase Order.
     */
    public function index(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $costs = $purchaseOrder->landedCosts()
            ->with(['product:id,name,sku', 'allocatedByUser:id,name'])
            ->orderBy('created_at')
            ->get()
            ->map(fn ($lc) => $this->format($lc));

        return response()->json(['data' => $costs]);
    }

    // ─── Store ─────────────────────────────────────────────────────────────────

    /**
     * Record a new landed cost charge against a Purchase Order.
     */
    public function store(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validated = $request->validate([
            'cost_type' => ['required', 'string', Rule::in(LandedCost::COST_TYPES)],
            'amount' => 'required|numeric|min:0.00000001',
            'notes' => 'nullable|string|max:500',
        ]);

        $cost = $purchaseOrder->landedCosts()->create([
            'cost_type' => $validated['cost_type'],
            'amount' => FinancialMath::round((string) $validated['amount'], FinancialMath::LINE_SCALE),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['data' => $this->format($cost)], 201);
    }

    // ─── Destroy ───────────────────────────────────────────────────────────────

    /**
     * Delete a landed cost — only allowed if not yet allocated to cost layers.
     */
    public function destroy(PurchaseOrder $purchaseOrder, LandedCost $landedCost): JsonResponse
    {
        abort_if($landedCost->purchase_order_id !== $purchaseOrder->id, 404);
        abort_if(
            $landedCost->is_allocated,
            409,
            'This landed cost has already been applied to inventory cost layers and cannot be deleted.'
        );

        $landedCost->delete();

        return response()->json(['message' => 'Landed cost deleted successfully.']);
    }

    // ─── Allocate ──────────────────────────────────────────────────────────────

    /**
     * Prorate and apply a landed cost into the matching inventory_cost_layers.
     *
     * Supports two methods:
     *   - by_value:    proportional to (net_received_qty × unit_cost) per PO line.
     *   - by_quantity: equal per-unit adjustment across all received units.
     *
     * Precision: All arithmetic uses FinancialMath (BCMath) at 8-decimal scale.
     * Idempotency guard: A cost can only be allocated once.
     */
    public function allocate(Request $request, PurchaseOrder $purchaseOrder, LandedCost $landedCost): JsonResponse
    {
        abort_if($landedCost->purchase_order_id !== $purchaseOrder->id, 404);
        abort_if(
            $landedCost->is_allocated,
            409,
            'This landed cost has already been allocated.'
        );

        $request->validate([
            'method' => ['required', Rule::in([LandedCost::METHOD_BY_VALUE, LandedCost::METHOD_BY_QUANTITY])],
        ]);

        $method = $request->method;

        // Load PO lines that have actually been received
        $purchaseOrder->loadMissing('lines.product');
        $receivedLines = $purchaseOrder->lines->filter(
            fn ($line) => FinancialMath::isPositive((string) $line->net_received_qty)
        );

        if ($receivedLines->isEmpty()) {
            abort(422, 'No received lines found on this PO. Receive stock before allocating landed costs.');
        }

        $totalCost = FinancialMath::toDecimal((string) $landedCost->amount);

        DB::transaction(function () use ($landedCost, $receivedLines, $totalCost, $method, $purchaseOrder) {

            // ── Step 1: Calculate the denominator for proration ──────────────
            if ($method === LandedCost::METHOD_BY_VALUE) {
                // denominator = Σ (net_received_qty × unit_cost)
                $denominator = '0';
                foreach ($receivedLines as $line) {
                    $lineValue = FinancialMath::mul(
                        (string) $line->net_received_qty,
                        (string) $line->unit_cost,
                    );
                    $denominator = FinancialMath::add($denominator, $lineValue);
                }
            } else {
                // denominator = Σ net_received_qty (total received units)
                $denominator = '0';
                foreach ($receivedLines as $line) {
                    $denominator = FinancialMath::add($denominator, (string) $line->net_received_qty);
                }
            }

            abort_if(
                FinancialMath::isZero($denominator),
                422,
                'Cannot allocate: denominator is zero (no value or quantity to prorate against).'
            );

            // ── Step 2: Apply proportional per-unit adjustment to cost layers ─
            foreach ($receivedLines as $line) {
                if ($method === LandedCost::METHOD_BY_VALUE) {
                    $lineValue = FinancialMath::mul(
                        (string) $line->net_received_qty,
                        (string) $line->unit_cost,
                    );
                    $proportion = FinancialMath::div($lineValue, $denominator);
                    $lineShare = FinancialMath::mul($totalCost, $proportion);
                    $perUnitAddition = FinancialMath::div($lineShare, (string) $line->net_received_qty);
                } else {
                    // by_quantity: uniform per-unit split
                    $perUnitAddition = FinancialMath::div($totalCost, $denominator);
                }

                $perUnitAddition = FinancialMath::round($perUnitAddition, FinancialMath::LINE_SCALE);

                // Find all non-exhausted cost layers for this product on this PO's GRN transactions
                $layers = InventoryCostLayer::whereHas('transactionLine.transaction', function ($q) use ($purchaseOrder) {
                    $q->where('purchase_order_id', $purchaseOrder->id);
                })
                    ->where('product_id', $line->product_id)
                    ->lockForUpdate()
                    ->get();

                foreach ($layers as $layer) {
                    $newUnitCost = FinancialMath::round(
                        FinancialMath::add((string) $layer->unit_cost, $perUnitAddition),
                        FinancialMath::LINE_SCALE
                    );
                    $layer->update(['unit_cost' => $newUnitCost]);
                }
            }

            // ── Step 3: Stamp allocation metadata ─────────────────────────────
            $landedCost->update([
                'allocation_method' => $method,
                'allocated_at' => now(),
                'allocated_by' => auth()->id(),
            ]);
        });

        $landedCost->refresh();

        return response()->json([
            'message' => 'Landed cost successfully allocated into inventory cost layers.',
            'data' => $this->format($landedCost),
        ]);
    }

    // ─── Private Helpers ───────────────────────────────────────────────────────

    private function format(LandedCost $lc): array
    {
        return [
            'id' => $lc->id,
            'purchase_order_id' => $lc->purchase_order_id,
            'product_id' => $lc->product_id,
            'product' => $lc->product ? ['id' => $lc->product->id, 'name' => $lc->product->name, 'sku' => $lc->product->sku] : null,
            'cost_type' => $lc->cost_type,
            'amount' => (string) $lc->amount,
            'formatted_amount' => FinancialMath::format($lc->amount, 2),
            'notes' => $lc->notes,
            'is_allocated' => $lc->is_allocated,
            'allocation_method' => $lc->allocation_method,
            'allocated_at' => $lc->allocated_at?->toDateTimeString(),
            'allocated_by' => $lc->allocatedByUser?->name,
            'created_at' => $lc->created_at?->toDateTimeString(),
        ];
    }
}
