<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LaravelPlus\Tenants\Contracts\OrganizationServiceInterface;
use LaravelPlus\Tenants\Http\Requests\Admin\StoreOrganizationRequest;
use LaravelPlus\Tenants\Http\Requests\Admin\UpdateOrganizationRequest;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Support\AuditLogger;

final class OrganizationController
{
    public function __construct(
        private(set) OrganizationServiceInterface $organizationService,
    ) {}

    public function index(Request $request): Response
    {
        $organizations = $this->organizationService->list(
            perPage: 15,
            search: $request->get('search'),
        );

        return Inertia::render('admin/Organizations/Index', [
            'organizations' => $organizations,
            'filters' => [
                'search' => $request->get('search', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/Organizations/Create');
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $organization = $this->organizationService->create(
            $request->validated(),
            $request->user()->id,
        );

        AuditLogger::log('organization.created', $organization, null, [
            'name' => $organization->name,
        ]);

        return redirect()->route('admin.organizations.index')
            ->with('status', 'Organization created successfully.');
    }

    public function show(Organization $organization): Response
    {
        $organization->load(['owner', 'members', 'invitations' => fn ($q) => $q->pending()]);

        return Inertia::render('admin/Organizations/Show', [
            'organization' => $organization,
        ]);
    }

    public function edit(Organization $organization): Response
    {
        $organization->load(['owner', 'members', 'invitations' => fn ($q) => $q->pending()]);

        return Inertia::render('admin/Organizations/Edit', [
            'organization' => $organization,
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $oldValues = ['name' => $organization->name, 'description' => $organization->description];

        $this->organizationService->update($organization, $request->validated());

        AuditLogger::log('organization.updated', $organization, $oldValues, [
            'name' => $organization->name,
        ]);

        return redirect()->route('admin.organizations.index')
            ->with('status', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        AuditLogger::log('organization.deleted', $organization, [
            'name' => $organization->name,
        ]);

        $this->organizationService->delete($organization);

        return redirect()->route('admin.organizations.index')
            ->with('status', 'Organization deleted successfully.');
    }
}
