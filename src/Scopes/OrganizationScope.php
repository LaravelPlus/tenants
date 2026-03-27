<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use LaravelPlus\Tenants\Support\CurrentOrganizationResolver;

final class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = app(CurrentOrganizationResolver::class)->id();

        if ($organizationId) {
            $builder->where($model->getTable().'.organization_id', $organizationId);
        }
    }
}
