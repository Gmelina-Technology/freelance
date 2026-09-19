<?php

namespace App\Providers;

use App\Filament\App\Widgets\MyTasksTable;
use App\Models\Comment;
use App\Policies\CommentPolicy;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
            TablesRenderHook::TOOLBAR_START,
            fn (): string => new HtmlString('<h1 class="fi-ta-header-heading text-xl font-semibold text-gray-900 dark:text-white">My tasks</h1>'),
            scopes: [
                MyTasksTable::class,
            ]
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): string => view('legal.auth-notice')->render(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Comment::class, CommentPolicy::class);

        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
