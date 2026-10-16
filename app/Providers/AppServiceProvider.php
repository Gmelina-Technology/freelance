<?php

namespace App\Providers;

use App\Enums\AccountCapability;
use App\Filament\App\Widgets\MyTasksTable;
use App\Models\Account;
use App\Models\Comment;
use App\Models\User;
use App\Policies\CommentPolicy;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

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

        foreach (AccountCapability::cases() as $capability) {
            Gate::define(
                $capability,
                fn (User $user, Account $account): bool => $capability->isGrantedTo($user->getAccountRole($account)),
            );
        }

        FilamentColor::register([
            'primary' => self::BRAND_PALETTE,
            'gray' => Color::Zinc,
        ]);

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('api.rate_limits.api')))->by($this->apiClientKey($request)));
        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(max(1, (int) config('api.rate_limits.mcp')))->by($this->apiClientKey($request)));

        Queue::failing(function (JobFailed $event): void {
            Log::error('Queued job failed', [
                'job' => $event->job->resolveName(),
                'job_id' => $event->job->getJobId(),
                'uuid' => $event->job->uuid(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'exception' => $event->exception::class.': '.$event->exception->getMessage(),
            ]);
        });

        Model::preventLazyLoading(! $this->app->isProduction());
    }

    /**
     * Rate limit bucket for an API or MCP request: the Sanctum token, else the user, else the IP.
     */
    private function apiClientKey(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            return 'token:'.$token->getKey();
        }

        if ($request->user() !== null) {
            return 'user:'.$request->user()->getKey();
        }

        return 'ip:'.$request->ip();
    }
}
