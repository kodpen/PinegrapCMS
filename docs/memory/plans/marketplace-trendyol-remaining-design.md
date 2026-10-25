---
name: marketplace-trendyol-remaining-design
description: Design of the open Trendyol steps (schema, 2b-ii, 5a-5c) from a full build finished 2026-10-24 that never reached GitHub; what to build, deviations, unverified doc facts
---

## What is this?
- A build of every open step of `marketplace-trendyol-hepsiburada.md` was finished and tested on 2026-10-24 (branch `feature/trendyol-remaining-steps`, 9 commits, never pushed). These are its decisions, so a session rebuilding a step follows them. None of these names exist on development yet.

## B. Schema? (owner asked for it; recheck the label)
- Step `upgrade_2026_11_0_marketplace_columns()`, label 11.0.34 (free on 2026-10-24): `marketplace_product_map.commission` DECIMAL(6,2) NULL and `invoice_sent` INT UNSIGNED DEFAULT 0 on the imported order's map row. Name the marketplace columns for the `[SCHEME]` line in the PR body (the merging session writes it). Code checks the columns first (`mp_commission_ready()`, `mp_invoice_sent_ready()`).

## 2b-ii. Listing update?
- The approval read stores the commission; the matched list shows "Commission 7.83 %".
- Approved listings go through `content-bulk-update`, keyed by `contentId`, with title, description and https images only (no attributes).
- New queue operation `listing_update`, sent after new listings and before the stock batch; its result is matched by `remote_id`.
- Screen: tick boxes and "Send changes to Trendyol". Rows are queued for the selected account only, not through `mp_enqueue()`. A result never writes the mapping row; a failure shows under "What did not go through" as "Listing changes".

## 5a. Invoice link?
- Fallback: the ERP e-document PDF is only behind the panel login, so there is no automatic job. Each Imported orders row has a "Send invoice link" form for a URL the operator types; success writes `invoice_sent`.
- A refusal is a screen warning, not `last_error` (the own-carrier job skips rows that have one). Removing a link is not built: the delete call needs the buyer's customerId, which the import does not keep.

## 5b and 5c. Returns and questions?
- "Returns from Trendyol" card: reads the last 14 days on a button (at most 4 pages of 50); approve sends the waiting units of the last read after a confirm. Rejecting with reason codes is left.
- "Questions from Trendyol" card: reads on a button; each waiting question has a 10–2000 character textarea and an Answer button.
- Both keep the read in `$_SESSION['software']['marketplace_read']`, never in a table.

## Tests?
- `tests/marketplace_trendyol_remaining_test.php` (red first per part), claims and questions fixtures; `tests/message_values_commerce_test.php` counts the new escaped warnings.

## What did the docs not prove?
- The content update's batch result line (assumed keyed by `requestItem.contentId`); the invoice-link 201 and 409 bodies; every claims field, the largest claims page and whether `orderOutboundPackageId` is the imported package id; a successful claim approval's and answer's bodies; a question's status after answering. The return reasons page was not read.
