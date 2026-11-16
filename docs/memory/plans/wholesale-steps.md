---
name: wholesale-steps
description: Plan wholesale, steps WS1 to WS8: the offers' group condition, price lists and the resolver, the Price lists screen, the minimum order, quantity prices, a company's buyers, the API, close-out
---

## Which steps are there?
- WS1 (M) The offers' group condition, server only.
  - Existing files: `edit_offer_f.php` (the condition in `_pg_offer_condition_types()` and its form row), `includes/fn/ecommerce.php` (its test where offers are judged: `apply_offers_to_cart()`, `get_best_offer_id()`). `includes/fn/ecommerce.php` is held by other open PRs: the step names the clash.
  - Tests, red first: a member, a non-member and a guest; a break for a group; an offer without the condition judged as before.
  - Features: J1, J2.

- WS2 (M) Price lists and the resolver, server and data only. After the owner's yes on tables.
  - A migration step adds the list and its rows (two tables), listed in `get_tables()`; the draft PR's body carries the columns for the architect.
  - Existing files: `includes/fn/ecommerce.php` (the resolver asked where `pg_erp_member_prices()` is, above the ERP's gate), `includes/settings/features.php` and `features.save.php` (the switch).
  - New: `includes/fn/wholesale.php` (lists read and written, the resolver, the personal mark of a page), `tests/wholesale_test.php`.
  - Tests: the lowest of account and list; the recorded rule against a campaign, never stacked; retail in the feed, the structured data and the kept page copy; the switch off pricing as today, with and without the ERP.
  - Features: J4 (data), J5, J6, J7, J14.

- WS3 (M) after WS2: the Price lists screen. New: the root screen and its CSV. Existing files: `includes/fn/output.php` (one menu link). Pictures to the owner; not marked ready before his look. Features: J4.

- WS4 (S) after WS2: the minimum order. Existing files: `submit_order.php` (one refusal before the pre-save hook; the express order and order preview include it), the cart widget's message. Held by the closed-blocks steps: the step names the clash. Features: J8.

- WS5 (S) after WS1: quantity prices on a product page. Existing files: `includes/fn/widgets_catalog.php` (the part), the designer's token list in `assets/js/style_designer.src.js` (held by other open PRs). Pictures to the owner. Features: J3.

- WS6 (M) after WS2 and the owner's answer on approval: a company's buyers.
  - A migration step adds the buyers' table; columns to the architect in the draft PR.
  - Existing files: `includes/fn/ecommerce.php` (`pg_erp_member_account()` also finds the account by a buyer's row), `submit_order.php` (the account on every buyer's order), `includes/fn/widgets_account.php` (the company's part), the ERP account screen (a Buyers card), `includes/fn/contacts.php` and `includes/fn/privacy.php`.
  - New: `includes/erp/account_buyers.php`, its test. Pictures to the owner.
  - Features: J9 to J13, J16.

- WS7 (S) after WS3: the API. The API schema's rows and their resource file; `php tools/check_api_schema.php`. Features: J15.

- WS8 (S) close-out: every J number checked against the code with its PR; `commerce/wholesale.md` and `erp/erp-account-buyers.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps run side by side?
- WS1 and WS2 touch `includes/fn/ecommerce.php`: one after the other. WS3, WS4 and WS5 may run side by side; WS6 after them.
