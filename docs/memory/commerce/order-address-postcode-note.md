---
name: order-address-postcode-note
description: The order screen's warning-only note when a Turkish postcode belongs to another province than the address's state (pg_shipping_postcode_province())
module: shipping
---

## What does the note do?
- `pg_shipping_postcode_province($country, $state, $postcode)` (`includes/fn/shipping.php`) compares a Turkish postcode's first two digits with the plate number of the province in the state field. When they differ it returns the province the postcode points to, else ''.
- `view_order.php` shows it as a yellow note (`pg_shipping_postcode_province_text()`) under the ship-to card's address line, and under the billing zip code. Warning only: nothing is blocked or changed.

## Where do the plate numbers come from?
- `pg_sales_map_tr_regions()` (`TR-01` Adana … `TR-81` Düzce; the digits are the plate number) and `pg_sales_map_match_region('TR', $state)`, which also knows Afyon, İçel, Maraş, Urfa. `includes/sales_map.php` is loaded with `require_once`, not edited.

## What is not checked?
- A country other than `TR`, a postcode that is not five digits or whose prefix is no plate (00, 82-99), and a state the map does not know: older n11 imports kept the district there (since #2004 the province).

## What is still open?
- The postcode check still reads the plate from the province name; `states.plate_code` and `ship_tos.district` (#3569, step 11.0.134) are described in `commerce/tr-district-plate-code.md`. No district data set exists (licence decision).
