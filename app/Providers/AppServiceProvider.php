<?php

namespace App\Providers;

use App\Filament\App\Widgets\MyTasksTable;
use App\Models\Comment;
use App\Policies\CommentPolicy;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
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
     * Deep "ledger" green shared by every panel. Shades sit darker than Filament's
     * generated palettes so primary buttons keep white text at WCAG AA contrast.
     *
     * @var array<int, string>
     */
    public const BRAND_PALETTE = [
        50 => 'oklch(0.975 0.018 155)',
        100 => 'oklch(0.948 0.04 155)',
        200 => 'oklch(0.895 0.07 155)',
        300 => 'oklch(0.82 0.11 155)',
        400 => 'oklch(0.72 0.14 155)',
        500 => 'oklch(0.56 0.14 155)',
        600 => 'oklch(0.49 0.125 155)',
        700 => 'oklch(0.43 0.11 155)',
        800 => 'oklch(0.37 0.095 155)',
        900 => 'oklch(0.31 0.075 155)',
        950 => 'oklch(0.24 0.055 155)',
    ];

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

        FilamentColor::register([
            'primary' => self::BRAND_PALETTE,
            'gray' => Color::Zinc,
        ]);

        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
