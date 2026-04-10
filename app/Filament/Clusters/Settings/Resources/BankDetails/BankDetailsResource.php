<?php

namespace App\Filament\Clusters\Settings\Resources\BankDetails;

use App\Filament\Clusters\Settings\Resources\BankDetails\Pages\ListBankDetails;
use App\Filament\Clusters\Settings\Resources\BankDetails\Schemas\BankDetailsForm;
use App\Filament\Clusters\Settings\Resources\BankDetails\Tables\BankDetailsTable;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\BankDetail;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BankDetailsResource extends Resource
{
    protected static ?string $model = BankDetail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::CreditCard;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $recordTitleAttribute = 'bank_name';

    public static function form(Schema $schema): Schema
    {
        return BankDetailsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankDetailsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankDetails::route('/'),
        ];
    }
}
