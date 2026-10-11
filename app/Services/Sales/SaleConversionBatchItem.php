<?php

namespace App\Services\Sales;

use App\Models\Sale;

/**
 * A sale and the validated figures to save for it in a batch conversion.
 */
final readonly class SaleConversionBatchItem
{
    public function __construct(
        public Sale $sale,
        public SaleConversionFigures $figures,
    ) {}
}
