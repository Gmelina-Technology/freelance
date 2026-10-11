<?php

use App\Filament\App\Common\Actions\Sales\CreateSaleAction;
use App\Filament\App\Common\Actions\Sales\SaveSaleConversionAction;
use App\Models\SaleConversion;
use App\Services\InvoiceService;
use App\Services\SaleConversionService;

arch('sales are created without converting them')
    ->expect([CreateSaleAction::class, InvoiceService::class])
    ->not->toUse([SaleConversionService::class, SaleConversion::class, SaveSaleConversionAction::class]);

arch('the conversion service only orchestrates')
    ->expect(SaleConversionService::class)
    ->not->toUse(['Illuminate\Support\Facades\Validator', 'Illuminate\Support\Facades\DB']);
