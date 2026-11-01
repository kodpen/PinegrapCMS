---
name: license-check
description: The panel's licence check (license_check(), includes/fn/output.php): when it asks, the Kodpen API branch behind PG_VENDOR_API_V1 (PR #4381), the card, the key field
---

## Where is the licence checked?
- `license_check($properties)` in `includes/fn/output.php`. The account menu calls it twice on every panel page: `'bar'` for the licence card and `'badge'` for the avatar dot (`account-menu.md`). It does nothing without a key (`SUBSCRIPTION_KEY`, from `config.subscription_key`).
- The result lives in the session (`$_SESSION['software']['settings']['license']`: `status`, `error_code`, `countdown`, `expiration_date_formatted`, `last_check`, `diag_*`; from the Kodpen API also `type`, `sites_used`, `sites_total`, `source` 'v1'). Site Settings unsets the whole array when the normalised key or the Hostname changes (`general.save.php`), so the next page asks at once.

## When does it ask the server?
- `pg_license_check_due($force, $status, $last_check, $now)` decides: a forced check (`?check_license_again=1`, Manager or above) or a session that has not asked yet asks; an answer (any non-empty `status`, a refused key too) is trusted for 12 hours; a failed attempt (`status` `''`: no answer, not JSON, a proxy's HTML page) is retried after 15 minutes.
- `last_check` is stamped for every attempt, answered or not.
- Only the first call of a request may ask (a static flag in `license_check()`), so the card and the dot never cost two requests, a forced one included.

## Why the 15-minute wait?
- An unreachable server cost every panel page two timed-out requests (#815, PR #913).

## What does the card's Check License button open?
- Drawn for every error state, only for Manager or above (`role <= 2`, who may force a check): `pg_license_check_again_url($_GET)`, the same screen with its query kept (#996, PR #1129).

## How does it ask the Kodpen API?
- Only with `PG_VENDOR_API_V1` on (`vendor-api-client.md`); off, the api2 call runs as before. On, a state without `source` 'v1' (left by api2) is asked again at once.
- The key is normalised (`pg_license_key_normalize()`: dashes and blanks out, upper case); outside 16 of 0-9 A-Z without I, L, O, U (`pg_license_key_valid()`) nothing is sent and the state is `error`/`no_match`. Else `POST /licenses/verify` with `pg_license_v1_options()` (bearer key, body `version` when a release number and `channel`, `site_required`).
- `pg_license_map_v1()` follows `tests/fixtures/vendor_api/licence_mapping.json`: schema-valid 200 active/expired `success` + `P_end_d`, suspended `error`/`suspended`; 401 `invalid_license` `no_match`; 403 `seats_full`/`access_blocked` that code; all else status '' (15-minute retry). Type and site rights only from a 200.
- Ten `invalid_license` answers an hour close the licence check for the whole address, valid keys too.

## What does the card show?
- Card row `pg_license_card_title()`: "Pinegrap <type> · <state>" (`pg_license_type_name()`: Demo/Premium/Enterprise, else the escaped code); no type keeps "Pinegrap Premium™"; no separate type line (#4928). `pg_license_card_details()`: "Site rights: 3 / 5". Expiry: `pg_license_expiry_text()`, `get_absolute_time()` plain text (#4929).

## What does the key field do?
- Mask `pgLicenseKeyMask()` (`backend.src.js`): the alphabet, any case shown upper. With the switch on, `pg_license_site_warning()` warns under it: `local` (localhost, `.test`, `.local`, private IPv4) still spends a site right; `none` (no valid name) fails every check. The update channel card names what checks send (no erasure promised).

## What guards it?
- `tests/output_test.php`: `test_output_license_check_due_*`, `test_output_license_check_again_url_keeps_the_query`, `test_output_license_check_button_only_for_managers`, `test_output_license_card_*`, `test_output_license_check_v1_branch_behind_the_switch`.
- `tests/license_v1_test.php`: every vector case and its retry, the request against the vectors' block, the key alphabet, card texts, the warning against `site_host.json`.
