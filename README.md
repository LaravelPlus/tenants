# LaravelPlus Tenants

Multi-tenancy organization management package for Laravel 12+. Provides organizations, member roles, invitations, and automatic tenant scoping.

## Requirements

- PHP 8.4+
- Laravel 12+

## Installation

```bash
composer require laravelplus/tenants
```

Publish configuration and run migrations:

```bash
php artisan tenants:install
```

Or manually:

```bash
php artisan vendor:publish --tag=tenants-config
php artisan migrate
```

## Setup

Add the `HasOrganizations` trait to your User model:

```php
use LaravelPlus\Tenants\Traits\HasOrganizations;

class User extends Authenticatable
{
    use HasOrganizations;
}
```

Register the middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \LaravelPlus\Tenants\Http\Middleware\SetCurrentOrganization::class,
    ]);
})
```

## Configuration

Publish the config file to customize behavior:

```php
// config/tenants.php
return [
    'entity_name'        => 'Organization',
    'entity_name_plural' => 'Organizations',
    'multi_org'          => true,           // users can belong to multiple orgs
    'personal_org'       => true,           // auto-create on registration
    'routing_mode'       => 'session',      // 'session' or 'url'
    'invitation_expiry_hours' => 72,
    'max_organizations_per_user'    => 0,   // 0 = unlimited
    'max_members_per_organization'  => 0,   // 0 = unlimited
    'default_member_role' => 'member',
    'member_roles' => ['owner', 'admin', 'member'],
    'admin' => [
        'enabled'    => true,
        'prefix'     => 'admin/organizations',
        'middleware' => ['web', 'auth', 'role:super-admin,admin'],
    ],
];
```

## Usage

### Creating Organizations

```php
use LaravelPlus\Tenants\Facades\Organization;

$org = Organization::create(['name' => 'Acme Corp'], $user->id);
```

### Member Management

```php
$org->addMember($user, 'admin');
$org->hasMember($user);          // true
$org->getMemberRole($user);      // 'admin'
$org->changeMemberRole($user, 'member');
$org->removeMember($user);
```

### Switching Organizations

```php
$user->switchOrganization($organization);
$user->currentOrganization(); // returns active org
```

### Tenant-Scoped Models

Add `BelongsToOrganization` to models that should be scoped to the current organization:

```php
use LaravelPlus\Tenants\Traits\BelongsToOrganization;

class Project extends Model
{
    use BelongsToOrganization;
}

// Queries automatically filter by current organization
Project::all(); // only returns projects for the active org
```

The scope fails closed: with no current organization (queued jobs, console, users without an org) queries return no rows and creating a record without `organization_id` throws. System code opts out explicitly with `Project::withoutGlobalScope(\LaravelPlus\Tenants\Scopes\OrganizationScope::class)` and sets `organization_id` itself.

### Invitations

```php
use LaravelPlus\Tenants\Services\InvitationService;

app(InvitationService::class)->send($organization, 'user@example.com', 'member', $invitedBy->id);
```

Invitations send an email and an in-app notification (for existing users). Recipients accept or decline via token-based URLs.

### Facade

The `Organization` facade proxies to `OrganizationService`:

```php
use LaravelPlus\Tenants\Facades\Organization;

Organization::list(perPage: 15, search: 'acme');
Organization::create($data, $ownerId);
Organization::addMember($org, $userId, 'admin');
Organization::switchOrganization($userId, $org);
```

## Spatie Permission Teams

The package integrates with Spatie Permission teams. Set in `config/permission.php`:

```php
'teams' => true,
'team_foreign_key' => 'organization_id',
```

The `SetCurrentOrganization` middleware automatically calls `setPermissionsTeamId()`.

## Events

All major actions dispatch events:

| Event | Payload |
|-------|---------|
| `OrganizationCreated` | `$organization` |
| `OrganizationDeleted` | `$organization` |
| `MemberAdded` | `$organization`, `$user`, `$role` |
| `MemberRemoved` | `$organization`, `$user` |
| `MemberRoleChanged` | `$organization`, `$user`, `$oldRole`, `$newRole` |
| `InvitationSent` | `$invitation` |
| `InvitationAccepted` | `$invitation` |
| `InvitationDeclined` | `$invitation` |

## Admin Panel

When `tenants.admin.enabled` is `true`, the package registers admin routes at `/admin/organizations` with full CRUD for organizations and member management.

## Artisan Commands

```bash
php artisan tenants:install                  # Publish config and run migrations
php artisan tenants:cleanup-invitations      # Remove expired invitations
php artisan tenants:cleanup-invitations --days=7
```

## AI Skills

Publish AI development skills for Claude Code or GitHub Copilot:

```bash
php artisan vendor:publish --tag=tenants-skills          # .claude/skills/
php artisan vendor:publish --tag=tenants-skills-github    # .github/skills/
```

## File Structure

```
├── config/tenants.php
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── resources/views/emails/
├── routes/
│   ├── admin.php
│   └── web.php
├── skills/tenants-development/
└── src/
    ├── Console/Commands/
    ├── Contracts/
    ├── Enums/
    ├── Events/
    ├── Facades/
    ├── Http/
    │   ├── Controllers/
    │   ├── Middleware/
    │   └── Requests/
    ├── Mail/
    ├── Models/
    ├── Notifications/
    ├── Repositories/
    ├── Scopes/
    ├── Services/
    ├── Traits/
    └── TenantsServiceProvider.php
```

## License

MIT
