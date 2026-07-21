# DonorConnect ↔ WordPress Bridge

Partner NGOs install the **DonorConnect Bridge** plugin next to **NGOBuddy**. Each organization has its own WordPress site and unique API key + HMAC secret so only that organization’s CRM workspace can sync their donors and Razorpay ledger.

## Architecture

```
NGOBuddy (donors + Razorpay donations + projects CPT)
        │
        ▼
DonorConnect Bridge (WP plugin)   ← per org site
  GET /wp-json/donorconnect/v1/donors     ←── HMAC-signed pull ── DonorConnect CRM (that org)
  GET /wp-json/donorconnect/v1/projects   ←── project + general donate URLs (tracking picker)
  POST /api/v1/ingest/donors              ── optional push ────►
```

## Who can connect

| Role | Access |
|------|--------|
| **Org Admin** | Connect / manage WordPress for their organization (Team → WordPress site, or Org profile) |
| **Super Admin** | Connect / manage WordPress for any organization (Organizations → Profile / WordPress, or switch org then WordPress site) |

Team Lead / Finance / Volunteers cannot manage Bridge credentials.

## Security

1. **Per-site credentials** — Site ID, API Key, HMAC secret generated on plugin activation; rotatable from WP admin.
2. **HMAC request signing** — timestamp + nonce + method + path + body hash; replay rejected via nonce transients.
3. **Optional IP allowlist** on the WordPress side.
4. **Encrypted credentials** in CRM (`organization_api_connections.credentials`) — one connection row per organization.
5. **Org-scoped API tokens** for push ingest (`AuthenticateOrgApiToken`).

## Razorpay keys + payment requests

1. Org profile / WordPress site → **Sync Razorpay keys** pulls NGOBuddy keys over HMAC into the organization record.
2. Volunteers/admins can **Send payment link** on a donor:
   - Preferred: CRM creates the link with synced keys
   - Fallback: CRM asks WordPress Bridge to create the link on the partner site using NGOBuddy keys
3. Optional live ledger: Bridge `GET /razorpay/payments`.

## Donation tracking link picker

Donor Show → **Donation tracking link** loads targets from Bridge `GET /projects`:

1. **General donation (NGOBuddy)** — theme `gdnb_theme_settings.donation_url`, else `/donate` page, else site home
2. **Published projects** — NGOBuddy `project` CPT permalinks (or custom `_gdnb_donation_url` when mode is custom/external)

CRM caches the list for 10 minutes per org connection. Without a Bridge connection, volunteers can still paste a custom URL.

## Setup

1. Install NGOBuddy + DonorConnect Bridge on **that org’s** WordPress site.
2. WP Admin → **DonorConnect** → Reveal secrets.
3. CRM → Org profile (or Team → **WordPress site**) for that organization.
4. Auth = HMAC; paste base URL + secrets; Test → Sync now.
5. (Optional) Create CRM API key; enable Bridge push for near-real-time.

Routes: `organizations/{id}/sync` (preferred). Legacy `/sync` still works when an org workspace is selected.

Plugin path in this repo: `wordpress-plugins/donorconnect-bridge/`
