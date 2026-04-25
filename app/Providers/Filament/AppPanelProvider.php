<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Tenancy\EditAccountPage;
use App\Filament\App\Pages\Tenancy\RegisterAccount;
use App\Models\Account;
use Filament\Actions\CreateAction;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Enums\GlobalSearchPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->viteTheme('resources/css/filament/app/theme.css')
            ->login()
            ->registration()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->tenant(Account::class, 'id', 'account')
            ->tenantRegistration(RegisterAccount::class)
            ->tenantProfile(EditAccountPage::class)
            ->discoverClusters(in: app_path('Filament/App/Clusters'), for: 'App\Filament\App\Clusters')
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->databaseNotifications()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->multiFactorAuthentication([
                EmailAuthentication::make()
            ], isRequired: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CreateAction::configureUsing(function(CreateAction $action) {
            $action->icon(Heroicon::Plus);
        });
    }
}
