<?php

namespace App\Filament\App\Resources\Sales\Actions;

use App\Enums\AccountCapability;
use App\Exceptions\SaleConversionException;
use App\Filament\App\Common\Schemas\SaleConversionForm;
use App\Models\Sale;
use App\Services\SaleConversionService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ConvertSaleAction
{
    public static function handle(): Action
    {
        return Action::make('convert')
            ->label('Convert')
            ->icon(Heroicon::ArrowsRightLeft)
            ->modalHeading('Convert sale')
            ->modalSubmitActionLabel('Convert')
            ->authorize(fn (): bool => Auth::user()->can(AccountCapability::ManageSales, Filament::getTenant()))
            ->visible(fn (Sale $record): bool => ! $record->isConverted())
            ->disabled(fn (): bool => Filament::getTenant()->currency_code === null)
            ->tooltip(fn (): ?string => Filament::getTenant()->currency_code === null
                ? 'Set your account currency in Account Profile first.'
                : null)
            ->fillForm(fn (Sale $record): array => $record->currency_code === Filament::getTenant()->currency_code
                ? ['converted_amount' => $record->amount]
                : [])
            ->schema(SaleConversionForm::components())
            ->action(function (Sale $record, array $data, Action $action, SaleConversionService $conversions) {
                try {
                    $conversions->convert($record, $data, Auth::user());
                } catch (SaleConversionException $exception) {
                    Notification::make('convertFailed')
                        ->danger()
                        ->title('Sale not converted')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();
                }

                Notification::make('convert')
                    ->success()
                    ->title('Sale converted')
                    ->send();
            });
    }
}
