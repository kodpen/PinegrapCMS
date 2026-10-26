---
name: marketplace-trendyol-remaining-design
description: Design of the open Trendyol step (5c) from a full build finished 2026-10-24 that never reached GitHub; what to build, deviations, unverified doc facts
---

## What is this?
- A build of every open step of `marketplace-trendyol-hepsiburada.md` was finished and tested on 2026-10-24 (branch `feature/trendyol-remaining-steps`, 9 commits, never pushed). These are its decisions, so a session rebuilding a step follows them. Names of the open steps do not exist on development yet.

## 2b-ii. Listing update?
- Built (#1761): `commerce/trendyol-listing-update.md`.

## 5a. Invoice link?
- Built (#1747): `commerce/trendyol-invoice-link.md`.

## 5b. Returns?
- Built (#1772): `commerce/trendyol-returns.md`. Rejecting with reason codes is left.

## 5c. Questions?
- "Questions from Trendyol" card: reads on a button; each waiting question has a 10–2000 character textarea and an Answer button.
- Keeps the read in `$_SESSION['software']['marketplace_read'][<account id>]['questions']`, never in a table, as returns do.

## Tests?
- `tests/marketplace_trendyol_remaining_test.php` (red first per part), a questions fixture (claims done); `tests/message_values_commerce_test.php` counts the new escaped warnings.

## What did the docs not prove?
- A successful answer's body; a question's status after answering.
