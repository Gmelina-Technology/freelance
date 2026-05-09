<?php

namespace App\Filament\App\Clusters\Settings;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::End;

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return ! Auth::user()->isMember(Filament::getTenant());
    }
}
