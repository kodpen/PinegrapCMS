---
name: shipping-address-verified-box
description: Verified shipping addresses help box of the Shipping Address widget (#3177): has_verified_addresses, the verified_addresses section, the fill script by name
module: widgets_shipping_address
---

## What is it?
- The classic page's "Shipping Address Help" box. `_pg_sa_verified_addresses()` reads the rows with the classic query (all of them, not per visitor: company, then address line 1) and labels each "company or address 1, city"; get_shipping_address_and_arrival.php keeps its own inline copy (frozen file).
- Flag `has_verified_addresses` (`pg_sa_flags()`, 5th argument): a recipient and at least one row. The starter's bordered box (heading and two lines, designable) holds the section `verified_addresses`, which `pg_sa_verified_box_html()` draws: country, state and address pick lists with no `name` (nothing posts), a message, the picked address, a `type="button"` Use This Address. Labels go through `h()`.
- `pg_sa_verified_script()` (after the state script): country lists its states (all states, as the classic page), state its addresses; the summary is written with text nodes. The button fills `pg_sa_verified_fill_fields()` by name, the country first with a `change` event so `pg_sa_state_script()` switches the state control, then `state`.

## What could not be checked?
- No PHP default tree: the JS starter is the only default. Checked by a headless run on a static page (fill, state switch, escaping); not yet on a live checkout.
