<?php

namespace App\Filament\App\Resources\Sales\Pages;

use App\Filament\App\Resources\Sales\SaleResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    public function getSubheading(): ?string
    {
        return Filament::getTenant()->currency_code === null
            ? 'Set your account currency in Account Profile to convert sales.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
