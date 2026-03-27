<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants;

use App\Support\AdminNavigation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LaravelPlus\Tenants\Contracts\OrganizationRepositoryInterface;
use LaravelPlus\Tenants\Contracts\OrganizationServiceInterface;
use LaravelPlus\Tenants\Repositories\OrganizationRepository;
use LaravelPlus\Tenants\Services\OrganizationService;
use LaravelPlus\Tenants\Services\TenantSettingsService;
use LaravelPlus\Tenants\Support\CurrentOrganizationResolver;

final class TenantsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenants.php', 'tenants');

        $this->app->bind(OrganizationRepositoryInterface::class, OrganizationRepository::class);

        $this->app->singleton(OrganizationServiceInterface::class, fn ($app) => new OrganizationService(
            $app->make(OrganizationRepositoryInterface::class),
        ));

        $this->app->singleton(OrganizationService::class, fn ($app) => $app->make(OrganizationServiceInterface::class));

        $this->app->singleton(TenantSettingsService::class);

        $this->app->singleton(CurrentOrganizationResolver::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerResources();
        $this->registerRoutes();
        $this->registerCommands();
        $this->registerAdminNavigation();
        $this->registerRateLimiters();
    }

    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tenants.php' => config_path('tenants.php'),
            ], 'tenants-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'tenants-migrations');

            $this->publishes([
                __DIR__.'/../database/seeders' => database_path('seeders'),
            ], 'tenants-seeders');

            $this->publishes([
                __DIR__.'/../skills/tenants-development' => base_path('.claude/skills/tenants-development'),
            ], 'tenants-skills');

            $this->publishes([
                __DIR__.'/../skills/tenants-development' => base_path('.github/skills/tenants-development'),
            ], 'tenants-skills-github');
        }
    }

    private function registerResources(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tenants');
    }

    private function registerRoutes(): void
    {
        if ($this->isAdminEnabled()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    /**
     * Check if admin routes should be enabled via DB setting or config fallback.
     */
    private function isAdminEnabled(): bool
    {
        if (class_exists(\LaravelPlus\GlobalSettings\Models\Setting::class)) {
            try {
                $dbValue = \LaravelPlus\GlobalSettings\Models\Setting::get('package.organizations.enabled');

                if ($dbValue !== null) {
                    return in_array($dbValue, ['1', 'true', true, 1], true);
                }
            } catch (\Throwable) {
                // Table may not exist yet during migrations
            }
        }

        return (bool) config('tenants.admin.enabled', true);
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\InstallCommand::class,
                Console\Commands\CleanupInvitationsCommand::class,
            ]);
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('invitations', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }

    private function registerAdminNavigation(): void
    {
        $this->callAfterResolving(AdminNavigation::class, function (AdminNavigation $nav): void {
            $prefix = config('tenants.admin.prefix', 'admin/organizations');

            $nav->register('organizations', 'Organizations', 'Building2', [
                ['title' => 'All Organizations', 'href' => "/{$prefix}", 'icon' => 'Building2'],
                ['title' => 'Create Organization', 'href' => "/{$prefix}/create", 'icon' => 'Plus'],
                ['title' => 'Settings', 'href' => "/{$prefix}/settings", 'icon' => 'Settings'],
            ], 40);
        });
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            OrganizationRepositoryInterface::class,
            OrganizationServiceInterface::class,
            OrganizationService::class,
            TenantSettingsService::class,
            CurrentOrganizationResolver::class,
        ];
    }
}
