---
name: tr-district-plate-code
description: ship_tos.district and states.plate_code (step 11.0.134, decision #3569): the district as free text, the plate fill by province name, the readiness probe, what reads them so far
module: shipping
---

## What do the two columns hold?
- `ship_tos.district` VARCHAR(50) '': the recipient's district (ilçe) as typed, free text, optional. Before it, Turkish carriers and the n11 import read the district from `city` and the province from `state`; that stays the fallback, so older orders keep working.
- `states.plate_code` VARCHAR(4) '': a Turkish province's licence plate number ('34'). Only the states of Turkish countries rows carry one.
- No district list and no district validation: a data set and its licence would be a separate decision (#3569 (b)).

## How is the plate code filled?
- Step 11.0.134 (`upgrade_2026_11_0_tr_district_plate()`) adds both columns with `install_add_column()`, then fills the plate codes once.
- Countries rows that stand for Turkey: `pg_state_plate_country_ids()`, the sales map's reading of a row (`pg_sales_map_canonical_country()`: code `TR`, else the name, else a code like `TÜRKİYE` read as a name).
- `pg_state_plate_codes($rows)` gives id => plate for rows whose `plate_code` is empty and whose name is a province; `pg_state_plate_code($name)` goes through `pg_sales_map_match_region('TR', …)`, so Turkish letters, case, spacing and the older spellings (Afyon, Hakkari, İçel, Maraş) match. An unmatched name stays empty.
- The UPDATE carries `AND (plate_code = '')`: a code set by hand survives, a rerun writes nothing. If `includes/fn/shipping.php` is older than the step, the fill is skipped with a note and the next run does it.
- `ship_tos` is in the 2026.11.0 heavy-table list (`install_heavy_tables()`): the ALTER copies it where it cannot be added in place.

## Who reads or writes the district so far?
- `pg_ship_to_district_ready()` (`includes/fn/shipping.php`) probes the column by exact name; code that reads or writes it asks first (files land before the upgrade runs).
- A subscription renewal copies it with the rest of the address (`pg_subscription_renewal_order_write()`).
- Carrier drivers and marketplace imports (#3654): `tr-district-carriers.md`; the API field (#3917): `shipment-api-fields.md`.

## Where is the district box (#3641) and what is stored?
- Express order widget (default tree after City, JS starter twins, the server's per-recipient form), the Shipping Address and Arrival widget and the classic `get_shipping_address_and_arrival.php`. A layout designed before the box gets it after City's column (`pg_ship_to_district_tree_fit()`); while the column is missing the box is dropped.
- Shown (`pg_ship_to_district_shown()`, `pg_ship_to_district_script()`) only for an address abroad whose carrier asks (`data-pg-district-asks`; driver hook `pg_label_<key>_needs_district()`: Aras, Kargonomi). A Turkish address never gets it: its city box is asked as İlçe (owner decision 2026-11-01, `address-labels-by-country.md`).
- Stored by `pg_ship_to_district_value()`: a Turkish recipient's city box, else the typed box while the carrier asks, else ''. Saved by `express_order.php` (`pg_ship_to_district_address_sql()`) and `shipping_address_and_arrival.php` (`pg_ship_to_district_save()`), both passing the posted city; nothing while the column is missing. The address book has no district.
- Read: `view_order.php` before the city; the delivery note through `erp_address_from_ship_to()` (the recipient's district first, else the city box of a Turkish address).

## Where is it tested?
- `tests/tr_district_box_test.php`: the show and store rules (Turkey never, abroad when the carrier asks), the tree fit and mark, the twins and the save paths by source.
- `tests/tr_district_plate_test.php`: the plate fill (Turkish letters, unmatched names, filled rows kept), the Turkish countries rows, the step's source (label, helpers, empty-only update) and the probe.
