# DonorConnect ↔ WordPress Bridge

Partner NGOs install the **DonorConnect Bridge** plugin next to **NGOBuddy**. Each tenant site has unique API key + HMAC secret so only that organization’s CRM workspace can sync their donors and Razorpay ledger.

## Architecture

```
NGOBuddy (donors + Razorpay donations)
        │
        ▼
DonorConnect Bridge (WP plugin)
  GET /wp-json/donorconnect/v1/donors   ←── HMAC-signed pull ── DonorConnect CRM (per org)
  POST /api/v1/ingest/donors            ── optional push ────►
```

## Security

1. **Per-site credentials** — Site ID, API Key, HMAC secret generated on plugin activation; rotatable from WP admin.
2. **HMAC request signing** — timestamp + nonce + method + path + body hash; replay rejected via nonce transients.
3. **Optional IP allowlist** on the WordPress side.
4. **Encrypted credentials** in CRM (`organization_api_connections.credentials`).
5. **Org-scoped API tokens** for push ingest (`AuthenticateOrgApiToken`).

## Razorpay keys + payment requests

1. CRM **API Sync → Sync Razorpay keys** pulls NGOBuddy `razorpay_key_id` / `razorpay_key_secret` over HMAC into the organization record.
2. Volunteers/admins can **Send payment link** on a donor:
   - Preferred: CRM creates the link with synced keys
   - Fallback: CRM asks WordPress Bridge to create the link on the partner site using NGOBuddy keys
3. Optional live ledger: Bridge `GET /razorpay/payments`.

1. Install NGOBuddy + DonorConnect Bridge on the org WordPress site.
2. WP Admin → **DonorConnect** → Reveal secrets.
3. CRM → switch to that organization → **API Sync**.
4. Auth = HMAC; paste base URL + secrets; Test → Sync now.
5. (Optional) Create CRM API key; enable Bridge push for near-real-time.

Plugin path in this repo: `wordpress-plugins/donorconnect-bridge/`
