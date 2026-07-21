# NGOBuddy structure (API & data reference)

> Snapshot of `/ngobuddy` so the folder can be removed later.  
> DonorConnect Bridge + CRM sync depend on these tables, options, and endpoints.  
> Generated for DonorConnect CRM integration.

---

## 1. Plugin identity

| Item | Value |
|------|--------|
| Plugin Name | NGOBuddy |
| Main file | `ngobuddy/ngobuddy.php` |
| Header Version | `2.0.1` |
| `GDNB_DONATIONS_VERSION` | `0.1.34` (runtime truth) |
| `GDNB_DONATIONS_DB_VERSION` | `2.12` |
| Text domain | `ngobuddy` |
| Migration const | `GDNB_MIGRATION_VERSION` = `1` |

### Table name constants (suffixes; real name = `$wpdb->prefix` + suffix)

| Constant | Suffix |
|----------|--------|
| `GDNB_DONATIONS_TABLE_DONORS` | `gdnb_donors` |
| `GDNB_DONATIONS_TABLE_DONATIONS` | `gdnb_donations` |
| `GDNB_DONATIONS_TABLE_TEAM_MEMBERS` | `gdnb_team_members` |
| `GDNB_DONATIONS_TABLE_VOLUNTEERS` | `gdnb_volunteers` |
| `GDNB_DONATIONS_TABLE_CERTIFICATES` | `gdnb_certificates` |
| `GDNB_DONATIONS_TABLE_DONOR_OTPS` | `gdnb_donor_otps` |
| `GDNB_DONATIONS_TABLE_VOLUNTEER_INTEREST` | `gdnb_volunteer_interest_submissions` |

### Primary settings option keys

| Option | Notes |
|--------|-------|
| `gdnb_donations_settings` | Main plugin settings (Razorpay, org, receipts) |
| `gdnb_donations_db_version` | Tracks DB version |
| `gdnb_donations_plans` | Razorpay plan cache `monthly_{paise}` → plan id |
| `gdnb_theme_settings` | Theme / donation button / hero / Instagram |
| `gdnb_volunteer_interest_settings` | Interest form emails |
| `gdnb_mail_settings` | SMTP / from |
| `gdnb_team_id_defaults` / `gdnb_volunteer_id_defaults` | ID card defaults |
| `gdnb_sheets_sync_api_key` / `gdnb_sheets_sync_allowed_emails` | Sheets REST auth |
| `gdnb_page_setup_map` | Generated pages map |
| `gdnb_manager_panel_page_id` | Manager front panel page |
| `gdnb_bank_accounts` / `gdnb_default_bank_account_id` | Project bank accounts |
| `gdnb_nbf_migration_version` | NBF→GDNB migration flag |
| `gdnb_setup_wizard_completed` / `_skipped` / `_do_redirect` | Setup wizard |
| `gdnb_core_pages_seeded`, `gdnb_hero_project_migrated`, `gdnb_manager_migrated_from_staff` | Misc flags |

---

## 2. Database tables

Created by `GDNB_Donations_DB::activate()` via `dbDelta`.

### `{prefix}gdnb_donors`

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED AI PK | |
| `name` | VARCHAR(191) NULL | |
| `email` | VARCHAR(191) NULL | KEY |
| `phone` | VARCHAR(32) NULL | KEY |
| `pan` | VARCHAR(32) NULL | KEY |
| `photo_url` | VARCHAR(255) NULL | |
| `created_at` / `updated_at` | DATETIME NOT NULL | |

**Match logic:** find/create by email OR phone OR pan.

