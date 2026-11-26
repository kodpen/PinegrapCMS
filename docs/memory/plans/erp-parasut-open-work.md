---
name: erp-parasut-open-work
description: Paraşüt work left after the staging round of 2026-11-27: six bug issues, what staging could not try, the decisions #2440-#2442, the queue worker, incoming e-invoices; where to start
---

## Where does it stand?
- The order buttons, product and stock sync, inbox, connection card and driver fixes are merged (#1405-#1682): the `erp/erp-parasut-*.md` files.
- The live round the demo key's end (2026-10-25) had left open ran on 2026-11-27 against the staging company 35477 through the Developer Portal client (#11961), by k2's five local workers: passed. #1627, #1626 with the #2025 guard, #1628, #1629, #1630, #1660, #1682, #1631 and #1600 answered as written; draft, hand to GİB, ask the status, cancel and the one-press send work. The findings are in the `erp-parasut-*` files.
- Left on 35477: the default product 240384 only; the connecting user has "Uygulama Entegrasyonları: Yetki Var".

## What could staging not try?
- Every e-Arşiv job of 35477 ends at Paraşüt with "e-Belge entegrasyonunuz ile ilgili bir hata oluştu": the PDF, the cancel of a sent document and GİB's verdict were not reached. No buyer there is an e-Fatura payer, so no e-Fatura was sent.
- These wait for a company whose e-document integration works; nobody asks the owner for one, he says when there is one.

## What was decided? (#2440, #2441, #2442)
1. **Payment at Paraşüt:** a "Payment account" pick on the Paraşüt card from `GET accounts` (a non-VARCHAR `config` column, empty = today); when set, the order's paid moment posts `POST sales_invoices/{id}/payments`; not built.
2. **A reused buyer card** (the driver, and the legacy buttons since #1543): only its empty fields are filled; a value it has is never overwritten. Not in the code yet: bug issue #11984.
3. **A `sent` e-Invoice whose conversion failed** keeps refusing Cancel (first Ask After the Status; `erp/erp-parasut-sent-cancel.md`); the #2025 guard held on staging, but no sent e-Fatura could be made there, so it stays.

## Which bug issues are open? (one PR each, unit tests; tried live at the next round)
- #11977 a user without the integration permission connects with no warning, then meets a raw 403; #11978 the legacy Create Invoice takes the e-Arşiv's 202 for a failure; #11979 two inbox texts not translated.
- #11982 a product card opened for an invoice line takes the line's VAT rate (architect, 2026-11-27: the line is the sale, the card's rate a default; a stale catalogue rate must not stop an invoice).
- #11983 a 0% line with no exemption code is refused before any request, for Paraşüt and İşbaşı (the schema rule of `erp/erp-accounting-rules.md`; not confirmed live). #11984 above.

## What bigger work is open?
- **Queue worker** for `erp_edoc_queue` (nothing processes it; Paraşüt v4 has no webhook): create, convert, poll each tick while the 15-minute job window is open, reconcile from the document at 5 min, 30 min, 2 h, 6 h, 24 h, pay, cancel; after 6 tries `abandoned` and a panel warning. `erp_edoc_job` in `pg_cron_jobs()` (`'inline' => true`, 10 s budget) and a standalone `erp_edoc_job.php`. The e-document error notification and the automatic invoice's send wait on it (`erp/erp-auto-invoice-edocument.md`).
- **Incoming e-invoices:** the driver declares no `inbox`; `purchase_bills` are Paraşüt's own expense records. Demo probe 2026-10-24 (empty): `GET e_invoices` and `GET inbound_e_despatches` answer 200. Whether incoming ones sit in `e_invoices` needs a company with an e-document integration (`erp/erp-not-built-on-purpose.md`).
- A list the driver gains follows `meta.total_pages`: `page[size]` is capped at 25.

## Where to start?
- Only in a local session on the dev machine (dev database, the staging client: `erp/erp-parasut-devhub-staging.md`), k2's line; a cloud worker takes none of it.
- Read the key rules in `erp/README.md` and the `erp-parasut-*.md` files of the item; a bug issue is closed by its PR (`Fixes #n`). When nothing is left here, the lasting decisions move to `erp/` and this file is deleted.
