# Tenancy & security

## Organization context

- Stored in session key `current_organization_id`.
- Switched via `POST /organization/switch` (authorized).
- Super Admin may access any organization.
- Volunteers/Org Admins may only access organizations on `organization_user`.

## Enforcement layers

1. **Middleware** `role:*` for admin-only routes.
2. **Policies** (`DonorPolicy`, `OrganizationPolicy`, …).
3. **Query scopes** `forOrganization($id)` on business models.
4. **Form Requests** validate input; org id is taken from session, not trusted from the client for writes.
5. **Encrypted casts** on `organization_api_connections.credentials`.
6. **Rate limit** `throttle:sync` on sync endpoints.
7. **Audit logs** for assignments, org/user changes, API settings, call logs.

## Do Not Call

Volunteers cannot log new calls while `donors.do_not_call = true`. Org/Super admins can clear the flag.

## Tests

See `tests/Feature/Tenancy/TenantIsolationTest.php`.
