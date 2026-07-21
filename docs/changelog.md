# Changelog

## 2026-07-21 — WordPress sync actions 500 fix

- Root cause: Laravel resolves controller deps then `array_values()`s them. Injecting
  `WordPressDonorSyncService` *after* `{organization}` / `{connection}` made Test / Razorpay
  actions TypeError (HTTP 500) on org-scoped routes. Sync worked because it had no extra DI.
- Fix: resolve services with `app()` inside action handlers; wrap actions in try/catch.
- Vite `base` no longer derived from APP_URL (was baking `/DRM/public/build/` into live assets).
- After deploy on live: `php artisan route:clear && php artisan optimize:clear`

## 2026-07-21 — Logout fix

- Set `APP_URL` to MAMP path `http://localhost:8888/DRM/public` so Ziggy logout URL is correct
- Sidebar logout now uses `router.post(route('logout'))`
- Logout redirects to the login screen

## 2026-07-21 — Assignment + org-admin visibility fixes

- Fixed donor assignment/unassign/distribute posting (reliable POST flow, redirect back to selected volunteer)
- Organization admins only see volunteer memberships for organizations they manage
- Org admin user updates preserve memberships in other organizations
- Added feature tests for assignment and org-admin visibility

## 2026-07-21 — MVP foundation

- Laravel 12 + Breeze Inertia React scaffold on MySQL `drm` (downgraded from Laravel 13 for PHP 8.3 shared hosting)
- Multi-tenant organizations, roles, policies, org switcher
- Volunteer calling workflow (queue, profile, log call, Save+Next, DNC)
- Admin assignments with equal distribute
- Encrypted WordPress API connections + queued sync + history
- Org Admin / Super Admin dashboards, reports CSV, notifications shell
- Phase 2 stubs for commissions and email schedules
- Feature tests: tenancy isolation, call logging, sync idempotency
- Living docs under `docs/`
