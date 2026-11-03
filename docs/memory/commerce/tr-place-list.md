---
name: tr-place-list
description: The Turkish place list (includes/geo/places/TR.json, 81 provinces, 973 districts): lookup, the city box suggester, the spelling written at save, the order screen's note for an unlisted district
module: places
---

## Where is the list and where does it come from?
- `includes/geo/places/TR.json`: `places` → `TR-01`…`TR-81` → `name` (as `pg_sales_map_tr_regions()` names it) and `districts` (973, 51 "Merkez" in the non-metropolitan provinces). Compiled by us from the official public list, MIT (owner decision 2026-11-01, `workflow/decisions-made-product-17.md`). A changed district list is an edit of this file; `tests/tr_place_list_test.php` checks the counts.
- `includes/fn/places.php`: `pg_places_pack($cc)` reads it once per request, `pg_places_parse()` is pure. Turkey is one country pack among any `<CC>.json` (`place-packs.md`).

## How is a district looked up?
- `pg_place_key()` folds like the sales map (Turkish letters, case, spaces, punctuation). `pg_place_tr_province($state, $plate)` (through `pg_place_province()`): by `states.plate_code` first, else by name with the older spellings (Afyon, İçel).
- `pg_place_district($pack, $province, $district)` (pure): inside a known province only its districts; the province's own name counts as listed where it has a "Merkez" (kept as typed). With no province any province's district counts; a name of several provinces (Gölbaşı) keeps no province.

## What happens at save?
- `pg_place_liveform_fix($liveform, $prefix)` writes the listed spelling into a Turkish city box ("kadikoy" → "Kadıköy") before the save: `express_order.php` (each recipient and billing), `billing_information.php`, `shipping_address_and_arrival.php`. An unlisted district is saved as typed; nothing is refused (owner decision, question 2). The address book and account profile keep what is posted.

## What does the order screen show?
- `view_order.php`: `pg_place_district_note()` under a recipient's and the billing address when the Turkish district is not listed (yellow, beside the postcode note); with a known province it names it.

## How does the browser suggest?
- `pg_places_suggest_script()` rides `pg_ship_to_district_script()` (ERP forms print it themselves, `place-packs.md`). The list is inlined (`pg_places_client_list()`: per plate the name, the shop's states row code and name, districts joined by `|`), no endpoint. It hooks every `<prefix>city` text box with a `<prefix>country` control while that country is a Turkish code; suggestions "Kadıköy / İstanbul" (prefix matches first, the chosen İl's first, "Merkez" only of the chosen İl), at most 8, ARIA combobox. A pick writes the district and selects the İl (option by states code or name), so the box order does not matter. Checked once in local Chromium.
