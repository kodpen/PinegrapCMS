---
name: update-check-errors
description: How the software update check reports a failed answer (HTTP status, cURL, invalid), the checks-off state, its single Site Log line, and the one "update available" bell row.
---

## What does a failed update check say, and where?
- `pg_update_check_error_text($details)` (`includes/fn/update.php`, pure, tested in `tests/update_test.php`) gives the one sentence for `curl_missing`, `curl_error`, `http_error`, `invalid_response` and `checks_off`. The Site Log and the settings screen show the same sentence; each starts with a capital letter and none says "daily".
- `software_update_check()` writes the one Site Log line of a failed check; `software_update.php` only shows it (`mark_error('update', …)` on the Updates and maintenance pane). Before #6013 the screen logged the same event a second time, and the function called a manual check "daily".

## How is an error page told apart from a bad answer?
- `pg_curl_run()` returns `status` and `ok` (2xx). Both the api2 path of `software_update_check()` and `pg_update_server_version()` (the update/Repair `check` step) read `ok`: a non-2xx answer is `http_error` with `http_status`, the message names "HTTP 500". Before, a 500 page was decoded as JSON and reported as an invalid response with no status anywhere.
- The `check` step answers `The update server answered with HTTP {var:1}.`

## What happens with SOFTWARE_UPDATE_CHECK false?
- `software_update_check()` returns the `checks_off` error before any request, on both the api2 and the Kodpen API path; the manual "Check for Updates" screen says checks are off in data/config.php instead of "There is no update available." Repair Software refuses the same way (`pg_update_repair_decision()`).
- Before, the api2 path still posted the site's details to kodpen.com and then showed "no update".
- The update steps' `check` (`pg_panel_software_update()`, `includes/panel/software.php`) asks nothing either: on the Kodpen API path (on since #P8PR) it answers "There is no update available.", on the api2 path the `checks_off` sentence; before #6991 api2 posted to kodpen.com and then warned on an undefined `$software_update_available`.

## How many "update available" bell rows does a check leave?
- At most one. A version answer on the api2 path deletes the `software_update` notification before it decides (`software_update_check.php`), as the Kodpen API path does (`vendor-api-update.md`): an answer offering an update writes one row, an answer offering nothing leaves none.
- Before #6121 every check, and every opening of `software_update.php`, added another row while an update waited, and an answer offering nothing left the old one. Test: `tests/software_update_check_notification_test.php`.
