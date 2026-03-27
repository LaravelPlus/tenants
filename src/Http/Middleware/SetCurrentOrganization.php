<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelPlus\Tenants\Contracts\OrganizationRepositoryInterface;
use LaravelPlus\Tenants\Support\CurrentOrganizationResolver;
use Symfony\Component\HttpFoundation\Response;

final class SetCurrentOrganization
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $repository,
        private readonly CurrentOrganizationResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $routingMode = config('tenants.routing_mode', 'session');

        if ($routingMode === 'url') {
            $this->resolveFromUrl($request, $user);
        } else {
            $this->resolveFromSession($user);
        }

        return $next($request);
    }

    private function resolveFromUrl(Request $request, $user): void
    {
        $organization = $request->route('organization');

        if ($organization && is_string($organization)) {
            $organization = $this->repository->findBySlug($organization);
        }

        if ($organization && $organization->hasMember($user)) {
            $this->resolver->set($organization->id);
            $this->setTeamId($organization->id);

            return;
        }

        $this->resolveFromSession($user);
    }

    private function resolveFromSession($user): void
    {
        // Eager load all user organizations in a single query
        $userOrganizations = $user->organizations()
            ->select('organizations.id', 'organizations.name', 'organizations.slug', 'organizations.is_personal', 'organizations.owner_id')
            ->get();

        $organizationId = $this->resolver->id();

        if ($organizationId) {
            $organization = $userOrganizations->firstWhere('id', $organizationId);

            if ($organization) {
                $this->setTeamId($organization->id);

                return;
            }
        }

        // Fallback: find personal org from already-loaded collection
        $personal = $userOrganizations->first(fn ($org) => $org->is_personal && $org->owner_id === $user->id);

        if (!$personal) {
            $personal = $userOrganizations->first();
        }

        if ($personal) {
            $this->resolver->set($personal->id);
            $this->setTeamId($personal->id);
        }
    }

    private function setTeamId(int $organizationId): void
    {
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($organizationId);
        }
    }
}
