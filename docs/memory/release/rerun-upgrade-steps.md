---
name: rerun-upgrade-steps
description: How to re-run an open version's upgrade steps on a dev DB (config.version rollback by SQL, the Repair Software card, from a browser tab or a script) and what the package repair card does.
---

## A feature I added does not show up / the Upgrade option is missing. Why?
- The dev DB version has already passed that version. Pull `config.version` back to the previous released version and re-run the upgrade (today `UPDATE config SET version = '2026.4.10'`). Do NOT open a new number.
- Steps added to the version that is already open never run by themselves, because `config.version` already equals it: after a pull that brings a schema step, the screens that expect it fail until the re-run.
- Only a number from `versions.php` goes into `config.version`: one the runner does not know closes the upgrade screen completely. Without DB access use the panel card below.
- Safe because every step is guarded "exists -> skip"; applied steps are skipped (also the function's double-run test). An unguarded `ADD` / `CREATE` is forbidden.

## Can it be done from the panel (2026.4.8)?
- Settings › Jobs › Repair Software (`software_repair.php`, admin only): the card "Run the database upgrade steps again" writes the chosen version to `config.version` and goes to `install/index.php?automated_upgrade=true`. It refuses while the `install/` folder is missing (Repair Software puts it back).
- Both cards send the browser to `install/index.php?automated_upgrade=true` with a relative `Location`, so the upgrade screen keeps the scheme the visitor used; built from `URL_SCHEME` (the site setting, `http://` on dev behind cloudflared) the redirect left the HTTPS origin and a script following it was refused as mixed content.
- Choices come from `pg_upgrade_rerun_choices()` (`includes/fn/update.php`). It reads `versions.php` as text (`pg_upgrade_versions_from_source()`; the file is `INSTALL_OR_UPDATE`-gated and cannot be included), from the first version starting with `'2026'` up to the one before `VERSION`. Pre-2026 `legacy.php` steps write raw `ALTER ... ADD` and cannot be re-run.

## How is the card run from a signed-in browser tab or a script?
- In the tab: set the version select of `#rerun_form` to the version before the open one and call `form.submit()`. The upgrade screen then runs by itself in about two seconds and reports how many statements ran and how many were already in place. Check `SELECT version FROM config` and the new column afterwards.
- A script posts `action=rerun_upgrades`, `version` and the page's token to the literal address `software_repair.php`: the form's `action` property returns its hidden input named `action`, not the address.

## What does the Repair Software package card do?
- Downloads the channel's current package and overwrites all files (`api.php` `software_update` action, `repair: true`, role 0).
- It does not run if the server gives no version, or gives one lower than `VERSION` while `SOFTWARE_UPDATE_CHECK === false` (`pg_update_repair_decision()`, pure, tested).
- repair was removed from `download_assistant.php` (install / update / check remain).
