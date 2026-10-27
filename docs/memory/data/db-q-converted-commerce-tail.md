---
name: db-q-converted-commerce-tail
description: Commerce panel tail screens, add to cart, private label, widgets 5, 21, 22 and the iyzico 3-D Secure row on db_q() (PR #2369): special cases, what stayed raw, guard test.
---

## Which files are converted, and how?
- PR #2369: `add_tax_zone.php` 3, `edit_referral_source.php` 3, `add_referral_source.php` 1, `edit_featured_and_new_items.php` 3, `edit_recurring_commission_profile.php` 2, `edit_commission.php` 2, `edit_products.php` 2, `catalog_detail.php` 2, `barcode_increase_inventory.php` 2, `barcode_decrease_inventory.php` 2, `add_currency.php` 2, `add_country.php` 2, `add_state.php` 1, `add_to_cart.php` 1, `private_label.php` 1, `includes/panel/commerce.php` 2, dashboard widgets 5 (1), 21 (2), 22 (2), `includes/payments/iyzico.php` 3.
- `(string)` where the SQL quoted, `(int)` where it wrote a bare number (the `user` column of the tax zone, referral source and state INSERTs, the new zone and country ids, the firewall widgets' day boundary).
- The barcode screens and the featured items screen build `$sql_where` with `?` and a `$where_values` array beside it; the featured UPDATE passes `array_merge()` of its SET values and those.
- Unescaped before, bound now: the session order id of `add_to_cart.php`, the item and group ids of the featured items WHERE (both came from the session or the database, so no reachable injection).
- iyzico's `iyzipay_3ds_state` row: the cent figures stay quoted (`(string) (int)`), so the INSERT, the conversation lookup and the claim send the text they sent; the claim still reads `mysqli_affected_rows()` right after `db_q()`.

## What stayed raw?
- The list screens (`view_*`: only a whitelisted ORDER BY), `view_gift_cards.php` (its `$where` holds no value, the count has none), `duplicate_product_group.php`, `delete_key_codes.php`, the config reads of `private_label.php` and `includes/settings/general.save.php`, widgets 16 and 20 (no values), widget 18 (completed by `$fm_where` / `$fm_optimize_condition` built elsewhere, integer LIMIT).
- The barcode lookup `db_value("… product_barcodes WHERE barcode = '" . escape(…))` is a helper call, not a raw query.

## What guards it?
- `tests/db_q_commerce_tail_test.php`: no escaped value (`escape`, `e`, `mysqli_real_escape_string`) in a `db_q()` SQL or a raw query, one value per `?` in each literal list, the built WHERE clauses hold only `?`, the bound text of a zone id, a barcode, the 3-D Secure cents and the day boundary.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/commerce/payment-iyzico-threeds-return.md
