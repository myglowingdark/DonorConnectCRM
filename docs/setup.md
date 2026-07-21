# Setup

## Requirements

- PHP 8.4+ (Laravel 13; use MAMP PHP 8.4.1)
- Composer
- Node.js 20+
- MySQL 8 (MAMP)
- Database name: **`drm`**

## Environment checklist

| Variable | Suggested value |
|----------|-----------------|
| `APP_NAME` | `DonorConnect CRM` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `8889` (MAMP default) or `3306` |
| `DB_DATABASE` | `drm` |
| `DB_USERNAME` | `root` |
| `DB_PASSWORD` | `root` |
| `QUEUE_CONNECTION` | `database` |
| `APP_URL` | your local URL |

## Queue worker

WordPress sync runs via queued jobs:

```bash
php artisan queue:work --tries=3
```

## Scheduler

Hourly sync for active API connections is registered in `routes/console.php`.

```bash
php artisan schedule:work   # local
# production: cron * * * * * php artisan schedule:run
```

## Seeded data

`php artisan db:seed` creates Hope Foundation, Seva Trust, Green Future NGO, sample donors (Anita Mehta, Rohan Gupta, Neha Patel), and demo users.
