---
name: db-q-converted-commerce
description: Cart, checkout and form files whose raw queries with values run through db_q() (fn/ecommerce, shopping_cart, submit_order, fn/forms): which PR, special cases, what stayed raw and why, the guard tests.
---

## Which commerce files are converted, and what stayed raw?
- `includes/fn/ecommerce.php` and `shopping_cart.php` (PR #2254): 100 and 36 calls with values (cart lines, ship-tos, zones, taxes, offers, inventory and reservation checks, recurring commissions, `duplicate_product()`), `(string)` where the SQL quoted, also where a value was written unescaped into quotes (now escaped), `(int)` for the bare `$discounted_by_offer` and the zone copy of `duplicate_product()`. Optional SQL pieces carry their values beside them: the restored price of `apply_offers_to_cart()` (`$values`), the affiliate code of the cart's offer code update (`$affiliate_code_values`, `array_merge()`). Raw stay the calls without values, the offer code lookup completed by `$sql_scope` and the products INSERT of `duplicate_product()`. `tests/db_q_ecommerce_cart_test.php` guards both and counts each literal value list against its `?`.
- `submit_order.php` and `includes/fn/forms.php` (PR #2272): 67 and 20 calls with values (order completion, gift cards, commissions, reservations, memberships, the 3-D Secure state rows, `log_activity()`, tax zones, login regions), `(string)` where the SQL quoted, `(int)` for the bare reservation quantity. The 3-D Secure rows passed `mysqli_real_escape_string()` values: the value goes in bare, so it is escaped once. Raw stay the billing contact update (`$sql_fax`, `$sql_tax`), the order update (`$sql_erp_on_account`, `$sql_credit_debit_card_values`), the visitor update that adds a bare `$total`, and in `forms.php` the clauses `$sql_where`, `$sql_collection`, `$sql_update`. `tests/db_q_submit_order_forms_test.php` guards both.

## What do their tests check beyond the content files' tests?
- Each literal value list of a `db_q()` call has as many elements as the '?' in the SQL string literals (a PHP ternary or `??` counts none), so a dropped or extra value fails the test.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/orders-table-write-traps.md
