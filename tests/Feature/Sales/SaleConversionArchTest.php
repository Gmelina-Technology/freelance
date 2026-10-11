<?php

use App\Filament\App\Common\Actions\Sales\CreateSaleAction;
use App\Filament\App\Common\Actions\Sales\SaveSaleConversionAction;
use App\Filament\App\Resources\Sales\Actions\ConvertSalesBulkAction;
use App\Models\SaleConversion;
use App\Services\InvoiceService;
use App\Services\SaleConversionService;
use App\Services\Sales\SaleConversionBatchItem;
use App\Services\Sales\SaleConversionFigures;
use App\Services\Sales\SaleConversionInput;

arch('sales are created without converting them')
    ->expect([CreateSaleAction::class, InvoiceService::class])
    ->not->toUse([SaleConversionService::class, SaleConversion::class, SaveSaleConversionAction::class]);

arch('the conversion service only orchestrates')
    ->expect(SaleConversionService::class)
    ->not->toUse(['Illuminate\Support\Facades\Validator', 'Illuminate\Support\Facades\DB']);

arch('batch conversion goes through the service')
    ->expect(ConvertSalesBulkAction::class)
    ->not->toUse([SaveSaleConversionAction::class, 'Illuminate\Support\Facades\DB']);

arch('conversion figures are an immutable value object')
    ->expect(SaleConversionFigures::class)
    ->toBeFinal()
    ->toBeReadonly();

arch('batch conversion records are immutable value objects')
    ->expect([SaleConversionInput::class, SaleConversionBatchItem::class])
    ->toBeFinal()
    ->toBeReadonly();
