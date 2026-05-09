<?php

namespace App\Providers;

use App\Filament\App\Widgets\MyTasksTable;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_END,
            fn (): string => new HtmlString('<h2 class="fi-ta-header-heading text-lg font-semibold text-gray-900">My tasks</h2>'),
            scopes: [
                MyTasksTable::class,
            ]
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
