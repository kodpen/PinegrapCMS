---
name: erp-parasut-driver-design
description: Paraşüt e-document driver: what awaits the live test, what is still open (queue worker and reconcile ladder, account title lookup), decisions behind both
---

## Where does it stand?
- Written: the authorization-code connection (docs/memory/erp/erp-parasut-connection.md) and the driver's send / submit / poll / fetch_document / cancel path (docs/memory/erp/erp-parasut-driver.md), checked against the published swagger (apidocs.parasut.com/swagger.json, read 2026-10-19). The live run on dev.pinegrap.com with the owner's demo application settles the readings still marked UNVERIFIED in the code.

## How are Paraşüt credentials obtained and stored?
- Done: the authorization code (Connect on the Paraşüt card, `parasut_connect.php`) beside the password grant; the secret in `config.parasut_credentials_enc`, the token sealed in the service tokens; access token 2 h, refresh token rotates on every use.
- `client_id` / `client_secret` are not self-service; they come through Paraşüt support (destek@parasut.com), redirect `urn:ietf:wg:oauth:2.0:oob` unless another is registered with them.

## Which HTTP layer?
- Done: `erp_edoc_parasut_request()` on `erp_edoc_http()` + `erp_edoc_log()`; token renewal under `GET_LOCK`; one retry after a 401, one after a 429; `parasut_throttle()` keeps 10 requests per 10 s per process.
- Open: `page[size]` is capped at 25 (follow `meta.total_pages` in any list the driver gains).

## e-invoice or e-archive?
- Done: decided at conversion time with a fresh `e_invoice_inboxes` lookup, for TCKN numbers too; the card's alias wins when GİB still lists it; scenario from `erp_invoices.edoc_scenario`. A failed lookup refuses the conversion (the row stays `created`). Export invoices always go as e-Fatura (documentation); the driver refuses export types today.

## How does the async ladder run? (open)
- Today: `submit_invoice` keeps the trackable job (15 min) on the row and `poll` reads it on the operator's click; after the window the document itself is read. No cron reads it on its own and nothing processes `erp_edoc_queue`.
- Planned: a worker for `erp_edoc_queue` (create → convert → poll every tick while the window is open → reconcile from the document at 5 min, 30 min, 2 h, 6 h, 24 h → pay → cancel); after 6 tries `abandoned` + panel warning; `erp_edoc_job` in `pg_cron_jobs()` with `'inline' => true` and a 10 s budget, plus a standalone `erp_edoc_job.php`.

## Is there a webhook?
- No: the v4 API has no webhook or event subscription. State is only ever read back (poll, the queue worker); nothing listens for Paraşüt.

## Can the account card fill itself? (open)
- `e_invoice_inboxes` gives the alias and `name` (the registered title) and `registered_at`. GİB tax plate lookup has a captcha, MERSİS no open API. Address and tax office come from the customer or the order.
- Plan: a "Lookup" button beside the tax number that fills the title from `e_invoice_inboxes.name`, title correction in `erp_account_for_contact()`.

## How is testing done without a sandbox?
- Live tests only (the staging host does not take merchant credentials). The demo application the owner holds points at a demo company; low-value one-line invoices there, with the cancel path tried on each.
