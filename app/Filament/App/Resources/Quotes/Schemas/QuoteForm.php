<?php

namespace App\Filament\App\Resources\Quotes\Schemas;

use App\Enums\EmailTemplateType;
use App\Enums\QuoteStatus;
use App\Models\EmailTemplate;
use App\Models\Quote;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::detailsComponents(),
                ...self::itemComponents(),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    public static function detailsComponents(): array
    {
        return [
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
                FileUpload::make('attachment_path')
                    ->label('Custom attachment')
                    ->helperText('Sent to the client instead of the generated quote PDF. Leave empty to send the generated PDF.')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->disk(Quote::ATTACHMENT_DISK)
                    ->directory('quote-attachments')
                    ->visibility('private')
                    ->downloadable()
                    ->visible(fn (): bool => self::customAttachmentEnabled())
                    ->columnSpanFull(),
            ])->columnSpan(5),
            Group::make([
                // Status only changes through the quote actions, so accepting always generates tasks.
                Select::make('status')
                    ->options(QuoteStatus::class)
                    ->default(QuoteStatus::Draft->value)
                    ->disabled()
                    ->dehydrated(),
                DateTimePicker::make('issued_at')
                    ->default(now()),
                DateTimePicker::make('valid_until')
                    ->default(now()->addDays(30)),
            ])->columnSpan(2),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function itemComponents(): array
    {
        return [
            Repeater::make('items')
                ->relationship('items')
                ->orderColumn('sort_order')
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minItems(fn (string $operation): int => $operation === 'create' ? 1 : 0)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->columnSpan(2),
                    Select::make('category_id')
                        ->relationship('category', 'name'),
                    Select::make('unit_id')
                        ->relationship('unit', 'name')
                        ->required(),
                    TextInput::make('quantity')
                        ->required()
                        ->default(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::updateSubTotal($get, $set))
                        ->minValue(0)
                        ->numeric(),
                    TextInput::make('unit_price')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::updateSubTotal($get, $set))
                        ->minValue(0)
                        ->numeric(),
                    TextInput::make('amount')
                        ->label('Sub Total')
                        ->readOnly()
                        ->dehydrated(false)
                        ->numeric(),
                    Textarea::make('description')
                        ->columnSpanFull(),
                ])
                ->live()
                ->afterStateUpdated(function (?array $state, Set $set) {
                    $set('amount', collect($state ?? [])->sum(
                        fn ($item) => Number::parseFloat($item['unit_price'] ?? 0) * Number::parseFloat($item['quantity'] ?? 0)
                    ));
                })
                ->columns(6)
                ->columnSpanFull(),
            Hidden::make('amount')
                ->default(0),
        ];
    }

    /**
     * Whether the current account's quote email template allows uploading a custom attachment.
     */
    private static function customAttachmentEnabled(): bool
    {
        return EmailTemplate::query()
            ->where('account_id', Filament::getTenant()->getKey())
            ->where('type', EmailTemplateType::QUOTE_REQUEST)
            ->first()
            ?->featureEnabled('custom_attachment') ?? false;
    }

    private static function updateSubTotal(Get $get, Set $set): void
    {
        $quantity = $get('quantity');
        $unitPrice = $get('unit_price');

        if (is_numeric($quantity) && is_numeric($unitPrice)) {
            $set('amount', (float) $quantity * (float) $unitPrice);
        }
    }
}
