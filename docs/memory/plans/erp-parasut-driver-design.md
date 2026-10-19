---
name: erp-parasut-driver-design
description: Design decided for the unfinished Paraşüt e-document driver: credentials, HTTP layer, taxpayer check, scenario, async job ladder, totals check, webhook, account title lookup
---

## How are Paraşüt credentials obtained and stored?
- Done: the authorization-code flow (Connect on the Paraşüt card, `parasut_connect.php`) beside the password grant; the refresh token is used; the secret stays in `config.parasut_credentials_enc`, the token sealed in the service tokens. Details: docs/memory/erp/erp-parasut-connection.md.
- Still to verify live (demo application, 2026-10-19): that Paraşüt accepts `authorization_code` with the out-of-band redirect, the token lifetime, whether the refresh token rotates on use.
- `client_id` / `client_secret` are not self-service; they come through Paraşüt support.

## Which HTTP layer?
- `erp_edoc_http()` (shared driver helper) or `api_http_request()`; with the latter pass `timeout => 30` and `max_body => 0` (the 1000-byte default silently cuts a JSON reply).
- One token refresh at a time under `GET_LOCK`. 401 → refresh once; 401 again → error recorded, queue `failed`, red badge.
- `page[size]` is silently capped at 25: use 25 and follow `meta.total_pages`. No published rate limit: stay near 5 requests/s, back off on 429.

## e-invoice or e-archive?
- Decided at issue time with a fresh `e_invoice_inboxes` lookup (`parasut_check_einvoice_address()`), for TCKN numbers too (sole traders can be taxpayers). Cached `einvoice_*` fields are display only (≤24 h).
- Lookup fails → the document is not issued and waits in the queue.
- Several inboxes may come back: store all in `einvoice_aliases`; the chosen alias goes as `to`.
- Scenario `basic` or `commercial` (`erp_invoices.edoc_scenario`, store default `config.erp_einvoice_scenario`); old code always sent `commercial` and never `to`. Only `commercial` can be rejected.

## How does the async ladder run?
- Steps: create (contact upsert + sales invoice) → convert (e-invoice / e-archive create, returns a `trackable_job` valid 15 min) → poll every tick until the window closes → reconcile from the document itself at 5 min, 30 min, 2 h, 6 h, 24 h → pay → cancel (long ladder). After 6 tries `abandoned` + panel warning.
- Reconcile is the guarantee, poll only speeds things up (a 5-minute cron may miss the window).
- Counted in cron ticks, not seconds (`job.php` runs from the operator's crontab).
- Job `erp_edoc_job` in `pg_cron_jobs()` with `'inline' => true` (outside the rotation, called on every tick, still gated by `pg_cron_job_is_enabled()`), 10 s budget; also a standalone `erp_edoc_job.php`. Reason: a backup can hold the rotation lock for an hour.

## What must match exactly?
- Paraşüt computes VAT itself. The `gross_total` sent and returned must be identical; otherwise the document is not issued, the queue item fails and the operator is warned. Minor units → TRY conversion in one place (`includes/erp/parasut/map.php`).

## Can the incoming webhook be trusted?
- No documented HMAC signature. `parasut_webhook.php` uses an unguessable token in the URL, but its body is never a data source: it only queues a reconcile, and the state is read back with our own credentials. Register it in the WAF sensitive scripts and `waf_is_api_request()`.

## Can the account card fill itself?
- `e_invoice_inboxes` gives title and taxpayer status (title not yet seen live). GİB tax plate lookup has a captcha, MERSİS no open API, paid integrator lookups need a decision. Address and tax office come from the customer or the order.
- Plan: a "Lookup" button beside the tax number, title correction in `erp_account_for_contact()`, optional nightly refresh. First call with real credentials: query one tax number and look at the reply.

## How is testing done without a sandbox?
- `parasut_use_sandbox` does nothing: live tests only, with a written cancellation procedure and low-value one-line invoices to our own tax number.
