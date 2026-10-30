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
- Checkout and order screens (D2), carrier drivers, marketplace imports and the API field (D3): `plans/shipping-tr-district.md`.

## Where is it tested?
- `tests/tr_district_plate_test.php`: the plate fill (Turkish letters, unmatched names, filled rows kept), the Turkish countries rows, the step's source (label, helpers, empty-only update) and the probe.
