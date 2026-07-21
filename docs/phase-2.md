# Phase 2 — shipped

Commission, attribution, and scheduled report email features are live (tables were pre-migrated in MVP).

## Live routes

| Route | Purpose |
|-------|---------|
| `/commissions` | Org payment % + per-volunteer overrides |
| `/commission-cycles` | Calculate / approve / mark paid monthly cycles |
| `/attributions` | Approve volunteer credit for donations |
| `/my-commission` | Volunteer earnings from line items |
| `/email-reports` | Recipients + weekly/monthly schedules |

## Flow

1. Volunteer logs a call with **Attribute donation** → pending `donation_attributions` for that donor’s last-30-day unattributed donations.
2. Admin approves on **Attributions**.
3. Admin **Calculates** a `YYYY-MM` cycle from approved attributions using `commission_settings` rates (shared pool split across contributors when enabled).
4. Approve → Mark paid.
5. Volunteers see line items on **Earnings**.

## Reports

- `php artisan reports:send-due` (hourly schedule) sends active schedules during the configured local hour.
- Types: `weekly_stats` (Mondays), `monthly_commission_summary` (day of month).
- Mail uses org SMTP → platform SMTP → `.env` mailer.
