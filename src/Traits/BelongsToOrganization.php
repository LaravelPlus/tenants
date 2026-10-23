<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LaravelPlus\Tenants\Models\Organization;
use LaravelPlus\Tenants\Scopes\OrganizationScope;
use RuntimeException;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());

        static::creating(function ($model): void {
            if (!$model->organization_id) {
                $model->organization_id = session('current_organization_id');
            }

            if (!$model->organization_id) {
                throw new RuntimeException('No current organization: set organization_id explicitly when creating '.$model::class.' outside a request.');
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
