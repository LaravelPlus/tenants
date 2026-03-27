<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LaravelPlus\Tenants\Http\Controllers\InvitationController;
use LaravelPlus\Tenants\Http\Controllers\OrganizationController;
use LaravelPlus\Tenants\Http\Controllers\OrganizationSwitchController;

Route::middleware(['web', 'auth'])
    ->group(function (): void {
        Route::get('organizations', [OrganizationController::class, 'index'])
            ->name('organizations.index');
        Route::get('organizations/create', [OrganizationController::class, 'create'])
            ->name('organizations.create');
        Route::post('organizations', [OrganizationController::class, 'store'])
            ->name('organizations.store');
        Route::get('organizations/{organization}', [OrganizationController::class, 'show'])
            ->name('organizations.show');

        Route::post('organizations/switch/{organization}', OrganizationSwitchController::class)
            ->name('organizations.switch');

        Route::get('invitations/{token}/accept', [InvitationController::class, 'show'])
            ->name('invitations.show');
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])
            ->middleware('throttle:invitations')
            ->name('invitations.accept');
        Route::post('invitations/{token}/decline', [InvitationController::class, 'decline'])
            ->middleware('throttle:invitations')
            ->name('invitations.decline');

        Route::post('organizations/{organization}/invitations', [InvitationController::class, 'store'])
            ->middleware('throttle:invitations')
            ->name('organizations.invitations.store');
        Route::delete('organizations/{organization}/invitations/{invitation}', [InvitationController::class, 'destroy'])
            ->middleware('throttle:invitations')
            ->name('organizations.invitations.destroy');
    });
