<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use LaravelPlus\Tenants\Models\OrganizationInvitation;

final class OrganizationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly OrganizationInvitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        $entityName = config('tenants.entity_name', 'Organization');

        return new Envelope(
            subject: "You've been invited to join {$this->invitation->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'tenants::emails.invitation',
            with: [
                'acceptUrl' => route('invitations.show', $this->invitation->token),
                'organizationName' => $this->invitation->organization->name,
                'role' => $this->invitation->role,
                'expiresAt' => $this->invitation->expires_at->format('F j, Y'),
            ],
        );
    }
}