### `{prefix}gdnb_donations` (Razorpay ledger)

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED AI PK | |
| `donor_id` | BIGINT UNSIGNED NULL | KEY → donors |
| `project_id` | BIGINT UNSIGNED NULL | KEY → CPT `project` |
| `amount` | DECIMAL(12,2) NOT NULL DEFAULT 0 | |
| `currency` | VARCHAR(8) NOT NULL DEFAULT `INR` | |
| `status` | VARCHAR(32) NOT NULL DEFAULT `created` | KEY; `created`, `paid`, `failed`, `authorized`, `cancelled`, `completed`, `active`, manual `paid` |
| `razorpay_order_id` | VARCHAR(191) NULL | KEY |
| `razorpay_payment_id` | VARCHAR(191) NULL | KEY; manual = `Cash Transaction` |
| `razorpay_signature` | VARCHAR(191) NULL | |
| `razorpay_subscription_id` | VARCHAR(191) NULL | KEY |
| `receipt_number` | VARCHAR(64) NULL | KEY |
| `receipt_sequence` | BIGINT UNSIGNED NULL | |
| `thankyou_token` | VARCHAR(64) NULL | KEY |
| `is_anonymous` | TINYINT(1) DEFAULT 0 | |
| `is_recurring` | TINYINT(1) DEFAULT 0 | |
| `wants_80g` | TINYINT(1) DEFAULT 0 | |
| `donor_name` / `donor_email` / `donor_phone` / `donor_pan` | denormalized snapshot | |
| `utm_source` … `utm_content` | VARCHAR(191) | KEY on source/campaign |
| `landing_url` / `referrer_url` | TEXT | |
| `essentials_json` | LONGTEXT | `{custom_amount, essentials_total, items[]}` |
| `donor_ip` | VARCHAR(45) | KEY |
| `geo_country_code` | VARCHAR(2) | KEY |
| `geo_country` / `geo_region` / `geo_city` | VARCHAR(100) | |
| `created_at` / `updated_at` | DATETIME | |

### `{prefix}gdnb_team_members`

`id`, `id_number` (UNIQUE), `first_name`, `middle_name`, `last_name`, `designation`, `email`, `website`, `emergency_contact`, `photo_url`, `primary_phone`, `secondary_phone`, `address`, `aadhaar_number`, `aadhaar_front_url`, `aadhaar_back_url`, `status` (default `active`), timestamps.

### `{prefix}gdnb_volunteers`

Same family as team; `id_number` nullable; also `aadhaar_card_url`, `validity_start`, `validity_end`; designation default `Volunteer`.

### `{prefix}gdnb_certificates`

`name`, `email`, `phone`, `type` (default `donation`), `institution`, `program_title`, internship fields, templates/bg, `admin_note`, `created_at`.

### `{prefix}gdnb_donor_otps`

`identifier`, `email`, `otp_hash`, `expires_at`, `verified_at`, `created_at`.

### `{prefix}gdnb_volunteer_interest_submissions`

`name`, `email`, `phone`, `city`, `availability`, `message`, `project_id`, `project_title`, email-delivery audit columns, `created_at`.

### Migrator (`gdnb-migrator.php`)

- Renames `nbf_*` → `gdnb_*` tables/options/meta/shortcodes  
- Does **not** change donation column schema beyond rename

---

## 3. Settings: `gdnb_donations_settings` keys

### Razorpay (required for DonorConnect Bridge payment sync/trigger)

| Key | Purpose |
|-----|---------|
| `razorpay_key_id` | Razorpay Key ID |
| `razorpay_key_secret` | Razorpay Key Secret |
| `razorpay_webhook_secret` | Webhook HMAC secret |
| `razorpay_mode` | `test` \| `live` |

### Donation UX

`accept_anonymous`, `enable_80g`, `enable_profile_edit_thankyou`, `suggested_amounts`, `min_amount`, `enable_otp_debug`, `enable_project_essentials`, `thankyou_page_url`, `facebook_pixel_id`

### Receipt email

`receipt_email_subject`, `receipt_email_from`, `receipt_email_from_address`

### Prefixes

`receipt_prefix`, `receipt_padding`, `certificate_prefix`, `volunteer_id_prefix`, `team_id_prefix`

### Org / 80G

`org_name`, `org_address`, `org_pan`, `org_80g_number`, `org_authorized_signatory`, `org_logo_url`, `org_signature_url`, `id_card_logo`

### Certificates

`certificate_bg_image`, `certificate_seal_image`, `certificate_body_text`, `volunteer_certificate_body_text`, `internship_message_templates`, `certificate_bg_templates`, `certificate_verify_url`, legacy internship mirrors

