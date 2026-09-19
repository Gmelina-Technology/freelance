<?php

namespace App\Filament\App\Resources\Quotes\Schemas;

use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuoteInfolist
{
    public static function configure(Schema $schema)
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('Quote Details')->schema([
                        TextEntry::make('client.name')->label('Client'),
                        TextEntry::make('project.name')->label('Project'),
                        TextEntry::make('status')->label('Status')
                            ->badge(),
                    ])->columns(3),

                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->state(fn ($record) => $record->items()->with(['category', 'unit', 'tasks'])->get())
                        ->table([
                            TableColumn::make('Item'),
                            TableColumn::make('Unit'),
                            TableColumn::make('Quantity'),
                            TableColumn::make('Unit Price'),
                            TableColumn::make('Sub Total'),
                            TableColumn::make('Task Status'),
                            TableColumn::make('Billing Status'),
                        ])
                        ->schema([
                            TextEntry::make('title')
                                ->aboveContent(fn ($record) => $record->category?->name),
                            TextEntry::make('unit.name'),
                            TextEntry::make('quantity'),
                            TextEntry::make('unit_price'),
                            TextEntry::make('amount'),
                            TextEntry::make('task_status')
                                ->state(fn ($record) => $record->tasks->first()?->status)
                                ->badge()
                                ->placeholder('-'),
                            TextEntry::make('billing_status')
                                ->state(fn ($record) => $record->tasks->first()?->billing_status)
                                ->badge()
                                ->placeholder('-'),
                        ]),
                    Section::make('Total Payment Summary')
                        ->schema([
                            TextEntry::make('amount')
                                ->label('Total Amount')
                                ->inlineLabel(),
                        ]),
                ])->columnSpan(5),
                Section::make('Other Details')
                    ->schema([
                        TextEntry::make('issued_at')->label('Issued At')
                            ->dateTime()
                            ->inlineLabel(),
                        TextEntry::make('valid_until')->label('Valid Until')
                            ->dateTime()
                            ->inlineLabel(),
                        TextEntry::make('invoice_ref')->label('Invoice')
                            ->placeholder('-')
                            ->inlineLabel(),
                        TextEntry::make('notes')->label('Notes'),
                    ])->columnSpan(2),

            ])->columns([
                'sm' => 1,
                'md' => 7,
                'lg' => 7,
            ]);
    }
}
