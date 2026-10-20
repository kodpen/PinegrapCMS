---
name: erp-parasut-driver-design
description: Paraşüt e-document driver: what awaits the live test, what is still open (queue worker and reconcile ladder), the decisions behind it
---

## Where does it stand?
- Written and checked against the published swagger: the authorization-code connection (docs/memory/erp/erp-parasut-connection.md) and the driver (docs/memory/erp/erp-parasut-driver.md). Live on dev.pinegrap.com with the owner's demo company (830533) on 2026-10-19: connection, contacts, sales invoice, totals (withholding scale and `net_total` settled, #630), poll, delete. The demo company has no e-document integration, so conversion, job, PDF and e-Arşiv cancel are still untried; ask Paraşüt support for a test e-Arşiv / e-Fatura integration on it, or use a company that has one.
- Account sync (`accounts`, #711, docs/memory/erp/erp-parasut-accounts.md): reading, linking by tax number and writing a card (PUT, POST) work live (2026-10-20).

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

## Can incoming e-invoices come through Paraşüt?
- No: v4 lists no incoming e-invoices (`e_invoices` has create, show and pdf only) and hands out no UBL; `purchase_bills` are Paraşüt's own expense records (what `view_parasut_inbox.php` lists). The driver declares no `inbox`.

## Can the account card fill itself?
- Done (#625): Query beside the tax number on the account form fills the name from GİB's register (docs/memory/erp/erp-taxpayer-lookup.md). GİB tax plate lookup has a captcha, MERSİS no open API: address and tax office still come from the customer or the order, and a number that is not an e-Invoice user gets no name.
- Done: an account opened from an order takes the name GİB registers its VKN under (docs/memory/erp/erp-taxpayer-lookup.md).

## How is testing done without a sandbox?
- Live tests only (the staging host does not take merchant credentials). The owner's demo application points at a demo company; low-value one-line invoices there, with the cancel path tried on each.
