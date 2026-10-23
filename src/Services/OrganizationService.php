<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LaravelPlus\Tenants\Contracts\OrganizationRepositoryInterface;
use LaravelPlus\Tenants\Contracts\OrganizationServiceInterface;
use LaravelPlus\Tenants\Events\MemberAdded;
use LaravelPlus\Tenants\Events\MemberRemoved;
use LaravelPlus\Tenants\Events\MemberRoleChanged;
use LaravelPlus\Tenants\Events\OrganizationCreated;
use LaravelPlus\Tenants\Events\OrganizationDeleted;
use LaravelPlus\Tenants\Models\Organization;
use RuntimeException;

final class OrganizationService implements OrganizationServiceInterface
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $repository,
    ) {}

    public function list(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $query = $this->repository->query()->with(['owner', 'members']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data, ?int $ownerId = null): Organization
    {
        if ($ownerId && empty($data['is_personal'])) {
            $this->assertUnderOrganizationLimit($ownerId);
        }

        return DB::transaction(function () use ($data, $ownerId): Organization {
            $data['owner_id'] = $ownerId;

            if (!empty($data['slug'])) {
                $data['slug'] = Str::slug($data['slug']);
            }

            $organization = $this->repository->create($data);

            if ($ownerId) {
                $organization->addMember(User::findOrFail($ownerId), 'owner');
            }

            event(new OrganizationCreated($organization));

            return $organization;
        });
    }

    private function assertUnderOrganizationLimit(int $ownerId): void
    {
        $max = (int) app(TenantSettingsService::class)->getAll()['max_organizations_per_user'];

        if ($max > 0 && $this->repository->query()->where('owner_id', $ownerId)->where('is_personal', false)->count() >= $max) {
            throw ValidationException::withMessages(['name' => 'You have reached the maximum number of organizations.']);
        }
    }

    public function update(Organization $organization, array $data): Organization
    {
        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $this->repository->update($organization, $data);
    }

    public function delete(Organization $organization): bool
    {
        $result = $this->repository->delete($organization);

        if ($result) {
            event(new OrganizationDeleted($organization));
        }

        return $result;
    }

    public function createPersonalOrganization(int $userId, string $userName): Organization
    {
        return $this->create([
            'name' => $userName."'s Organization",
            'is_personal' => true,
        ], $userId);
    }

    public function addMember(Organization $organization, int $userId, string $role = 'member'): void
    {
        $maxMembers = (int) config('tenants.max_members_per_organization', 0);
        if ($maxMembers > 0 && $organization->members()->count() >= $maxMembers) {
            throw new RuntimeException('Organization has reached its member limit.');
        }

        $user = User::findOrFail($userId);
        $organization->addMember($user, $role);

        event(new MemberAdded($organization, $user, $role));
    }

    public function removeMember(Organization $organization, int $userId): void
    {
        $user = User::findOrFail($userId);
        $organization->removeMember($user);

        if (auth()->id() === $user->id && (int) session('current_organization_id') === $organization->id) {
            session()->forget('current_organization_id');
        }

        event(new MemberRemoved($organization, $user));
    }

    public function changeMemberRole(Organization $organization, int $userId, string $role): void
    {
        $user = User::findOrFail($userId);
        $oldRole = $organization->getMemberRole($user) ?? 'member';
        $organization->changeMemberRole($user, $role);

        event(new MemberRoleChanged($organization, $user, $oldRole, $role));
    }

    public function switchOrganization(int $userId, Organization $organization): void
    {
        if (!$organization->hasMember(User::findOrFail($userId))) {
            throw new RuntimeException('User is not a member of this organization.');
        }

        session()->put('current_organization_id', $organization->id);

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($organization->id);
        }
    }
}
