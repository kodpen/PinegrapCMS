---
name: wholesale-steps
description: Plan wholesale, steps WS1 to WS7: the offers' group condition, an account using another's prices, its picker on the prices screen, the minimum order, quantity prices, the API, close-out
---

## Which steps are there?
- WS1 (M) The offers' group condition, server only. No schema.
  - Existing files: `edit_offer_f.php` (the condition in `_pg_offer_condition_types()` and its form row), `includes/fn/ecommerce.php` (its test where offers are judged: `apply_offers_to_cart()`, `get_best_offer_id()`). The cart module is held by other open PRs: the step names the clash.
  - Tests, red first: a member, a non-member and a guest; a break for a group; an offer without the condition judged as before.
  - Features: J1, J2, J10.

- WS2 (M) Shared prices, server and data only. After the owner's yes on the set's schema.
  - A migration step adds one column to `erp_accounts`; the draft PR's body carries it for the architect.
  - Existing files: where the ERP reads an account's prices (`erp_account_price_terms()` in `includes/erp/price_lists.php`) and its save's check. `includes/fn/ecommerce.php` only if `pg_erp_member_prices()` itself must follow the pointer, and then last in that file's order (`plans/more-features-shared-files.md`).
  - Tests: an account buying at another's prices and discount; a second hop refused at the save; a pointed-at account's delete refused; the recorded rule against a campaign; retail in the feed, the structured data and the kept page copy; an account that uses nobody's prices priced as today.
  - Features: J4 (data), J5, J6, J7, J10.

- WS3 (S) after WS2: the picker. Existing files: the account's prices screen (`includes/erp/price_lists.php` and the screen that draws it). Pictures to the owner; not marked ready before his look. Features: J4.

- WS4 (S) The minimum order. Existing files: `submit_order.php` (one refusal before the pre-save hook; the express order and order preview include it), the cart widget's message, the contact group's edit screen (the field, kept in the one settings text). Held by the closed-blocks steps: the step names the clash. Tests, red first: one group, two groups (the highest), a guest, the limit itself, an order in another currency. Features: J8, J9.

- WS5 (S) after WS1: quantity prices on a product page. Existing files: `includes/fn/widgets_catalog.php` (the part), the designer's token list in `assets/js/style_designer.src.js` (held by other open PRs). Pictures to the owner. Features: J3.

- WS6 (S) after WS3: the API. The API schema's row for the account's pointer and its resource file; `php tools/check_api_schema.php`. Features: J11.

- WS7 (S) close-out: every J number checked against the code with its PR; `commerce/wholesale.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps run side by side?
- WS1, WS2 and WS4 touch different files and may run side by side, each after the open PRs on its files. WS3 and WS6 follow WS2; WS5 follows WS1.
