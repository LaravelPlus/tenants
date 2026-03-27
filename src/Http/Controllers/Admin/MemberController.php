<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use LaravelPlus\Tenants\Contracts\OrganizationServiceInterface;
use LaravelPlus\Tenants\Http\Requests\Admin\StoreMemberRequest;
use LaravelPlus\Tenants\Http\Requests\Admin\UpdateMemberRoleRequest;
use LaravelPlus\Tenants\Models\Organization;

final class MemberController
{
    public function __construct(
        private(set) OrganizationServiceInterface $organizationService,
    ) {}

    public function store(StoreMemberRequest $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validated();

        $this->organizationService->addMember(
            $organization,
            (int) $validated['user_id'],
            $validated['role'],
        );

        return back()->with('status', 'Member added successfully.');
    }

    public function destroy(Organization $organization, User $user): RedirectResponse
    {
        $this->organizationService->removeMember($organization, $user);

        return back()->with('status', 'Member removed successfully.');
    }

    public function updateRole(UpdateMemberRoleRequest $request, Organization $organization, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $this->organizationService->changeMemberRole($organization, $user, $validated['role']);

        return back()->with('status', 'Member role updated successfully.');
    }
}
