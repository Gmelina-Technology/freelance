<?php

namespace App\Filament\App\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\App\Resources\Invoices\InvoiceResource;
use App\Services\InvoiceService;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class CreateInvoice extends CreateRecord
{
    use HasWizard;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected static string $resource = InvoiceResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->form->fill([
            ...$this->data,
            'number' => InvoiceService::generateInvoiceNumber(Filament::getTenant()->id),
        ]);
    }

    protected function getSteps(): array
    {
        return [
            $this->detailsStep(),
            $this->invoiceItemsStep()
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
                        ->afterStateUpdated(function (Get $get, callable $set) {
                            $taskId = $get('task_id');
                            if ($taskId) {
                                $set('unit_id', null);
                                $set('quantity', 0);
                                $set('unit_price', 0);
                                $set('sub_total', 0);
                            }
                        })
                        ->relationship('task', 'title', function (Builder $query, Get $get) {
                            $query->when($get('../../project_id'), function (Builder $subQuery, $projectId) {
                                $subQuery->where('project_id', $projectId);
                            });
                        })
                        ->required(),
                    Select::make('unit_id')
                        ->relationship('unit', 'name')
                        ->required(),
                    TextInput::make('quantity')->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, callable $set) {
                            $quantity = $get('quantity');
                            $unitPrice = $get('unit_price');
                            if (is_numeric($quantity) && is_numeric($unitPrice)) {
                                $set('sub_total', $this->calculateSubTotal($quantity, $unitPrice));
                            }
                        })
                        ->minValue(0)
                        ->numeric(),
                    TextInput::make('unit_price')
                        ->live()
                        ->afterStateUpdated(function (Get $get, callable $set) {
                            $quantity = $get('quantity');
                            $unitPrice = $get('unit_price');
                            if (is_numeric($quantity) && is_numeric($unitPrice)) {
                                $set('sub_total', $this->calculateSubTotal($quantity, $unitPrice));
                            }
                        })
                        ->minValue(0)
                        ->required()

                        ->numeric(),
                    TextInput::make('sub_total')->required()
                        ->readOnly()
                        ->numeric(),
                ])
                ->afterStateUpdated(function (array $state, Set $set) {
                    $items = $state ?? [];
                    $totalAmount = collect($items)->sum(fn($item) => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0));
                    $set('amount', $totalAmount);

                    Log::info('Updated invoice items. Total amount: ' . $totalAmount);
                    Log::info('Updated invoice items. Total amount: ', $items);
                })
                ->columns(5)
                ->columnSpanFull(),
            Hidden::make('amount')
                ->required()
                ->default(0),
        ]);
    }

    private function calculateSubTotal(int|float $quantity, int|float $unitPrice): int|float
    {
        return $quantity * $unitPrice;
    }
}
