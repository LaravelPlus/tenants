<?php

declare(strict_types=1);

namespace LaravelPlus\Tenants\Support;

use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * Log an audit event if the AuditLog model exists.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function log(string $event, ?Model $model = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        if (!class_exists(\App\Models\AuditLog::class)) {
            return;
        }

        \App\Models\AuditLog::log($event, $model, $oldValues, $newValues);
    }
}
