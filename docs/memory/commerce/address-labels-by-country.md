---
name: address-labels-by-country
description: City and state labels that follow the country (İlçe and İl for Turkey): pg_address_labels(), the render stamp in designer widgets, init_country() on classic pages, default words only
module: shipping
---

## What do the labels say?
- `pg_address_labels($country)` (pure, `includes/fn/shipping.php`): `District` / `Province` for Turkey (by code or name, `pg_ship_to_district_country_is_tr()`), `City` / `State / Province` elsewhere. In Turkish: İlçe / İl, Şehir / Eyalet / İl. Owner decision 2026-11-01 (`workflow/decisions-made-product-16.md`): a Turkish address is asked as İl (state box) and İlçe (city box) in every form; the city box is what the account card, e-invoice, e-waybill, imports and carriers read as the district.
- `pg_address_label_words()`: per slot (`city`, `state`) the Turkish and other words in the page's language and the default words (English and translated; `State` too).

## Which labels swap?
- Only a label holding a default word: `pg_address_label_key()` ignores asterisk, colon, case and spacing (none around a slash); `pg_address_label_swap()` keeps the spacing and asterisk around the words. A label a designer or a layout reworded ("İlçe/Semt", "Town") stays as it is. Saved trees, template pages and custom layouts are never rewritten.

## How do designer widgets do it?
- At render, `pg_address_labels_tree_stamp()` finds the label beside the city and state controls, writes the current country's words and stamps `data-pg-addr-label` (country control name), `-codes`, `-tr`, `-other`. `pg_ship_to_district_script()` swaps the label's first text node when that country control changes (and on load).
- Express order: `_eo_fit_district_field()` stamps shipping and billing (country: the recipient's stored one, else `_eo_default_country()`); the server-drawn billing rows and per-recipient form use `pg_address_labels_html()`.
- Shipping address, billing information, affiliate sign-up, account profile and address book: `pg_sa_address_labels($tree, $prefix, $country)` before the fill and the state split; each prints the script.
- JS starters still carry plain City / State / Province (A2 of `plans/address-fields-by-country.md`); the canvas is not stamped.

## How do classic pages do it?
- The six `get_*.php` address pages append `pg_address_labels_init_props($city_id)` to their `software.init_country()` call: `city_id`, `address_labels` (TR codes, words). `software.address_labels()` (`frontend.src.js`, hand-patched in `frontend.min.js`) matches `label[for]` of the city, state text box and pick list, and swaps on each country change. Custom and generated layouts (`includes/templates/*.php`) need a `<label for>` on the city; the default markup got one where it had a bare `<td>`.

## Where is it tested?
- `tests/address_labels_test.php`: labels by country, key and swap, tree stamp (own words kept), script, every place by source, init props, the delivery note's district (`erp_address_from_ship_to()`).
