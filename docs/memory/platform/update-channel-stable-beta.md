---
name: update-channel-stable-beta
description: Update channel (stable/beta): config key, pg_update_channel() for every caller and how the Kodpen API path sends it, the api2-only helpers, download_assistant stays stable, switching, version format.
---

## Where is the channel stored and asked?
- `config.software_update_channel` (`ENUM('stable','beta')`, 2026.4.4 step 4.21) -> `init.php` constant `SOFTWARE_UPDATE_CHANNEL`. Missing column or value outside the enum = `stable`.
- Ask the channel from ONE place in code (`includes/fn/update.php`): `pg_update_channel()` -> `'stable'`/`'beta'`. With `PG_VENDOR_API_V1` on (since #8653) it goes out only as `channel=` to `/releases/latest` (`vendor-api-update.md`); download, backup and replace use the pinned release's package (`vendor-api-update-download.md`).
- Three callers must ALL ask from the same place: the daily check (`software_update_check.php`), the updater screen (`software_update.php`) and the updater steps (`pg_panel_software_update()` in `includes/panel/software.php`, `api.php` action `software_update`: check / download / backup / replace). A site that asks the version on one channel and downloads the package from the other installs a package it never compared to its own version.
- api2 path (not reached since #8653; plan step P9 removes it): `pg_update_request_key()` -> `latest_version` / `latest_beta_version`; `pg_update_package_file()` -> `pinegrap_software_update.zip` / `pinegrap_software_update_beta.zip`. Its download step keeps that name in `$update_package`, backup and `replace` call the same function: if the two sides diverge, the extract step looks for a file that does not exist.

## What stays on stable?
- `download_assistant.php` stays on stable and never reads the channel: it is the manual rescue path for a site with broken software, and its answer is the release everyone runs. Repair (overwriting files) is NOT there since 2026.4.8; it lives in the panel at `software_repair.php`.

## How is the channel switched?
- Via Settings -> General (the updater screen redirects back to settings when there is no update, so it cannot be changed from there). On change: reset `software_update_available` + `last_software_update_check_timestamp` and delete the pending `software_update` notification (the old answer came from the other channel).
- Beta -> stable does NOT download a version: schema cannot be rolled back, writing files back would leave the DB ahead. The site stays on its installed version; the update appears once the stable release passes it.
- The api2 answer is compared by `pg_update_version_newer()` (`includes/fn/update.php`, `tests/update_test.php`): digits and dots only, numerically part by part with `version_compare()`, any number of parts. A beta package is numbered like a release; `2026.4.5-beta1` offers nothing. Before #6991 the daily check and the update steps compared three parts by hand and warned on a version of fewer parts (the `2026` release). With the Kodpen API a beta build carries its release's number and a build id; which build runs is in `data/update_build.json` (`update-beta-builds.md`).

Related: docs/memory/platform/update-package-extraction.md
