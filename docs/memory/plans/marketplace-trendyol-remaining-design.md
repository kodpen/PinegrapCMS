---
name: marketplace-trendyol-remaining-design
description: Design of the open Trendyol steps (2b-ii, 5b, 5c) from a full build finished 2026-10-24 that never reached GitHub; what to build, deviations, unverified doc facts
---

## What is this?
- A build of every open step of `marketplace-trendyol-hepsiburada.md` was finished and tested on 2026-10-24 (branch `feature/trendyol-remaining-steps`, 9 commits, never pushed). These are its decisions, so a session rebuilding a step follows them. None of these names exist on development yet, except the two columns below.

## Which columns do 2b-ii and 5a use?
- `marketplace_product_map.commission` and `marketplace_order_map.invoice_sent` exist since #1687 (`commerce/marketplace-commission-and-invoice-columns.md`); read and write them only after `mp_commission_ready()` / `mp_invoice_sent_ready()`.

## 2b-ii. Listing update?
- The approval read stores the commission; the matched list shows "Commission 7.83 %".
- Approved listings go through `content-bulk-update`, keyed by `contentId`, with title, description and https images only (no attributes).
- New queue operation `listing_update`, sent after new listings and before the stock batch; its result is matched by `remote_id`.
- Screen: tick boxes and "Send changes to Trendyol". Rows are queued for the selected account only, not through `mp_enqueue()`. A result never writes the mapping row; a failure shows under "What did not go through" as "Listing changes".

## 5a. Invoice link?
- Built (#1747): `commerce/trendyol-invoice-link.md`.

## 5b and 5c. Returns and questions?
- "Returns from Trendyol" card: reads the last 14 days on a button (at most 4 pages of 50); approve sends the waiting units of the last read after a confirm. Rejecting with reason codes is left.
- "Questions from Trendyol" card: reads on a button; each waiting question has a 10–2000 character textarea and an Answer button.
- Both keep the read in `$_SESSION['software']['marketplace_read']`, never in a table.

## Tests?
- `tests/marketplace_trendyol_remaining_test.php` (red first per part), claims and questions fixtures; `tests/message_values_commerce_test.php` counts the new escaped warnings.

## What did the docs not prove?
- The content update's batch result line (assumed keyed by `requestItem.contentId`); every claims field, the largest claims page and whether `orderOutboundPackageId` is the imported package id; a successful claim approval's and answer's bodies; a question's status after answering. The return reasons page was not read.
