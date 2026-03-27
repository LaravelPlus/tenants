<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use LaravelPlus\Tenants\Events\InvitationAccepted;
use LaravelPlus\Tenants\Events\InvitationDeclined;
use LaravelPlus\Tenants\Events\InvitationSent;
use LaravelPlus\Tenants\Events\MemberAdded;
use LaravelPlus\Tenants\Mail\OrganizationInvitationMail;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Models\OrganizationInvitation;
use LaravelPlus\Tenants\Notifications\InvitationReceivedNotification;
use RuntimeException;

final class InvitationService
{
    public function send(Organization $organization, string $email, string $role, int $invitedById): OrganizationInvitation
    {
        $existing = $organization->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            throw new RuntimeException('An active invitation already exists for this email.');
        }

        $invitation = DB::transaction(function () use ($organization, $email, $role, $invitedById): OrganizationInvitation {
            return $organization->invitations()->create([
                'email' => $email,
                'role' => $role,
                'invited_by' => $invitedById,
                'expires_at' => now()->addHours((int) config('tenants.invitation_expiry_hours', 72)),
            ]);
        });

        // Send mail after commit to avoid sending if transaction fails
        Mail::to($email)->send(new OrganizationInvitationMail($invitation));

        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            Notification::send($existingUser, new InvitationReceivedNotification($invitation));
        }

        event(new InvitationSent($invitation));

        return $invitation;
    }

    public function accept(OrganizationInvitation $invitation): void
    {
        if ($invitation->isExpired()) {
            throw new RuntimeException('This invitation has expired.');
        }

        if (!$invitation->isPending()) {
            throw new RuntimeException('This invitation is no longer pending.');
        }

        $user = User::where('email', $invitation->email)->firstOrFail();

        DB::transaction(function () use ($invitation, $user): void {
            $invitation->accept();
            $invitation->organization->addMember($user, $invitation->role);
        });

        event(new InvitationAccepted($invitation));
        event(new MemberAdded($invitation->organization, $user, $invitation->role));
    }

    public function decline(OrganizationInvitation $invitation): void
    {
        if (!$invitation->isPending()) {
            throw new RuntimeException('This invitation is no longer pending.');
        }

        $invitation->decline();

        event(new InvitationDeclined($invitation));
    }

    public function cancel(OrganizationInvitation $invitation): bool
    {
        return (bool) $invitation->delete();
    }
}
