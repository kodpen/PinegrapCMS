---
name: erp-parasut-driver-design
description: Paraşüt e-document driver: what is written and awaits the live test (2026-10-19), what is still open (queue worker and reconcile ladder, webhook, account title lookup), decisions behind both
---

## Where does it stand?
- Written (cloud thread, owner's assignment 2026-10-19): the authorization-code connection (docs/memory/erp/erp-parasut-connection.md) and the driver's send / submit / poll / fetch_document / cancel path (docs/memory/erp/erp-parasut-driver.md). Nothing of it has run against `api.parasut.com` yet: the cloud session has no route to it, the live test goes through dev.pinegrap.com with the demo application once the code is pulled there.
- The unverified readings are listed in erp-parasut-driver.md; each has a comment in the code where it is read. The first live run settles them.

## How are Paraşüt credentials obtained and stored?
- Done: the authorization code (Connect on the Paraşüt card, `parasut_connect.php`) beside the password grant; the refresh token is used; the secret stays in `config.parasut_credentials_enc`, the token sealed in the service tokens.
- To verify live: Paraşüt accepts `authorization_code` with the out-of-band redirect; the token lifetime; whether the refresh token rotates on use.
- `client_id` / `client_secret` are not self-service; they come through Paraşüt support.

## Which HTTP layer?
- Done: `erp_edoc_parasut_request()` on `erp_edoc_http()` + `erp_edoc_log()`; token renewal under `GET_LOCK` in `parasut_get_token()`; one retry after a 401.
- Open: `page[size]` is capped at 25 (follow `meta.total_pages` in any list the driver gains); no published rate limit: stay near 5 requests/s, back off on 429.

## e-invoice or e-archive?
- Done: decided at conversion time with a fresh `e_invoice_inboxes` lookup, for TCKN numbers too; the card's alias wins when GİB still lists it; scenario from `erp_invoices.edoc_scenario`. A failed lookup refuses the conversion (the row stays `created`, the hand-over button returns).

## How does the async ladder run? (open)
- Today: `submit_invoice` keeps the trackable job (15 min) on the row and `poll` reads it on the operator's click; after the window the document itself is read. No cron reads it on its own and nothing processes `erp_edoc_queue`.
- Planned: a worker for `erp_edoc_queue` (create → convert → poll every tick while the window is open → reconcile from the document at 5 min, 30 min, 2 h, 6 h, 24 h → pay → cancel); after 6 tries `abandoned` + panel warning; counted in cron ticks; `erp_edoc_job` in `pg_cron_jobs()` with `'inline' => true` and a 10 s budget, plus a standalone `erp_edoc_job.php` (a backup can hold the rotation lock for an hour).

## What must match exactly?
- Done: the `gross_total` Paraşüt answers is compared with the ERP's (`erp_edoc_parasut_expected_totals()`); a record that differs is deleted and nothing issued. To verify live: whether `gross_total` is read before or after the withholding.

## Can the incoming webhook be trusted? (open)
- No documented HMAC signature. A `parasut_webhook.php` would use an unguessable token in the URL, and its body is never a data source: it only queues a reconcile, and the state is read back with our own credentials. Register it in the WAF sensitive scripts and `waf_is_api_request()`.

## Can the account card fill itself? (open)
- `e_invoice_inboxes` gives the alias; title and taxpayer status not yet seen live. GİB tax plate lookup has a captcha, MERSİS no open API, paid integrator lookups need a decision. Address and tax office come from the customer or the order.
- Plan: a "Lookup" button beside the tax number, title correction in `erp_account_for_contact()`, optional nightly refresh.

## How is testing done without a sandbox?
- `parasut_use_sandbox` does nothing: live tests only. The demo application the owner holds (2026-10-19) points at a demo company; low-value one-line invoices there, with the cancel path tried on each.
