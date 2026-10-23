<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LaravelPlus\Tenants\Http\Requests\StoreInvitationRequest;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Models\OrganizationInvitation;
use LaravelPlus\Tenants\Services\InvitationService;

final class InvitationController
{
    public function __construct(
        private(set) InvitationService $invitationService,
    ) {}

    /** Invitation addressed to the logged-in user, 404 otherwise. */
    private function findForUser(Request $request, string $token): OrganizationInvitation
    {
        $invitation = OrganizationInvitation::where('token', $token)->firstOrFail();

        abort_unless(
            strcasecmp((string) $request->user()?->email, $invitation->email) === 0,
            404,
        );

        return $invitation;
    }

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = $this->findForUser($request, $token);
        $invitation->load('organization');

        if (!$invitation->isPending()) {
            return redirect()->route('dashboard')
                ->with('error', 'This invitation is no longer valid.');
        }

        return Inertia::render('Invitations/Show', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'organization' => [
                    'name' => $invitation->organization->name,
                ],
                'expires_at' => $invitation->expires_at->toISOString(),
            ],
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findForUser($request, $token);

        $this->invitationService->accept($invitation, $request->user());

        return redirect()->route('dashboard')
            ->with('status', 'You have joined '.$invitation->organization->name.'.');
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findForUser($request, $token);

        $this->invitationService->decline($invitation);

        return redirect()->route('dashboard')
            ->with('status', 'Invitation declined.');
    }

    public function store(StoreInvitationRequest $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validated();

        $this->invitationService->send(
            $organization,
            $validated['email'],
            $validated['role'] ?? config('tenants.default_member_role', 'member'),
            $request->user()->id,
        );

        return back()->with('status', 'Invitation sent successfully.');
    }

    public function destroy(Request $request, Organization $organization, OrganizationInvitation $invitation): RedirectResponse
    {
        abort_unless(
            $invitation->organization_id === $organization->id && $request->user()->isOrganizationAdmin($organization),
            404,
        );

        $this->invitationService->cancel($invitation);

        return back()->with('status', 'Invitation cancelled.');
    }
}
