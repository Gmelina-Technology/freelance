<?php

namespace App\Filament\App\Resources\Invoices\Concerns;

use App\Enums\InvoiceStatus;
use App\Enums\TaskBillingStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

trait HasInvoiceSteps
{
    protected function getSteps(): array
    {
        return [
            $this->detailsStep(),
            $this->invoiceItemsStep(),
        ];
    }

    private function detailsStep(): Step
    {
        return Step::make('Details')->schema([
            Group::make([
                TextInput::make('number')
                    ->readOnly(),
                Select::make('client_id')
                    ->relationship('client', 'name')
                    ->live()
                    ->required(),
                Select::make('project_id')
                    ->live()
                    ->required()
                    ->relationship('project', 'name', function (Builder $query, Get $get) {
                        $query->when($get('client_id'), function (Builder $query, $clientId) {
                            $query->where('client_id', $clientId);
                        });
                    }),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ])->columnSpan(5),
            Group::make([
                Select::make('status')
                    ->required()
                    ->options(InvoiceStatus::class)
                    ->default(InvoiceStatus::Draft->value),
                DateTimePicker::make('issued_at')
                    ->default(now()),
                DateTimePicker::make('due_date')
                    ->default(now()->addWeekdays(7)),
            ])->columnSpan(2),
        ])->columns(7);
    }

    private function invoiceItemsStep(): Step
    {
        return Step::make('Invoice Items')->schema([
            Repeater::make('items')
                ->relationship('items')
                ->schema([
                    Select::make('task_id')
                        ->live()
                        ->helperText('Only billable tasks are listed; tasks already invoiced or paid are hidden.')
                        ->afterStateUpdated(function (Get $get, callable $set) {
                            $taskId = $get('task_id');
                            if ($taskId) {
                                $set('unit_id', null);
                                $set('quantity', 0);
                                $set('unit_price', 0);
                                $set('amount', 0);
                            }
                        })
                        ->relationship('task', 'title', function (Builder $query, Get $get) {
                            $query->when($get('../../project_id'), function (Builder $subQuery, $projectId) {
                                $subQuery->where('project_id', $projectId);
                            });

                            // Tasks already on the invoice being edited stay selectable.
                            $ownTaskIds = $this->record?->items()->pluck('task_id')->filter()->all() ?? [];

                            $query->where(function (Builder $subQuery) use ($ownTaskIds) {
                                $subQuery->where('billing_status', TaskBillingStatus::Billable)
                                    ->orWhereIn('id', $ownTaskIds);
                            });
                        })
                        ->required(),
                    Select::make('unit_id')
                        ->relationship('unit', 'name')
                        ->required(),
                    TextInput::make('quantity')->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => $this->updateSubTotal($get, $set))
                        ->minValue(0)
                        ->numeric(),
                    TextInput::make('unit_price')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => $this->updateSubTotal($get, $set))
                        ->minValue(0)
                        ->required()
                        ->numeric(),
                    TextInput::make('amount')
                        ->label('Sub Total')
                        ->readOnly()
                        ->dehydrated(false)
                        ->numeric(),
                ])
                ->live()
                ->afterStateUpdated(function (?array $state, Set $set) {
                    $set('amount', collect($state ?? [])->sum(
                        fn ($item) => Number::parseFloat($item['unit_price'] ?? 0) * Number::parseFloat($item['quantity'] ?? 0)
                    ));
                })
                ->columns(5)
                ->columnSpanFull(),
            Hidden::make('amount')
                ->required()
                ->default(0),
        ]);
    }

    private function updateSubTotal(Get $get, Set $set): void
    {
        $quantity = $get('quantity');
        $unitPrice = $get('unit_price');

        if (is_numeric($quantity) && is_numeric($unitPrice)) {
            $set('amount', (float) $quantity * (float) $unitPrice);
        }
    }
}
