---
name: vendor-api-update
description: The Kodpen API's release check (GET /releases/latest, PR #3282) behind PG_VENDOR_API_V1: how an answer is read, what it offers, the pin the later steps read, flag and notification, screen texts
---

## Where is it asked?
- Once `PG_VENDOR_API_V1` is on (`vendor-api-client.md`), `pg_update_server_version($mode)` (`includes/fn/update.php`) calls `pg_vendor_release_check()`: `GET <base>/releases/latest?channel=<pg_update_channel()>&from=<VERSION>`, `from` only for a release number (`pg_vendor_release_path()`). Callers: the daily check (`software_update_check()`), the updater screen through it, the `check` step of `pg_panel_software_update()` (update and Repair). With `SOFTWARE_UPDATE_CHECK` off nothing is asked. While the switch is off api2 answers as before.

## How is an answer read?
- `pg_vendor_release_read()`: status, problem code, and the release in the vectors' flat shape (`release.artifacts.update` as `update`, `build` '' when absent, sha256 lower-cased). A 200 needs `application/json` and a release object: version a release number, channel stable or beta. A blocked release needs only its stepping stone (a release number); any other needs build (`[0-9A-Za-z._-]{0,64}`), manifest and signature addresses, a 64-hex manifest_sha256, key id `^[a-z0-9][a-z0-9._-]{0,15}$`, package url, size (int > 0), sha256. Less is no release, and the answer is unusable.
- Addresses are not checked here: the vectors refuse a foreign package host at the download step, not at the check.

## What does it offer?
- `pg_vendor_release_offer($found, $installed, $build, $channel, $mode)`: 403 `access_blocked` and 404 `release_not_found` are no_update; any other answer without a release is unusable; `blocked` (any reason) is blocked; another channel is refused. Update: newer is an update, else up_to_date; on beta the same version with another build only when the site records a build (#3204). Repair: older refused, equal repairs, newer updates.
- The installed build is '' until the build file exists, so a beta site is offered newer versions only.
- `pg_vendor_release_message()`: the stepping stone, another channel, "Service access is closed for this site." (the licence card's text too), the existing repair and no-update texts; a beta build is named beside its version. An unusable answer keeps the screens' own texts ("No access to the update server." with `pg_curl_tls_hint()`, "No response from the update server.").

## What does the check step keep?
- `$_SESSION['software']['update']`: mode, version, channel, build, package_url, size, sha256, manifest_sha256, sig_key_id, manifest_url, signature_url, package_file (`vendor_update_<16 hex>.zip` for `data/temp/`, one per check). Download, backup and replace read only this (`vendor-api-update-download.md`, #3632); replace or any refusal deletes the package and clears it. Repair also keeps `software.repair.version` for its log line.

## Flag and notification?
- `software_update_check()`: an update or a blocked release sets `software_update_available` and leaves one `software_update` notification; any other answer clears both; an unusable one takes the old error path (notification deleted, logged).
- `software_update.php`: blocked, refused and access-blocked answers go back to Settings with their reason as an error; nothing offered says "There is no update available.".

## Tests?
- `tests/vendor_release_test.php`: every `update_steps.json` case at the check step (three describe the site after the check, which keeps the baseline's values there), real-shaped bodies, refused shapes, problems, beta builds, Repair, texts.
