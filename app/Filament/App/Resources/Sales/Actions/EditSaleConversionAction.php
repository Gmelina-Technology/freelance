<?php

namespace App\Filament\App\Resources\Sales\Actions;

use App\Exceptions\SaleConversionException;
use App\Filament\App\Common\Schemas\SaleConversionForm;
use App\Models\Sale;
use App\Services\SaleConversionService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EditSaleConversionAction
{
    public static function handle(): Action
    {
        return Action::make('editConversion')
            ->label('Edit conversion')
            ->icon(Heroicon::PencilSquare)
            ->modalHeading('Edit conversion')
            ->authorize(fn (): bool => ! Auth::user()->isMember(Filament::getTenant()))
            ->visible(fn (Sale $record): bool => $record->isConverted())
            ->disabled(fn (Sale $record): bool => self::blockedReason($record) !== null)
            ->tooltip(fn (Sale $record): ?string => self::blockedReason($record))
            ->fillForm(fn (Sale $record): array => $record->saleConversion->only([
                'converted_amount',
                'fee_amount',
                'tax_amount',
                'conversion_note',
            ]))
            ->schema(SaleConversionForm::components())
            ->action(function (Sale $record, array $data, Action $action, SaleConversionService $conversions) {
                try {
                    $conversions->convert($record, $data, Auth::user());
                } catch (SaleConversionException $exception) {
                    Notification::make('editConversionFailed')
                        ->danger()
                        ->title('Conversion not updated')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();
                }

                Notification::make('editConversion')
                    ->success()
                    ->title('Conversion updated')
                    ->send();
            });
    }

    private static function blockedReason(Sale $record): ?string
    {
        $accountCurrencyCode = Filament::getTenant()->currency_code;

        if ($accountCurrencyCode === null) {
            return SaleConversionException::missingAccountCurrency()->getMessage();
        }

        $convertedCurrencyCode = $record->saleConversion?->converted_currency_code;

        if ($convertedCurrencyCode !== null && $convertedCurrencyCode !== $accountCurrencyCode) {
            return SaleConversionException::accountCurrencyChanged($convertedCurrencyCode, $accountCurrencyCode)->getMessage();
        }

        return null;
    }
}
