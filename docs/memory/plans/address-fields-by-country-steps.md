---
name: address-fields-by-country-steps
description: Steps A1-A8 of plans/address-fields-by-country.md: site labels and district rule, editor starters, account state list, panel and ERP, place lists; claim as [plan:address-fields-by-country] A<n>
---

## Which steps are there?
- S under 400 product lines, M about 1,000. A1 first. A2 and A4 after A1, side by side (different files); A3 after A2 (both touch the starters). A5 after A1 and questions 1 and 2.

- A1 (M) Site forms: labels and the district rule in one PR, so the district box never goes before the city box says İlçe. `free`.
  - `includes/fn/shipping.php`: `pg_address_labels($country)` (pure); `pg_ship_to_district_shown()` = the carrier asks and the country is not Turkey; a Turkish recipient's district written from the city (`pg_ship_to_district_address_sql()`, `pg_ship_to_district_save()`); the client script swaps the two labels and the box.
  - Designer widgets stamp both label texts at render (a tree fit beside `pg_ship_to_district_tree_fit()`): express order (default tree, `_eo_render_shipping_section()`, `_eo_render_billing_address()`, `_eo_fit_district_field()`), shipping address and arrival, billing information, account profile and address book, affiliate sign-up.
  - Classic pages: `software.init_country()` swaps default label words (`frontend.src.js` and the hand-patched `frontend.min.js`, which terser cannot rebuild byte for byte); the six `get_*.php` pages and the five generated layouts of `address-fields-by-country-places.md`.
  - `includes/erp/waybills.php`: the order's ship-to district from `ship_tos.district` first.
  - Tests: `tests/tr_district_box_test.php` (the rule turns), the labels, the waybill pick. Memory: `commerce/tr-district-plate-code.md`; new `commerce/address-labels-by-country.md`.

- A2 (S) Editor starters and canvas. After A1.
  - `style_designer.src.js`: `_buildExpressOrderStarterTree()`, the recipient list block, `_buildStarterTree()` cases `account_profile`, `address_book`, `billing_information`, `affiliate_sign_up_form`, `shipping_address_and_arrival` carry A1's stamps; the canvas shows the labels of the store's default country. `build_js.php`, `node --check`. Design templates open these widgets as starters: no template edit, no version bump.

- A3 (S) State pick list in the account profile and address book widgets. After A2.
  - Both draw the state as a text box, their classic pages switch to the pick list: add the list control and `pg_sa_state_script()` (`widgets_account.php`, A2's starters); a saved tree without the list gets it by a fit.

- A4 (S) Panel and ERP labels. After A1.
  - `verified_shipping_address_form.php`, ERP account and waybill forms: İl/İlçe for Turkey (ERP card fields unchanged: card city = il, district = ilçe). Turkish default layout `1005.php` still says "City" / "State / Province".

- A5 (M) Turkish place list. After A1 and questions 1 and 2.
  - `includes/geo/places/TR.json` (`TR-01`…`TR-81` → district names, 973 with the 51 "Merkez"), found by `states.plate_code` or name; the İlçe box suggests "Kadıköy / İstanbul" and fills the İl, so field order does not matter; the server corrects a matching spelling; question 2 for the rest. Tests on the lookup and spelling.

- A6 (S) Lists as country packs. After A5 and question 3.
  - Loader and picker take any `includes/geo/places/<CC>.json`; ERP and panel forms use the picker; the pack format in a memory file.

- A7 (M) Downloaded lists. Only if question 3 says so.
  - Per-country GeoNames download into `data/geo/`, attribution, suggestions only.

- A8 (S) Close-out.
  - Lasting decisions to `commerce/` and `designer/`; the three plan files deleted.
