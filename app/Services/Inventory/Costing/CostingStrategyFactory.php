<?php

namespace App\Services\Inventory\Costing;

use App\Models\Product;
use App\Services\Inventory\Costing\Strategies\AverageCostingStrategy;
use App\Services\Inventory\Costing\Strategies\FifoCostingStrategy;
use App\Services\Inventory\Costing\Strategies\LifoCostingStrategy;
use LogicException;

class CostingStrategyFactory
{
    /**
     * Resolve the costing strategy for a given product based on its configuration.
     */
    public static function resolve(Product $product): CostingStrategy
    {
        // Snapshot the costing method name to resolve strategy
        $method = strtolower($product->costingMethod->name ?? 'fifo');

        return match ($method) {
            'fifo' => new FifoCostingStrategy,
            'lifo' => new LifoCostingStrategy,
            'average' => new AverageCostingStrategy,
            default => throw new LogicException("Unsupported costing method: {$method}"),
        };
    }
}
