---
name: erp-parasut-open-work
description: Paraşüt work left after the rounds of 2026-10-23 to 26 (#1405-#1682): one live round when a new demo key comes, three owner questions, small fixes; where to start
---

## Where does it stand?
- The legacy order buttons, product and stock sync, inbox, connection card and driver fixes are merged: #1405-#1418, #1458, #1505-#1518, #1530, #1543, #1544, #1599, #1600, #1626-#1631, #1660, #1682. What each does: the `erp/erp-parasut-*.md` topic files.
- The demo company 830533's API key expired on 2026-10-25. Everything changed after that is unit-tested only. The demo was left at its baseline: 13 contacts, 0 sales invoices, 0 shipment documents, 9 products (archived included: 14 / 0 / 0 / 12).

## What waits for a new demo key?
- One live round of what never ran against Paraşüt:
  - #1627: the last commit of the stale-id resend;
  - #1600: purchase-bill totals (no purchase bill was ever read live);
  - #1626: a driver poll's 404 frees the row;
  - #1628 and #1660: the order gates and the ERP badge;
  - #1629: the ERP send of a legacy-invoiced order;
  - #1630: the opening stock and the per-product seed;
  - #1631: the card-failure refusal and the card currency;
  - #1682: the refusal texts.
- How: as the "How was it checked live?" sections of the `erp-parasut-*` files did it. Run the branch code against the dev database, with dev's `pinegrap/data/config.php` copied over temporarily and never committed. Ledger every record created, delete them all at the end, and compare the demo with a baseline taken first. Dev orders 438-444 (contact 140) are the test orders: record and restore every column a run changes.

## What does the owner decide?
1. **Payment at Paraşüt.** Nothing records an order's payment on its Paraşüt sales invoice, so it stays an open receivable there. v4 takes `POST sales_invoices/{id}/payments` with a cash or bank account (`GET accounts` lists them). That needs a setting (a schema column) and `registry.php`, which is the owner's area.
2. **A reused buyer card** (the driver, and the legacy buttons since #1543) keeps its old address and e-mail. Should each invoice update it?
3. **A `sent` e-Invoice whose conversion failed** cannot be cancelled from the ERP. #1626 left that out as unsafe: `erp/erp-parasut-sent-cancel.md`.

## Which small fixes are left? (one PR each, unit tests)
- #1628: an ERP record deleted on Paraşüt's screen keeps Create Invoice refused until the ERP invoice is cancelled. Orders from before 2026.4.4 can show "Sent" after a failed e-document, because the flag was set then. Record both in `erp-parasut-legacy-order-gates.md`.
- #1629: done in #2001 (the 404 legacy id is cleared only once the send goes on).
- #1630: a press that opens a card and then fails leaves the card higher by the order's units until the document is sent. Record it in `erp-parasut-product-card-opening-stock.md`.
- #1626: done in #2025 (a deleted-record 404 frees a row only when the company holds another record it names).
- Bigger, kept elsewhere: the `erp_edoc_queue` worker (`erp-parasut-driver-design.md`); incoming e-invoices through `GET e_invoices`, which needs a company with an e-document integration.

## Where to start?
- Read the key rules in `erp/README.md` and the `erp-parasut-*.md` files of the item. Claim it with a draft PR `[plan:erp-parasut-open-work] <item>`. Mark the item done here with its PR number. When the plan is empty, move its lasting decisions to `erp/` and delete it.
