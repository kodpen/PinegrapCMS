---
name: erp-parasut-open-work
description: Paraşüt work left after the rounds of 2026-10-23 to 26 (#1405-#1682): one live round when a new demo key comes, three decisions (#2440-#2442), the queue worker, incoming e-invoices; where to start
---

## Where does it stand?
- The legacy order buttons, product and stock sync, inbox, connection card and driver fixes are merged (#1405-#1682); what each does: the `erp/erp-parasut-*.md` topic files.
- The demo company 830533's API key expired on 2026-10-25. Everything changed after that is unit-tested only. The demo was left at its baseline: 13 contacts, 0 sales invoices, 0 shipment documents, 9 products (archived included: 14 / 0 / 0 / 12).

## What waits for a new demo key?
- One live round of what never ran against Paraşüt:
  - #1627: the last commit of the stale-id resend;
  - #1600: purchase-bill totals (no purchase bill was ever read live);
  - #1626: a driver poll's 404 frees the row; #2025: only when the company answers for the row's contact or product card (404 wording of `contacts/{id}`, `products/{id}`; a job read per company);
  - #1628 and #1660: the order gates and the ERP badge;
  - #1629: the ERP send of a legacy-invoiced order;
  - #1630: the opening stock and the per-product seed;
  - #1631: the card-failure refusal and the card currency;
  - #1682: the refusal texts.
- How: as the "How was it checked live?" sections of the `erp-parasut-*` files did it. Run the branch code against the dev database, with dev's `pinegrap/data/config.php` copied over temporarily and never committed. Ledger every record created, delete them all at the end, and compare the demo with a baseline taken first. Dev orders 438-444 (contact 140) are the test orders: record and restore every column a run changes.

## What was decided? (#2440, #2441, #2442)
1. **Payment at Paraşüt:** a "Payment account" pick on the Paraşüt card from `GET accounts` (a non-VARCHAR `config` column, empty = today); when set, the order's paid moment posts `POST sales_invoices/{id}/payments`; verified live when a demo key comes.
2. **A reused buyer card** (the driver, and the legacy buttons since #1543): only its empty fields are filled; a value it has is never overwritten.
3. **A `sent` e-Invoice whose conversion failed** keeps refusing Cancel (first Ask After the Status; `erp/erp-parasut-sent-cancel.md`); reconsidered once the live round confirms the #2025 guard.

## Which small fixes are left? (one PR each, unit tests)
- Done: #1628 #2032, #1629 #2001, #1630 #2032, #1626 #2025.

## What bigger work is open?
- **Queue worker** for `erp_edoc_queue` (nothing processes it; Paraşüt v4 has no webhook): create, convert, poll each tick while the 15-minute job window is open, reconcile from the document at 5 min, 30 min, 2 h, 6 h, 24 h, pay, cancel; after 6 tries `abandoned` and a panel warning. `erp_edoc_job` in `pg_cron_jobs()` (`'inline' => true`, 10 s budget) and a standalone `erp_edoc_job.php`. The e-document error notification and the automatic invoice's send wait on it (`erp/erp-auto-invoice-edocument.md`).
- **Incoming e-invoices:** the driver declares no `inbox`; `purchase_bills` are Paraşüt's own expense records. Demo probe 2026-10-24 (empty): `GET e_invoices` 200 (filters `issue_date`, `scenario`, `net_total`; includes `invoice`, `responses`), `GET inbound_e_despatches` 200 (incoming e-waybills). Whether incoming ones sit in `e_invoices` needs a company with an e-document integration (`erp/erp-not-built-on-purpose.md`).
- A list the driver gains follows `meta.total_pages`: `page[size]` is capped at 25.

## Where to start?
- Only in the owner's local session on the dev machine (dev database, the demo key: `workflow/sandbox-and-environment.md`); a cloud worker never takes these items, so a coordinator does not offer them.
- Read the key rules in `erp/README.md` and the `erp-parasut-*.md` files of the item. Claim it with a draft PR `[plan:erp-parasut-open-work] <item>`. Mark the item done here with its PR number. When the plan is empty, move its lasting decisions to `erp/` and delete it.
