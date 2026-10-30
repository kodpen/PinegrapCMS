---
name: country-names-in-addresses
description: The order view's address blocks and the address book name the stored country code in the site language; the country tokens keep the code
module: geo
---

## How does an address block name its country?
- `pg_geo_country_label_stored($stored, $names_by_code)` (`includes/fn/geo.php`): a two-letter code in the `countries` map is named by `pg_geo_country_label($name, $code)`, an unknown code by intl, falling back to the code; any other text (old rows, imports) is named as a stored name, falling back to itself; '' stays ''.
- `pg_geo_country_label_site($stored)` reads `SELECT code, name FROM countries` once per request (static cache) and calls it.

## Where is it used?
- The order view (`_render_system_widget_order_view()`, `widgets.php`): the address parts only, so `__billing_full_address`, `__billing_address_html`, `__shipping_full_address`, `__shipping_address_html` and the legacy `__shipping_address`. `__billing_country` / `__shipping_country` keep the stored code.
- The address book widget (`widgets_account.php`): the recipient's country, `__recipient_country` included ([[widget-entity-fields-address]]).
- Checkout's country lists: [[country-names-display]].

## Why?
- #3458 (item 5): the order summary and My Account printed "TR" at the end of a Turkish address, and the address book "Turkey". Fixed in #3574.
