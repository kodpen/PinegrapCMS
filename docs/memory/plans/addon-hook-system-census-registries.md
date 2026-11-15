---
name: addon-hook-system-census-registries
description: Add-on hook system AH1 census, other registries: how SMS providers, e-document drivers, scheduled jobs, address autocomplete and other provider lists are found and loaded today
---

## How is an SMS provider found? (SMS)
- Contract: `pg_sms_<key>_send()` and `pg_sms_<key>_senders()` required, `_number()` optional (`includes/fn/sms.php`).
- Shipped: `pg_sms_providers()`: `netgsm`, `iletimerkezi`, `twilio`. `pg_sms_driver()` requires `includes/sms/<file>`, a fixed folder.
- Settings: `connect_apps` `sms_<key>`, password sealed.
- Outside provider: no register function; it needs a `pg_sms_providers()` entry. Incoming SMS is wired by name: `pg_sms_incoming_ways()` (`includes/fn/sms_inbox.php`), an if/else in `pg_sms_inbox_fetch()`, the webhook accepting only `twilio`, `sms_webhook.php?provider=twilio`.

## How is an e-document driver found? (E-documents)
- Contract: `erp_edoc_<code>_<op>`, `info` required (capabilities inside), callers `erp_edoc_call()` / `erp_edoc_supports()` (`includes/erp/edoc/registry.php`).
- Shipped: `erp_edoc_drivers()` maps `parasut` and `isbasi` to absolute paths; `erp_edoc_load()` requires the file. The active one is the `erp_edoc_providers` row with `is_active = 1` (`ERP_PARASUT_ENABLED` falls back to Paraşüt).
- Settings: `erp_edoc_providers` (`provider` VARCHAR(20), `credentials_enc`, `settings`).
- Outside driver: an `erp_edoc_drivers()` entry (absolute paths already work), no register function; the code fits 20 characters (`erp_invoices.edoc_provider`, `erp_waybills.edoc_provider`). Paraşüt has its own hard-coded paths (`includes/fn/parasut.php`, `get_erp_edoc_test.php`).

## How is a scheduled job found? (Jobs, cron tick)
- `job.php`, called by cron every minute, runs a fixed sequence of inline `pg_*_run()` calls (gated by `pg_cron_job_is_enabled()` or due stamps), then `pg_cron_dispatch_next()` (`includes/fn/cron.php`).
- `pg_cron_jobs()` is a hard-coded array (label, `script` relative to `PG_FUNCTIONS_DIR`, interval, lane, lock, `dispatch`, `inline`). Dispatch takes only names in `config.job_dispatch` (Maintenance settings), claims a lock in `cron_runs`, and `job.php` includes the script with `PG_CRON_DISPATCH`; the job ends with `pg_cron_ran($name)`.
- Outside job: no register function; it needs a `pg_cron_jobs()` entry, a script under `PG_FUNCTIONS_DIR`, and its name in `config.job_dispatch`, or an inline call edited into `job.php`. `health.php`, the API meta resource, System Status and ops alerts read `pg_cron_jobs()`.
- No per-tick hook exists; the inline sequence in `job.php` is the nearest "cron tick".

## Which other provider lists exist?
- Address autocomplete: `pg_addr_providers()` (`google_places`, `mapbox`, `photon`), files in `includes/geo/autocomplete/`, settings `connect_apps` `addr_<key>`.
- Connected accounts: the only folder scan, `pg_connect_definitions()` globs `includes/connect/{providers,purposes}/*.php` (file name is the key); MCP adds runtime builders through `pg_connect_provider_builders()`.
- Hard-coded lists with no register function: `ws_model_providers()` (AI, data only), `pg_tr_engines()` (translation), `pg_captcha_providers()`, `pg_cloud_picker_providers()`, `pg_signin_providers()`, `ws_mail_providers()`, `pg_search_audits_engines()`.
