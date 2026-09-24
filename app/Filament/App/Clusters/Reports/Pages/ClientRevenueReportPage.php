<?php

namespace App\Filament\App\Clusters\Reports\Pages;

use App\Filament\App\Clusters\Reports\ReportsCluster;
use App\Services\Reports\ClientRevenueReportService;
use App\Services\Reports\SalesReportService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;

class ClientRevenueReportPage extends Page
{
    protected static ?string $cluster = ReportsCluster::class;

    protected static ?string $slug = 'client-revenue';

    protected static ?string $navigationLabel = 'Client & project revenue';

    protected static ?string $title = 'Client & project revenue';

    protected static ?int $navigationSort = 2;

    /**
     * @var array{year?: int|string|null}|null
     */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        return ReportsCluster::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(['year' => null]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('year')
                    ->options(fn (): array => array_combine($this->availableYears, array_map('strval', $this->availableYears)))
                    ->placeholder('All time')
                    ->live(),
            ])
            ->statePath('filters');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components(fn (): array => [
            EmbeddedSchema::make('form'),
            ...$this->reportComponents(),
        ]);
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function availableYears(): array
    {
        return app(SalesReportService::class)->availableYears(Filament::getTenant());
    }

    /**
     * @return array{clients: list<array<string, mixed>>, unattributed_total: float}
     */
    #[Computed]
    public function report(): array
    {
        return app(ClientRevenueReportService::class)->forAccount(Filament::getTenant(), $this->selectedYear());
    }

    /**
     * Null means all time. The year comes from the browser, so anything unknown is all time too.
     */
    protected function selectedYear(): ?int
    {
        $year = $this->filters['year'] ?? null;

        if (blank($year) || ! is_numeric($year)) {
            return null;
        }

        return in_array((int) $year, $this->availableYears, true) ? (int) $year : null;
    }

    /**
     * @return list<Component>
     */
    protected function reportComponents(): array
    {
        $report = $this->report;

        if ($report['clients'] === [] && $report['unattributed_total'] == 0) {
            return [Text::make('No sales recorded for this period.')];
        }

        $components = array_map(fn (array $client): Section => Section::make($client['client_name'])
            ->key("client-{$client['client_id']}")
            ->description('Total: '.Number::currency($client['total'], in: $client['currency_code'] ?? 'USD'))
            ->schema([
                RepeatableEntry::make('projects')
                    ->key("client-{$client['client_id']}-projects")
                    ->hiddenLabel()
                    ->state($client['projects'])
                    ->table([
                        TableColumn::make('Project'),
                        TableColumn::make('Revenue'),
                        TableColumn::make('Share'),
                        TableColumn::make('Top contributor')->hiddenHeaderLabel(),
                    ])
                    ->schema([
                        TextEntry::make('project_name'),
                        TextEntry::make('amount')->money($client['currency_code']),
                        TextEntry::make('share')->suffix('%'),
                        TextEntry::make('top_label')->badge()->color('success'),
                    ]),
            ]), $report['clients']);

        if ($report['unattributed_total'] != 0) {
            $currency = Filament::getTenant()->currency_code ?? 'USD';

            $components[] = Section::make('Unattributed sales')
                ->key('unattributed')
                ->description('Sales with no linked invoice, or whose invoice, client or project was deleted.')
                ->schema([
                    TextEntry::make('unattributed_total')
                        ->hiddenLabel()
                        ->state($report['unattributed_total'])
                        ->money($currency),
                ]);
        }

        return $components;
    }
}
