<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Support;

final class CurrentOrganizationResolver
{
    private const SESSION_KEY = 'current_organization_id';

    public function id(): ?int
    {
        return session(self::SESSION_KEY);
    }

    public function set(int $organizationId): void
    {
        session()->put(self::SESSION_KEY, $organizationId);
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