### Other options (shapes)

| Option | Shape |
|--------|-------|
| `gdnb_theme_settings` | Social URLs, `donation_url`, `donation_button_mode` (`redirect`\|`popup`), `donation_popup_project_id`, colors, Instagram, hero_*, etc. |
| `gdnb_volunteer_interest_settings` | `admin_to`, `admin_cc`, subjects/bodies, `send_user_email`, from_* |
| `gdnb_mail_settings` | `transport` (`site_default`\|`plugin_smtp`), from_*, `smtp_host/port/encryption/user/pass` |
| `gdnb_bank_accounts` | `[{id,label,bank_name,account_name,account_number,ifsc,upi_id,paytm_number,upi_qr_attachment_id,paytm_qr_attachment_id}]` |
| `gdnb_donations_plans` | `monthly_{amount_paise}` → Razorpay plan id |

---

## 4. REST API (`ngobuddy/v1`)

| Route | Method | Auth | Params | Response |
|-------|--------|------|--------|----------|
| `/razorpay-webhook` | POST | Public + HMAC header `X-Razorpay-Signature` vs `razorpay_webhook_secret` | Razorpay event JSON | `{message}` |
| `/sync` | GET | Headers `X-NGOBuddy-Key` + `X-NGOBuddy-Email` (or `api_key` query) | — | meta of resources |
| `/sync/{resource}` | GET | same | `page`, `per_page` (≤500) | paged rows |
| `/instagram-callback` | GET | public | OAuth | present in code; may be unloaded |

**`/sync/{resource}` resources:**  
`donations`, `donors`, `razorpay`, `volunteer-interest`, `volunteers`, `team-members`, `users`, `certificates`

---

## 5. AJAX actions (`admin-ajax.php`)

| Action | Priv | Cap / nonce | Request | Success shape |
|--------|------|-------------|---------|---------------|
| `gdnb_create_order` | both | `gdnb_donations_nonce` | `project_id`, `amount`, `name`, `email`, `phone`, `pan`, `is_anonymous`, `is_recurring`, `wants_80g`, UTM, `landing_url`, `referrer_url`, `essentials` JSON | `{order_id, subscription_id, key_id, amount(paise), currency, receipt}` |
| `gdnb_verify_payment` | both | same | `order_id` or `subscription_id`, `payment_id`, `signature` | `{message, receipt_url, thankyou_url}` |
| `gdnb_get_project_essentials` | both | same | `project_id` | `{enabled, project_id, items}` |
| `gdnb_volunteer_submit` | both | `gdnb_volunteer_nonce` | volunteer_* + project | `{message, admin_sent, user_sent}` |
| `gdnb_get_youtube_shorts` | both | none | `playlist_id`, `max` | video list |
| `gdnb_get_volunteer` | logged-in | `manage_options` | volunteer id | fields |
| `gdnb_get_home_carousel_admin_fields` | admin | `manage_options` | project id | carousel fields |
| `gdnb_get_hero_project_admin_fields` | admin | `manage_options` | project id | hero fields |
| `gdnb_demographics_backfill` | admin | nonce `gdnb_donation_demographics` | — | `{backfill, stats}` |
| `gdnb_rzp_fetch_entity` | admin | nonce `gdnb_rzp_transactions` | `type`, `id` | Razorpay entity JSON |
| `gdnb_setup_wizard_save_step` | admin | `manage_options` | step payload | wizard JSON |
| `gdnb_setup_wizard_skip` / `finish` | admin | `manage_options` | — | flags |

---

## 6. Admin POST actions (selected)

**Donations:** `gdnb_donations_export`, `gdnb_donations_send_receipt`, `gdnb_donations_manual_create`, `gdnb_donations_update_donor`, `gdnb_donations_update_row`, `gdnb_donations_repair_db`

**Certificates:** `gdnb_save_certificate`, `gdnb_resend_certificate`, `gdnb_download_certificate`, `gdnb_delete_certificate`

