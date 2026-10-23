<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaravelPlus\Tenants\Contracts\OrganizationRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;

final class SetCurrentOrganization
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $repository,
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
            session()->put('current_organization_id', $organization->id);
            $this->setTeamId($organization->id);

            return;
        }

        $this->resolveFromSession($user);
    }

    private function resolveFromSession($user): void
    {
        $organizationId = session('current_organization_id');

        if ($organizationId) {
            $organization = $this->repository->find($organizationId);

            if ($organization && $organization->hasMember($user)) {
                $this->setTeamId($organization->id);

                return;
            }
        }

        $personal = $this->repository->personalForUser($user->id);

        if ($personal) {
            session()->put('current_organization_id', $personal->id);
            $this->setTeamId($personal->id);

            return;
        }

        $first = $user->organizations()->first();

        if ($first) {
            session()->put('current_organization_id', $first->id);
            $this->setTeamId($first->id);

            return;
        }

        // No valid organization left (e.g. removed from the last one): drop the stale id.
        session()->forget('current_organization_id');
    }

    private function setTeamId(int $organizationId): void
    {
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($organizationId);
        }
    }
}
