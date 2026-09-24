<?php

namespace App\Filament\App\Clusters\Reports;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ReportsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 6;

    /**
     * The one access rule for the whole Reports section. Clusters don't gate their pages,
     * so every report page and widget delegates here explicitly.
     */
    public static function canAccess(): bool
    {
        return ! Auth::user()->isMember(Filament::getTenant());
    }
}
