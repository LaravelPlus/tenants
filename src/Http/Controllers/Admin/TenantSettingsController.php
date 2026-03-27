<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use LaravelPlus\Tenants\Http\Requests\Admin\UpdateTenantSettingsRequest;
use LaravelPlus\Tenants\Services\TenantSettingsService;
use LaravelPlus\Tenants\Support\AuditLogger;

final class TenantSettingsController
{
    public function __construct(
        private(set) TenantSettingsService $settingsService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/Organizations/Settings', [
            'settings' => $this->settingsService->getAll(),
        ]);
    }

    public function update(UpdateTenantSettingsRequest $request): RedirectResponse
    {
        $this->settingsService->update($request->validated());

        AuditLogger::log('tenant_settings.updated', null, null, $request->validated());

        return redirect()->route('admin.organizations.settings')
            ->with('status', 'Tenant settings updated successfully.');
    }
}
