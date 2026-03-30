<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ReorderRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReorderRuleController extends Controller
{
    /**
     * GET /api/reorder-rules?product_id=X
     * List all reorder rules for a product.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReorderRule::with(['product', 'location']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        return response()->json($query->get()->map(fn (ReorderRule $rule) => $this->format($rule)));
    }

    /**
     * POST /api/reorder-rules
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'location_id' => 'nullable|exists:locations,id',
            'min_stock' => 'required|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'reorder_qty' => 'required|numeric|min:1',
            'is_active' => 'boolean',
        ]);

        // Prevent duplicate rules for the same product+location combination
        $exists = ReorderRule::where('product_id', $data['product_id'])
            ->where('location_id', $data['location_id'] ?? null)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A reorder rule for this product and location already exists.',
            ], 422);
        }

        $rule = ReorderRule::create($data);
        $rule->load(['product', 'location']);

        return response()->json($this->format($rule), 201);
    }

    /**
     * PUT /api/reorder-rules/{rule}
     */
    public function update(Request $request, ReorderRule $reorderRule): JsonResponse
    {
        $data = $request->validate([
            'min_stock' => 'sometimes|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'reorder_qty' => 'sometimes|numeric|min:1',
            'is_active' => 'boolean',
        ]);

        $reorderRule->update($data);
        $reorderRule->load(['product', 'location']);

        return response()->json($this->format($reorderRule));
    }

    /**
     * DELETE /api/reorder-rules/{rule}
     */
    public function destroy(ReorderRule $reorderRule): JsonResponse
    {
        $reorderRule->delete();

        return response()->json(['message' => 'Reorder rule deleted.']);
    }

    private function format(ReorderRule $rule): array
    {
        return [
            'id' => $rule->id,
            'product_id' => $rule->product_id,
            'product_name' => $rule->product?->name,
            'location_id' => $rule->location_id,
            'location_name' => $rule->location?->name ?? 'All Locations (Global)',
            'min_stock' => (float) $rule->min_stock,
            'max_stock' => $rule->max_stock ? (float) $rule->max_stock : null,
            'reorder_qty' => (float) $rule->reorder_qty,
            'is_active' => (bool) $rule->is_active,
            'created_at' => $rule->created_at?->format('Y-m-d'),
        ];
    }
}
