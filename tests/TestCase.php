<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests;

use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use LaravelPlus\GlobalSettings\GlobalSettingsServiceProvider;
use LaravelPlus\Tenants\TenantsServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Spatie\Permission\PermissionServiceProvider;

if (! class_exists(\App\Models\User::class)) {
    class_alias(User::class, \App\Models\User::class);
}

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            PermissionServiceProvider::class,
            GlobalSettingsServiceProvider::class,
            TenantsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('permission.testing', true);

        $app['config']->set('view.paths', [__DIR__ . '/resources/views']);

        $app['config']->set('inertia.testing.ensure_pages_exist', false);

        $app['config']->set('tenants.personal_org', true);
        $app['config']->set('tenants.admin', [
            'enabled' => true,
            'prefix' => 'admin/organizations',
            'middleware' => ['web', 'auth'],
        ]);
    }

    protected function defineRoutes($router): void
    {
        Route::get('/login', fn () => 'login')->name('login');
        Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }
}
