<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LaravelPlus\GlobalSettings\Models\Setting;
use LaravelPlus\Tenants\Http\Middleware\SetCurrentOrganization;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Models\OrganizationInvitation;
use LaravelPlus\Tenants\Scopes\OrganizationScope;
use LaravelPlus\Tenants\Services\InvitationService;
use LaravelPlus\Tenants\Services\OrganizationService;
use LaravelPlus\Tenants\Tests\TestCase;
use LaravelPlus\Tenants\Tests\User;
use LaravelPlus\Tenants\Traits\BelongsToOrganization;
use RuntimeException;

final class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    private function org(User $owner): Organization
    {
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $org->addMember($owner, 'owner');

        return $org;
    }

    public function test_non_member_cannot_invite_or_cancel(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $org = $this->org($owner);
        $attacker = User::factory()->create();
        $invitation = OrganizationInvitation::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($attacker)
            ->post(route('organizations.invitations.store', $org), ['email' => $attacker->email, 'role' => 'owner'])
            ->assertNotFound();
        $this->actingAs($attacker)
            ->delete(route('organizations.invitations.destroy', [$org, $invitation]))
            ->assertNotFound();

        $this->assertDatabaseCount('organization_invitations', 1);
    }

    public function test_plain_member_cannot_invite_but_admin_can(): void
    {
        Mail::fake();
        $org = $this->org(User::factory()->create());
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $org->addMember($member, 'member');
        $org->addMember($admin, 'admin');

        $this->actingAs($member)
            ->post(route('organizations.invitations.store', $org), ['email' => 'x@example.com'])
            ->assertNotFound();
        $this->actingAs($admin)
            ->post(route('organizations.invitations.store', $org), ['email' => 'x@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('organization_invitations', ['email' => 'x@example.com']);
    }

    public function test_admin_cannot_cancel_invitation_of_another_organization(): void
    {
        $admin = User::factory()->create();
        $mine = $this->org($admin);
        $other = $this->org(User::factory()->create());
        $invitation = OrganizationInvitation::factory()->create(['organization_id' => $other->id]);

        $this->actingAs($admin)
            ->delete(route('organizations.invitations.destroy', [$mine, $invitation]))
            ->assertNotFound();
        $this->assertModelExists($invitation);
    }

    public function test_only_the_invited_email_can_accept(): void
    {
        $org = $this->org(User::factory()->create());
        $invitee = User::factory()->create(['email' => 'Invitee@Example.com']);
        $other = User::factory()->create();
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $org->id, 'email' => 'invitee@example.com', 'role' => 'member',
        ]);

        $this->actingAs($other)->post(route('invitations.accept', $invitation->token))->assertNotFound();
        $this->assertFalse($org->hasMember($other));

        $this->actingAs($invitee)->post(route('invitations.accept', $invitation->token))->assertRedirect();
        $this->assertTrue($org->hasMember($invitee));
    }

    public function test_scope_fails_closed_without_current_organization(): void
    {
        Schema::create('scoped_things', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('organization_id')->nullable();
        });
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();
        ScopedThing::withoutGlobalScope(OrganizationScope::class)->insert([
            ['organization_id' => $a->id], ['organization_id' => $b->id],
        ]);

        $this->assertCount(0, ScopedThing::all());
        $this->assertCount(2, ScopedThing::withoutGlobalScope(OrganizationScope::class)->get());

        session(['current_organization_id' => $a->id]);
        $this->assertCount(1, ScopedThing::all());

        session()->forget('current_organization_id');
        $this->expectException(RuntimeException::class);
        ScopedThing::create([]);
    }

    public function test_stale_session_org_is_cleared_when_user_has_no_membership(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->detach();
        Organization::query()->where('owner_id', $user->id)->delete();
        $user->organizations()->detach();
        session(['current_organization_id' => $org->id]);

        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $user);
        app(SetCurrentOrganization::class)->handle($request, fn () => response('ok'));

        $this->assertNull(session('current_organization_id'));
    }

    public function test_reinvite_after_decline_reuses_row(): void
    {
        Mail::fake();
        $inviter = User::factory()->create();
        $org = $this->org($inviter);
        $service = app(InvitationService::class);

        $first = $service->send($org, 'a@example.com', 'member', $inviter->id);
        $service->decline($first);
        $second = $service->send($org, 'a@example.com', 'admin', $inviter->id);

        $this->assertSame($first->id, $second->id);
        $this->assertNotSame($first->token, $second->token);
        $this->assertTrue($second->fresh()->isPending());
        $this->assertDatabaseCount('organization_invitations', 1);
    }

    public function test_max_organizations_per_user_is_enforced(): void
    {
        config(['tenants.max_organizations_per_user' => 1]);
        $user = User::factory()->create();
        Setting::flushCache();
        $service = app(OrganizationService::class);

        $service->create(['name' => 'One'], $user->id);

        $this->expectException(ValidationException::class);
        $service->create(['name' => 'Two'], $user->id);
    }
}

final class ScopedThing extends Model
{
    use BelongsToOrganization;

    public $timestamps = false;

    protected $table = 'scoped_things';

    protected $guarded = [];
}
