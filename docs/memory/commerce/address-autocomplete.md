---
name: address-autocomplete
description: Address autocomplete: drivers (Google Places, Mapbox, Photon) behind address_suggest.php, account in connect_apps addr_<key>, Turkish box mapping, Ecommerce settings card
module: address_autocomplete
---

## What is built, and where?
- `includes/fn/address_autocomplete.php`: the layer (`pg_addr_*`); drivers in `includes/geo/autocomplete/<key>.php` with `pg_addr_<key>_suggest($text, $country, $lang, $app, $session)` and, when a suggestion has no address yet, `pg_addr_<key>_fill($id, $lang, $app, $session)` (Google only: Mapbox v6 forward and Photon answer the whole address at once).
- Every driver answers one shape, `pg_addr_empty()`: street, street_2, city, district, state, state_code, postcode, country (ISO). Turkey: `district` is the İlçe (Google `administrative_area_level_2`, Mapbox `place`, Photon `county`).
- `pg_addr_boxes()` turns it into form boxes (`address_1`, `address_2`, `city`, `state`, `state_code`, `zip_code`, `country`) by `address-labels-by-country.md`: a Turkish address writes the İlçe into the city box. House number before the street only in `pg_addr_street_line()`'s list (US, GB, FR…).
- PR #5087 (SF1a). The form hooks (checkout's shipping and billing, the account address book, the panel's verified address form, the state pick list matched by `state_code` / name) are SF1b, after #5078.

## How does the browser ask?
- `address_suggest.php` (root), POST `action=suggest` (`q`, `country` two-letter code, `lang`) → `{ok, suggestions:[{id,label}]}` (at most `PG_ADDR_SUGGESTIONS` = 5); `action=fill` (`id`, `lang`) → `{ok, boxes}`. Form token compared with `hash_equals` (403 JSON), own rate bucket `addr_suggest` 60/min per `waf_ip_subject()` (429, logged `rate-address-suggest`), POST excluded from page-cache dirtying.
- The suggestions live in `$_SESSION['software']['address_suggest']` (provider, Google session token, id → address or null). A fill takes only an id this session was given, so nobody spends the site's Place Details calls on ids of their own; a pick clears the store and so ends the Google billing session.
- A provider that fails or is off answers `ok:false` with an empty list and 200: the box keeps what was typed. Short timeouts (5 s, connect 3 s), 512 KB cap, through `pg_curl_run()`.

## Where is the account kept?
- `connect_apps` row `addr_<key>` (no schema change, as `platform/sms-provider-layer.md`): key sealed as `client_secret`, never drawn back (empty box keeps it); Photon's host in `options.host` (https only, `PG_ADDR_PHOTON_HOST` when empty). One provider on at a time: saving one on switches the others off; `pg_addr_provider()` is the first on with a complete account.
- Settings › Ecommerce › Address Autocomplete (`pgset-address-autocomplete`): `pg_addr_settings_fields()`, `pg_addr_settings_decide()` (pure, tested), `_check()` in `commerce.save.php` before anything is written, `_save()` after the label drivers. "Save and show suggestions" runs one suggest with the Test Address and lists the labels as a notice.

## Why no browser SDK?
- The plan's rule: the key never reaches the browser and no third-party script loads, so the cookie notice (`platform/cookie-consent-new-cookies.md`) needs nothing. Photon's public instance is for light use; a busy store runs its own.

## How was it verified?
- `tests/address_autocomplete_test.php`: each provider's answer mapped from `tests/fixtures/address_autocomplete/`, the Turkish boxes, input clean-up, error answers, the card's decisions. No provider was called; the endpoint and the card were not run on a site.
