<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Scopes\OrganizationScope;
use LaravelPlus\Tenants\Support\CurrentOrganizationResolver;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function ($model): void {
            if (!$model->organization_id) {
                $model->organization_id = app(CurrentOrganizationResolver::class)->id();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
