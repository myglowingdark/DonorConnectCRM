# Architecture

## Request flow

1. User authenticates via Laravel Breeze (session).
2. `SetCurrentOrganization` ensures a valid `current_organization_id` in session.
3. Controllers authorize via Policies; queries use `BelongsToOrganization` scopes.
4. Inertia shares `auth`, `currentOrganization`, and `organizations` to React.
5. Domain logic lives in Services (`Donors\`, `WordPress\`, `AuditLogger`).
6. Background work uses Jobs (`Jobs\Sync\SyncOrganizationDonorsJob`).

## Folder map

```
app/
  Enums/                 Role, outcomes, sync status
  Http/Controllers/      Feature controllers
  Http/Middleware/       Org context, roles
  Http/Requests/         Validation
  Jobs/Sync/             Queued WordPress sync
  Models/                Eloquent + BelongsToOrganization
  Policies/              Authorization
  Services/              Business logic
  Support/               OrganizationContext helper
resources/js/
  Components/            Shared UI (sidebar, KPI, badges…)
  Layouts/               Authenticated / Guest
  Pages/                 Inertia pages by feature
docs/                    Living documentation
ui_design/               Design HTML references (do not delete)
```

## Multi-tenancy

Session org context + policy checks + query scopes. Never rely on the frontend alone.
