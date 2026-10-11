<?php

namespace App\Filament\App\Resources\Sales\Actions;

use App\Exceptions\SaleConversionException;
use App\Filament\App\Common\Schemas\SaleBatchConversionForm;
use App\Services\SaleConversionService;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ConvertSalesBulkAction
{
    public static function handle(): BulkAction
    {
        return BulkAction::make('convertBatch')
            ->label('Convert selected')
            ->icon(Heroicon::ArrowsRightLeft)
            ->modalHeading('Convert selected sales')
            ->modalSubmitActionLabel('Convert')
            ->authorize(fn (): bool => ! Auth::user()->isMember(Filament::getTenant()))
            ->disabled(fn (): bool => Filament::getTenant()->currency_code === null)
            ->tooltip(fn (): ?string => Filament::getTenant()->currency_code === null
                ? 'Set your account currency in Account Profile first.'
                : null)
            ->schema(SaleBatchConversionForm::components())
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, BulkAction $action, SaleConversionService $conversions) {
                $account = Filament::getTenant();

                try {
                    $converted = $conversions->convertBatch($account, $records, $data, Auth::user());
                } catch (SaleConversionException $exception) {
                    Notification::make('convertBatchFailed')
                        ->danger()
                        ->title('Sales not converted')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();
                }

                Notification::make('convertBatch')
                    ->success()
                    ->title('Sales converted')
                    ->body("{$converted->count()} sales converted into {$account->currency_code}.")
                    ->send();
            });
    }
}
