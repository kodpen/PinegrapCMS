---
name: address-autocomplete
description: Address autocomplete: drivers (Google Places, Mapbox, Photon) behind address_suggest.php, account in connect_apps addr_<key>, Turkish box mapping, Ecommerce settings card
module: address_autocomplete
---

## What is built, and where?
- `includes/fn/address_autocomplete.php`: the layer (`pg_addr_*`); drivers in `includes/geo/autocomplete/<key>.php` with `pg_addr_<key>_suggest($text, $country, $lang, $app, $session)` and, when a suggestion has no address yet, `pg_addr_<key>_fill($id, $lang, $app, $session)` (Google only).
- Every driver answers one shape, `pg_addr_empty()`: street, street_2, city, district, state, state_code, postcode, country (ISO). Turkey: `district` is the İlçe (Google `administrative_area_level_2`, Mapbox `place`, Photon `county`).
- `pg_addr_boxes()` turns it into form boxes (`address_1`, `address_2`, `city`, `state`, `state_code`, `zip_code`, `country`) by `address-labels-by-country.md`: a Turkish address writes the İlçe into the city box. House number before the street only in `pg_addr_street_line()`'s list (US, GB, FR…).
- Layer, endpoint and card: PR #5087; form hook: the SF1b PR.

## How does the browser ask?
- `address_suggest.php` (root), POST `action=suggest` (`q`, `country` two-letter code, `lang`) → `{ok, suggestions:[{id,label}]}` (at most `PG_ADDR_SUGGESTIONS` = 5); `action=fill` (`id`, `lang`) → `{ok, boxes}`. Form token (403 JSON); rate bucket `addr_suggest` 60/min per `waf_ip_subject()` (429, `rate-address-suggest`); not a page-cache dirtying POST.
- Suggestions live in `$_SESSION['software']['address_suggest']` (provider, Google session token, id → address or null); a fill takes only an id this session was given (nobody spends Place Details calls on their own ids); a pick clears it, ending Google's billing session. No key or provider script reaches the browser, so the cookie notice needs nothing.
- A provider that fails or is off answers `ok:false` with an empty list and 200: the box keeps what was typed. `pg_curl_run()`, 5 s timeout, 512 KB cap.

## How do the forms get it?
- `pg_addr_suggest_script()` rides `pg_ship_to_district_script()` (once per page, '' while `pg_addr_ready()` is false), which every address form already prints: checkout, billing, the account's profile and address book, the classic shipping page, affiliate sign-up, the panel's verified form.
- It hooks every `<prefix>address_1` text box in a form and finds the other boxes by the same prefix (`address_2`, `city`, `zip_code`, `country`, `state`, else `state_id`). It sends the form's own `token` field, so the page holds no session data and stays cacheable.
- A pick sets the country first (change event only if it changed, so the labels and the state list follow), then the street, city and postcode, then the state (option matched by value = `state_code` or by name with accents and case ignored). An empty answer field leaves the box as typed; `address_2` is filled only while empty. Synthetic `input` events are ignored (`isTrusted`): a fill asks nothing.
- ARIA combobox: arrows, Enter, Escape.

## Where is the account kept?
- `connect_apps` row `addr_<key>` (no schema change, like SMS): key sealed as `client_secret`, never drawn back (empty box keeps it); Photon's host in `options.host` (https only, `PG_ADDR_PHOTON_HOST` when empty). One provider on at a time: saving one on switches the others off; `pg_addr_provider()` is the first on with a complete account.
- Settings › Ecommerce › Address Autocomplete (`pgset-address-autocomplete`): `pg_addr_settings_decide()` (pure, tested); `_check()` before anything in `commerce.save.php` is written, `_save()` after the label drivers. "Save and show suggestions" lists the Test Address's suggestions.

## How was it verified?
- `tests/address_autocomplete_test.php`: each provider's answer mapped from `tests/fixtures/address_autocomplete/`, the Turkish boxes, input clean-up, error answers, the card's decisions. No provider was called, no site run; the form script ran once in local Chromium with `fetch` mocked.
