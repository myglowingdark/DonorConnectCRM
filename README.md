# DonorConnect CRM

Multi-tenant Donor Relationship Management portal for telecalling volunteers and organization admins.

**Stack:** Laravel 13 · Breeze (Inertia + React) · Tailwind CSS · MySQL (`drm`) · PHP 8.4+

## Quick start (MAMP)

1. Ensure MAMP MySQL is running and database **`drm`** exists (phpMyAdmin → create if needed).
2. From project root:

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
```

3. Configure `.env` for MAMP:

```env
APP_NAME="DonorConnect CRM"
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=8889
DB_DATABASE=drm
DB_USERNAME=root
DB_PASSWORD=root
QUEUE_CONNECTION=database
```

4. Install front-end and migrate:

```bash
npm install --legacy-peer-deps
npm run build
php artisan migrate --seed
php artisan storage:link
```

5. Serve the app:

```bash
php artisan serve
# or open via MAMP: http://localhost:8888/DRM/public
```

6. Run the queue worker (required for WordPress sync):

```bash
php artisan queue:work
```

7. Scheduler (hourly sync). Add to crontab:

```cron
* * * * * cd /Applications/MAMP/htdocs/DRM && php artisan schedule:run >> /dev/null 2>&1
```

## Demo logins (password: `password`)

| Role | Email |
|------|-------|
| Super Admin | `admin@donorconnect.test` |
| Organization Admin | `hope.admin@donorconnect.test` |
| Volunteer | `priya@donorconnect.test` |

## Documentation

- [Setup](docs/setup.md)
- [Architecture](docs/architecture.md)
- [Tenancy & security](docs/tenancy.md)
- [Roles & permissions](docs/roles-permissions.md)
- [WordPress API](docs/wordpress-api.md)
- [Phase 2 roadmap](docs/phase-2.md)
- [Changelog](docs/changelog.md)
- [Contributing](docs/contributing.md)

## UI source

Pixel and design tokens are based on [`ui_design/`](ui_design/) and [`ui_design/altruist_core/DESIGN.md`](ui_design/altruist_core/DESIGN.md).

## Tests

```bash
php artisan test --filter=TenantIsolation
php artisan test --filter=CallLogging
php artisan test --filter=DonorSyncIdempotency
```
