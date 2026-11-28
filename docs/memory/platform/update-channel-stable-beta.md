---
name: update-channel-stable-beta
description: Update channel (stable/beta): config key, pg_update_channel() for every caller, a beta site also follows stable, download_assistant stays stable, switching, version format.
---

## Where is the channel stored and asked?
- `config.software_update_channel` (`ENUM('stable','beta')`, 2026.4.4 step 4.21) -> `init.php` constant `SOFTWARE_UPDATE_CHANNEL`. Missing column or value outside the enum = `stable`.
- Ask the channel from ONE place in code (`includes/fn/update.php`): `pg_update_channel()` -> `'stable'`/`'beta'`. It goes out only as `channel=` to `/releases/latest` (`vendor-api-update.md`); download, backup and replace use the pinned release's package (`vendor-api-update-download.md`).
- A beta site follows both channels (owner, 2026-11-28): `pg_update_channels_followed()` gives stable and beta, `pg_vendor_release_check()` asks each, `pg_vendor_release_pick()` takes the higher version, the stable at equal version. So a beta site sends two requests a check; a stable site asks stable only.
- One channel's unusable answer fails the whole check: `pg_vendor_release_pick()` keeps it first and the channels after it are not asked. Deciding on the other answer alone could repair with an older beta build of the running version, or clear an update a check had offered.
- Three callers must ALL ask from the same place: the daily check (`software_update_check.php`), the updater screen (`software_update.php`) and the updater steps (`pg_panel_software_update()` in `includes/panel/software.php`, `api.php` action `software_update`: check / download / backup / replace). A site that asks the version on one channel and downloads the package from the other installs a package it never compared to its own version.
- The api2 helpers (`pg_update_request_key()`, `pg_update_package_file()`: one key and one fixed ZIP name per channel) went with the api2 path in P9 (#12212).

## What stays on stable?
- `download_assistant.php` stays on stable and never reads the channel: it is the manual rescue path for a site with broken software, and its answer is the release everyone runs. Repair (overwriting files) is NOT there since 2026.4.8; it lives in the panel at `software_repair.php`.

## How is the channel switched?
- Via Site Settings › Updates and maintenance, the Update channel row (the updater screen redirects back to settings when there is no update, so it cannot be changed from there). On change: reset `software_update_available` + `last_software_update_check_timestamp` and delete the pending `software_update` notification (the old answer came from the other channel).
- Beta -> stable does NOT download a version: schema cannot be rolled back, writing files back would leave the DB ahead. The site keeps its installed beta build until the stable release of its version comes as an update.
- That release is offered over a recorded beta build of its version on either channel; its replace step reruns the version's steps and deletes the build file (`pg_vendor_update_rerun()`, `pg_update_build_for_pin()`). The row's hint says so. Repair Software at the running version reruns its steps on either channel (`release/rerun-upgrade-steps.md`).
- `pg_vendor_release_offer()` compares versions (`vendor-api-update.md`); a version is a release number, `2026.4.5-beta1` is none. A beta build carries its release's number and a build id; which build runs is in `data/update_build.json` (`update-beta-builds.md`).

Related: docs/memory/platform/update-package-extraction.md
