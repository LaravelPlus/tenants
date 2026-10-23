<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = session('current_organization_id');

        // Fail closed: no current organization means no rows. System code
        // (jobs, console) opts out with ->withoutGlobalScope(OrganizationScope::class).
        if ($organizationId) {
            $builder->where($model->getTable().'.organization_id', $organizationId);
        } else {
            $builder->whereRaw('1 = 0');
        }
    }
}
