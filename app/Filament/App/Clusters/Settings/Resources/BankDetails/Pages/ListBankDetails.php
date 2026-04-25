<?php

namespace App\Filament\App\Clusters\Settings\Resources\BankDetails\Pages;

use App\Filament\App\Clusters\Settings\Resources\BankDetails\BankDetailsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBankDetails extends ListRecords
{
    protected static string $resource = BankDetailsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
