---
name: place-packs
description: Country place packs (includes/geo/places/<CC>.json): the file format, how a pack is found and matched, which forms suggest from it, how to add a country
module: places
---

## What is a pack?
- One file per country, `includes/geo/places/<CC>.json` (two capital letters, ISO 3166-1). Any such file is a pack: `pg_places_countries()` globs the folder; a country without one keeps the city as free text (owner decision 2026-11-01, question 3: country packs, no downloaded lists).
- Format: `{"country": "<CC>", "source": "<where the names come from>", "licence": "<licence>", "places": {"<CC>-<sub>": {"name": "<province>", "districts": ["<name>", …]}}}`. Province keys are ISO 3166-2 codes; `districts` are what the address's city box holds in that country (İlçe for Turkey). One province per line keeps diffs readable. A pack must be MIT-compatible and say its source.
- `pg_places_parse()` drops a province without `name` or `districts` and empty names; the folder is closed to the web (`includes/.htaccess`).

## How is an address matched to a pack?
- Country: `pg_places_country($code, $name)` reads a countries row the sales map's way (`pg_sales_map_canonical_country()`), so 'TR', 'Türkiye' and a code like 'TÜRKİYE' all find `TR`.
- Province: `pg_place_province($cc, $pack, $state, $plate)`: Turkey by `states.plate_code`, else by name with the older spellings (`pg_place_tr_province()`); any other country by the province's name or the code's part after `<CC>-` ("CA").
- District, spelling, note: `pg_place_check()` → `pg_place_district()`; saves and the order screen as in `tr-place-list.md`, for every pack.

## Which forms suggest from it?
- Storefront forms: every `<prefix>city` box with a `<prefix>country` control (script via `pg_ship_to_district_script()`).
- A form whose names differ marks its district box (on the box or an element around it) with `pg_places_input_attrs($country, $state)`: `data-pg-place-country` names the country control, or is a pack's code (then on only while the province box names one of its provinces); `data-pg-place-state` names the province box. A marked form gets no automatic hook.
  - ERP account card (`district`, İl in `city`, `country_code`) and delivery note (`ship_to_district`, `ship_to_city`, `ship_to_country`): both print `pg_places_suggest_script()`; a pick writes the province's own name into the text box.
  - Panel verified address: `city` marked `TR` / `state_id` (no country control): suggestions only once a Turkish province is picked.
- The browser config is `{label, packs: {<CC>: {codes, list}}}`: `codes` the pack's code plus the shop's countries codes read as that country; `list` `pg_places_client_list()`.

## How is a country added?
- Add the file and a test for its counts (as `tests/tr_place_list_test.php` does). If its city box is not the district, the pack still lists what that box holds.

## What is left out on purpose?
- Filling `states` for more countries: once a country has states, `get_valid_zones_for_destination()` and `get_tax_rate_for_address()` match a zone by state, so zones listing only the country stop matching.
- Neighbourhood (mahalle) lists, a downloaded list (GeoNames), a data step for older rows (they cannot be told apart). Address autocomplete is its own layer (`address-autocomplete.md`).
