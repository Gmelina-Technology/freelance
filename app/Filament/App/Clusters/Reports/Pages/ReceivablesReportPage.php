<?php

namespace App\Filament\App\Clusters\Reports\Pages;

use App\Filament\App\Clusters\Reports\ReportsCluster;
use App\Services\Reports\ReceivablesReportService;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Livewire\Attributes\Computed;

class ReceivablesReportPage extends Page
{
    protected static ?string $cluster = ReportsCluster::class;

    protected static ?string $slug = 'receivables';

    protected static ?string $navigationLabel = 'Receivables';

    protected static ?string $title = 'Receivables';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return ReportsCluster::canAccess();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components(fn (): array => [
            Text::make('As of now. Pending covers every outstanding invoice (Sent and Overdue) and is what is owed: Owed equals Pending.'),
            Text::make('"Of which overdue" is the part of Pending with status Overdue, plus Sent invoices whose due date is before today.'),
            Text::make('Collected (Paid) is shown separately and is not owed. Draft and Void invoices are not included.'),
            ...$this->reportComponents(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function report(): array
    {
        return app(ReceivablesReportService::class)->forAccount(Filament::getTenant());
    }

    /**
     * @return list<Component>
     */
    protected function reportComponents(): array
    {
        if ($this->report === []) {
            return [Text::make('No outstanding or collected invoices.')];
        }

        return array_map(fn (array $client): Section => Section::make($client['client_name'])
            ->key("client-{$client['client_id']}")
            ->schema([
                Grid::make(3)->schema([
                    TextEntry::make('owed')
                        ->label('Owed (pending)')
                        ->state($client['owed'])
                        ->money($client['currency_code']),
                    TextEntry::make('overdue')
                        ->label('Of which overdue')
                        ->state($client['overdue'])
                        ->money($client['currency_code']),
                    TextEntry::make('collected')
                        ->label('Collected')
                        ->state($client['collected'])
                        ->money($client['currency_code']),
                ]),
                RepeatableEntry::make('projects')
                    ->key("client-{$client['client_id']}-projects")
                    ->hiddenLabel()
                    ->state($client['projects'])
                    ->table([
                        TableColumn::make('Project'),
                        TableColumn::make('Pending'),
                        TableColumn::make('Of which overdue'),
                        TableColumn::make('Collected'),
                    ])
                    ->schema([
                        TextEntry::make('project_name'),
                        TextEntry::make('pending')->money($client['currency_code']),
                        TextEntry::make('overdue')->money($client['currency_code']),
                        TextEntry::make('collected')->money($client['currency_code']),
                    ]),
            ]), $this->report);
    }
}
