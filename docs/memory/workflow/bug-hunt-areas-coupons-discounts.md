---
name: bug-hunt-areas-coupons-discounts
description: The bug hunt of coupons and discounts (area 31, 2026-11-24): offer dates, usage limits under two checkouts, a gift of a deleted product; what it fixed, what was read clean
---

## Which area was hunted?
- Area 31, coupons and discounts: offers and their codes, key codes, `apply_offers_to_cart()`, `validate_offer()`, `pg_offer_usage_counts()`, `get_best_offer_id()` in `includes/fn/ecommerce.php`, and the checkout's submit (`submit_order.php`).
- Looked at: start and end dates, the usage limit per code and per customer with two orders at once, a code that stacks where it must not, a discount larger than the basket, a removed product's discount, amounts in minor units.
- Left alone: `import_key_codes.php`, held by the CSV hunt (#11411).

## What did the hunt's PR fix?
- Written when the PR is done.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-5.md