**ID cards:** `gdnb_volunteer_save|delete|pdf|defaults_save`, `gdnb_volunteer_register` (+ nopriv); same for `gdnb_team_member_*` / `gdnb_team_register`

**Volunteer interest:** `gdnb_volunteer_interest_resend`, `gdnb_volunteer_interest_delete`

**Manager panel:** `gdnb_manager_create|update|delete|reset_password|save_page|project_save|project_delete|appreciation_save|appreciation_delete|news_save|news_delete|save_cert_templates`

**Other:** `gdnb_page_setup_generate`, `gdnb_mail_test`, `gdnb_rzp_export`, `gdnb_contact` (+ nopriv), `gdnb_dismiss_bloodlink_notice`

---

## 7. Public donation / Razorpay flow

```
UI → AJAX gdnb_create_order
  → validate min amount, name, email, phone (≥10 digits), optional PAN for 80G
  → find/create donor (unless anonymous)
  → allocate receipt_number / receipt_sequence
  → recurring: plan in gdnb_donations_plans → subscriptions API
    else: POST https://api.razorpay.com/v1/orders
  → INSERT gdnb_donations status=created (+ razorpay_order_id or subscription_id)
  → return key_id + order/subscription id + amount paise
→ Checkout.js
→ AJAX gdnb_verify_payment
  → HMAC verify order_id|payment_id OR payment_id|subscription_id
  → UPDATE status=paid, razorpay_payment_id, razorpay_signature
  → bump project _gdnb_raised_amount / _gdnb_supporters_count
  → email receipt; thankyou_url
→ REST webhook (backup): payment.captured/failed/authorized, subscription.*
```

**Stored Razorpay fields:** `razorpay_order_id`, `razorpay_payment_id`, `razorpay_signature`, `razorpay_subscription_id`.

**Manual donations:** `status=paid`, `razorpay_payment_id='Cash Transaction'`.

---

## 8. DonorConnect Bridge field map

Used by `wordpress-plugins/donorconnect-bridge`.

### Donor (`gdnb_donors` → CRM)

| NGOBuddy | Bridge / CRM |
|----------|----------------|
| `id` | external id `ngobuddy-{id}` |
| `name` | `name` / `full_name` |
| `email` | `email` |
| `phone` | `phone` |
| — | `country` default `India` |

Orphans (`donor_id` null): id `rzp-orphan-{payment_id|id}`; use donation snapshot + geo + `utm_campaign`.

### Donation (`gdnb_donations` → CRM)

| NGOBuddy | Bridge |
|----------|--------|
| `razorpay_payment_id` (fallback order / `gdnb-don-{id}`) | `donation_id` / `external_donation_id` |
| `amount` | `amount` |
| `currency` | `currency` |
| `created_at` | `donated_at` |
| `status` (`paid`/`captured`/`success` → `completed`) | `payment_status` |
| — | `payment_method` = `Razorpay` |
| `utm_campaign` | `campaign` |
| `project_id` | `project_id` |
| `receipt_number` | `receipt_number` |
| `is_recurring` | `is_recurring` |
| UTM + landing | `source_data` |

**Default filter:** `status IN ('paid','captured','completed','success')`.

---

## 9. Custom post types & project meta

| Post type | Rewrite | Purpose |
|-----------|---------|---------|
| `project` | `project` | Campaigns / donation targets |
| `program` | `programs` | Program cards |
| `volunteer` | `volunteers` | Public volunteer stories |
| `team_member` | (none) | Team grid CPT |
| `appreciation` | `appreciations` | Testimonials |
| `gdnb_news` | `news` | Tax: `gdnb_news_category` |
| `gdnb_audit_report` | — | Tax: `gdnb_audit_report_type` |

### Project meta (donation-relevant)

`_gdnb_goal_amount`, `_gdnb_raised_amount`, `_gdnb_supporters_count`, `_gdnb_urgent`, `_gdnb_end_date`, `_gdnb_never_ending`, `_gdnb_duration_days`, `_gdnb_location`, `_gdnb_donation_url_mode`, `_gdnb_donation_url`, `_gdnb_project_essentials_enabled`, `_gdnb_project_essentials`, `_gdnb_bank_account_id`, `_gdnb_campaigner_name`, `_gdnb_beneficiary_*`, gallery/docs/updates, home carousel / hero / Instagram hashtag fields.

