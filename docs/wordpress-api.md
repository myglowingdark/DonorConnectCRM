# WordPress API integration

Each organization stores one connection in `organization_api_connections`.

For partner NGOBuddy sites, prefer the **DonorConnect Bridge** plugin — see [wordpress-bridge.md](./wordpress-bridge.md).

## Auth types

- `bearer` — `Authorization: Bearer {token}`
- `basic` — HTTP Basic username/password
- `api_key` — custom header (default `X-API-Key`)
- `hmac` — DonorConnect Bridge (API key + HMAC-SHA256 signed headers)
- `none` — public endpoint (local testing)

Credentials are stored with Laravel `encrypted:array` casts and **never** returned to the React UI (only `has_credentials` flag).

## Expected payload

`GET {base_url}/donors?page=1&per_page=100`

```json
[
  {
    "id": "ext-100",
    "name": "Anita Mehta",
    "email": "anita@example.com",
    "phone": "+91 9811111111",
    "alternate_phone": null,
    "address": "Andheri",
    "city": "Mumbai",
    "state": "Maharashtra",
    "country": "India",
    "donations": [
      {
        "donation_id": "don-100",
        "amount": 5000,
        "currency": "INR",
        "donated_at": "2026-01-15 10:00:00",
        "payment_status": "completed",
        "payment_method": "UPI"
      }
    ]
  }
]
```

Wrappers `{ "data": [ ... ] }`, `{ "donors": [ ... ] }` are also accepted.

## Field mappings

Defaults (overridable per connection):

| Local field | Source key |
|-------------|------------|
| donor_id | id |
| full_name | name |
| email | email |
| phone | phone |
| amount | amount |
| donated_at | donated_at |
| donation_id | donation_id |

## Idempotency

Upserts on `(organization_id, external_donor_id)` and `(organization_id, external_donation_id)`.

## Manual & scheduled sync

- UI: **Org profile → WordPress site** (or Team → WordPress site) → Test connection / Sync now (queued). Super Admin or Org Admin.
- Schedule: hourly for active connections (`routes/console.php`).
