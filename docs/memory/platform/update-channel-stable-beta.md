---
name: update-channel-stable-beta
description: Update channel (stable/beta): config key, the three channel functions all callers must use, download_assistant stays stable, switching, version format.
---

## Where is the channel stored and asked?
- `config.software_update_channel` (`ENUM('stable','beta')`, migration 4.21) -> `init.php` constant `SOFTWARE_UPDATE_CHANNEL`. Missing column or value outside the enum = `stable`.
- Ask the channel from ONE place in code (functions.php): `pg_update_channel()` -> `'stable'`/`'beta'`; `pg_update_request_key()` -> `latest_version` / `latest_beta_version`; `pg_update_package_file()` -> `pinegrap_software_update.zip` / `pinegrap_software_update_beta.zip`.
- Three callers must ALL ask from the same place: the daily check (`software_update_check.php`), the updater screen (`software_update.php`) and the updater steps (`api.php` -> check / download / replace). A site that asks the version on one channel and downloads the package from the other installs a package it never compared to its own version.
- `api.php` takes the package name once in the download step and puts it in `$update_package`; the `replace` step calls the same function: if the two sides diverge, the extract step looks for a file that does not exist.

## What stays on stable?
- `download_assistant.php` stays on stable and never reads the channel: it is the manual rescue path for a site with broken software, and its answer is the release everyone runs. Repair (overwriting files) is NOT there since 2026.4.8; it lives in the panel at `software_repair.php`.

## How is the channel switched?
- Via Settings -> General (the updater screen redirects back to settings when there is no update, so it cannot be changed from there). On change: reset `software_update_available` + `last_software_update_check_timestamp` and delete the pending `software_update` notification (the old answer came from the other channel).
- Beta -> stable does NOT download a version: schema cannot be rolled back, writing files back would leave the DB ahead. The site stays on its installed version; the update appears once the stable release passes it.
- Version comparison is three-part numeric (`explode('.')`), so beta packages must also be numbered `major.minor.maintenance`; `2026.4.5-beta1` cannot be compared.

Related: docs/memory/platform/update-package-extraction.md