---

## 10. Hooks / filters

| Hook | Type | Use |
|------|------|-----|
| `gdnb_donations_validation_handlers` | filter | Extend receipt/ID validation |
| `gdnb_donor_profile_after_verified` | action | After OTP-verified profile |
| `gdnb_donation_thankyou_after_details` | action | Thank-you page |
| `gdnb_page_setup_definitions` | filter | Page generator |
| `gdnb_manager_modules` | filter | Manager modules |
| `gdnb_manager_module_actions_map` | filter | Capability map |
| `gdnb_manager_panel_sections` | action | Extra manager UI |

Role: `gdnb_manager`.

---

## 11. File map (major includes)

| File | Role |
|------|------|
| `ngobuddy.php` | Bootstrap, constants, activation |
| `includes/gdnb-prefix-helpers.php` | Asset URLs, acronym, prefixes |
| `includes/gdnb-migrator.php` | NBF → GDNB rename |
| `includes/class-ngobuddy-db.php` | Schema + default options |
| `includes/class-ngobuddy-ajax.php` | Create/verify order, essentials, webhook |
| `includes/class-ngobuddy-admin.php` | Settings, lists, export, manual donation |
| `includes/class-ngobuddy-public.php` | Donate / thank-you / profile shortcodes |
| `includes/class-gdnb-donation-geo.php` | IP + geo |
| `includes/class-gdnb-mailer.php` | SMTP + mail templates |
| `includes/class-gdnb-manager-panel.php` | Front manager role + CRUD |
| `includes/class-gdnb-razorpay-transactions.php` | Live Razorpay ledger admin |
| `includes/class-gdnb-sheets-sync.php` | Google Sheets REST sync |
| `includes/class-gdnb-donation-demographics.php` | Donation map + geo backfill |
| `includes/class-gdnb-admin-menu.php` | Admin menu |
| `includes/class-gdnb-certificates.php` | Certificates PDF / verify |
| `includes/class-gdnb-volunteer-id-cards.php` | Volunteer ID cards |
| `includes/class-gdnb-team-id-cards.php` | Team ID cards |
| `includes/class-gdnb-page-setup.php` | Generate core pages |
| `includes/class-gdnb-setup-wizard.php` | First-run wizard |
| `includes/class-gdnb-volunteer-interest.php` | Interest form |
| `includes/gdnb-projects.php` | `project` CPT + bank accounts |
| `includes/gdnb-project-essentials.php` | Essentials line-items |
| `includes/gdnb-shortcodes.php` | Public shortcodes aggregate |
| `includes/gdnb-admin-settings.php` | Theme settings |
| `includes/gdnb-form-handlers.php` | Contact form |
| `includes/gdnb-legacy-aliases.php` | Backward-compatible aliases |
| `includes/sections/*.php` | Render partials |
| `includes/templates/*` | Single/archive overrides |

---

## 12. What DonorConnect already consumes

From this structure, **DonorConnect Bridge** uses:

1. Tables: `gdnb_donors`, `gdnb_donations`  
2. Option: `gdnb_donations_settings` → `razorpay_key_id`, `razorpay_key_secret`, `razorpay_webhook_secret`, `razorpay_mode`  
3. Optional: NGOBuddy REST `/ngobuddy/v1/sync/*` (Sheets-style) — Bridge primarily reads DB directly  
4. Bridge own REST: `/donorconnect/v1/donors`, `/razorpay/*` (HMAC)

See also: `docs/wordpress-bridge.md`, `wordpress-plugins/donorconnect-bridge/`.

---

## Version caveat

Treat **`GDNB_DONATIONS_VERSION` (`0.1.34`)** and **`GDNB_DONATIONS_DB_VERSION` (`2.12`)** as runtime truth; plugin header `2.0.1` / readme may disagree.
