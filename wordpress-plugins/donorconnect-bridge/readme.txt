# DonorConnect Bridge (WordPress)

Secure companion plugin for **NGOBuddy** partner sites. Install on every organization/tenant WordPress so DonorConnect CRM can pull (or receive) donors + Razorpay donation ledger data.

## Install

1. Copy `wordpress-plugins/donorconnect-bridge` into `wp-content/plugins/donorconnect-bridge`.
2. Activate **DonorConnect Bridge** in WP Admin.
3. Keep **NGOBuddy** active (Bridge reads `gdnb_donors` / `gdnb_donations` including Razorpay payment IDs).

## High-security linking

Each site gets unique credentials on activation:

| Value | Purpose |
|-------|---------|
| **Site ID** | Public tenant site identifier |
| **API Key** | Presented as `X-DC-API-Key` / Bearer |
| **HMAC Secret** | Signs every CRM request (never leave WP without HTTPS) |

### Request signature (CRM → WordPress)

Headers:

- `X-DC-API-Key`
- `X-DC-Timestamp` (unix seconds, ±5 min)
- `X-DC-Nonce` (16–64 chars, single-use)
- `X-DC-Signature` = `HMAC-SHA256( "{timestamp}.{nonce}.{METHOD}.{path}?{sortedQuery}.{sha256(body)}" , hmac_secret )`

`path` is the WP REST route **without** `/wp-json` (example: `/donorconnect/v1/donors?page=1&per_page=100`).

Optional: CRM IP allowlist + disable HMAC only for local testing.

### Endpoints

Base: `https://{partner-site}/wp-json/donorconnect/v1`

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Connectivity + NGOBuddy table status (+ donation_url / projects_count) |
| GET | `/donors?page=&per_page=` | Donors with nested Razorpay donations (CRM sync format) |
| GET | `/projects` | Published NGOBuddy `project` CPT posts + general donation URL |

## CRM configuration

In DonorConnect → **Insights → API Sync**:

1. Base URL: `https://partner.org/wp-json/donorconnect/v1`
2. Auth: **HMAC (DonorConnect Bridge)**
3. Paste Site ID, API Key, HMAC Secret from WP → DonorConnect → Reveal secrets
4. **Test connection** then **Sync now**

## Optional push (WordPress → CRM)

1. In CRM create an org API token (Platform → API keys).
2. In WP Bridge settings: CRM base URL + org token, enable push.
3. Hourly cron or **Push now** posts to `POST /api/v1/ingest/donors`.

## Razorpay from partner site

Bridge can also expose the NGOBuddy Razorpay account for that WordPress site:

| Method | Path | Description |
|--------|------|-------------|
| GET | `/razorpay/status` | Configured? masked key id (no secrets) |
| GET | `/razorpay/config` | Full key id + secret for CRM sync (HMAC only) |
| GET | `/razorpay/payments` | Live Razorpay payments list |
| POST | `/razorpay/payment-links` | Create payment link using WP keys |
| POST | `/razorpay/orders` | Create order using WP keys |

### In CRM

1. **API Sync → Check WP Razorpay** — confirms keys exist on the site  
2. **API Sync → Sync Razorpay keys** — copies keys into the org (encrypted) so CRM can charge directly  
3. **Donor → Send payment link** — creates Razorpay payment link  
   - Uses CRM-stored keys when available  
   - Otherwise proxies through WordPress Bridge (`POST …/razorpay/payment-links`) so payment still works without storing secrets

- Donor: name, email, phone (`external_donor_id` = `ngobuddy-{id}`)
- Donation: amount, currency, status, `razorpay_payment_id` / order id, UTM campaign, receipt number

Idempotent upserts in CRM on `(organization_id, external_donor_id)` and `(organization_id, external_donation_id)`.
