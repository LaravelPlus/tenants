<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Support\CurrentOrganizationResolver;
use RuntimeException;

trait HasOrganizations
{
    private ?Organization $resolvedCurrentOrganization = null;

    private bool $currentOrganizationResolved = false;

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function personalOrganization(): ?Organization
    {
        return $this->ownedOrganizations()
            ->where('is_personal', true)
            ->first();
    }

    public function currentOrganization(): ?Organization
    {
        if ($this->currentOrganizationResolved) {
            return $this->resolvedCurrentOrganization;
        }

        $organizationId = app(CurrentOrganizationResolver::class)->id();

        if ($organizationId) {
            $organization = $this->organizations()->where('organizations.id', $organizationId)->first();
        } else {
            $organization = $this->personalOrganization() ?? $this->organizations()->first();
        }

        $this->resolvedCurrentOrganization = $organization;
        $this->currentOrganizationResolved = true;

        return $organization;
    }

    public function clearCurrentOrganizationCache(): void
    {
        $this->resolvedCurrentOrganization = null;
        $this->currentOrganizationResolved = false;
    }

    public function switchOrganization(Organization $organization): void
    {
        if (!$this->belongsToOrganization($organization)) {
            throw new RuntimeException('User is not a member of this organization.');
        }

        app(CurrentOrganizationResolver::class)->set($organization->id);
        $this->clearCurrentOrganizationCache();

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($organization->id);
        }
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()->where('organizations.id', $organization->id)->exists();
    }

    public function organizationRole(Organization $organization): ?string
    {
        $member = $this->organizations()
            ->where('organizations.id', $organization->id)
            ->first();

        return $member?->pivot->role;
    }

    public function isOrganizationOwner(Organization $organization): bool
    {
        return $this->organizationRole($organization) === 'owner';
    }

    public function isOrganizationAdmin(Organization $organization): bool
    {
        return in_array($this->organizationRole($organization), ['owner', 'admin'], true);
    }

    protected static function bootHasOrganizations(): void
    {
        if (config('tenants.personal_org', true)) {
            static::created(function ($user): void {
                app(\LaravelPlus\Tenants\Services\OrganizationService::class)
                    ->createPersonalOrganization($user->id, $user->name ?? $user->email);
            });
        }
    }
}
