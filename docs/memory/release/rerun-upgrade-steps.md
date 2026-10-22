---
name: rerun-upgrade-steps
description: How to re-run an open version's upgrade steps on a dev DB (config.version rollback by SQL or the Yazılımı Onar card, which versions it offers) and the package repair card.
---

## A feature I added does not show up / the Upgrade option is missing. Why?
- The dev DB version has already passed that version. Pull `config.version` back to the previous released version and re-run the upgrade (today `UPDATE config SET version = '2026.4.10'`). Do NOT open a new number.
- Only a number from `versions.php` goes into `config.version`: one the runner does not know closes the upgrade screen completely. Without DB access use the panel card below.
- Safe because every step is guarded "exists -> skip"; applied steps are skipped (also the function's double-run test). An unguarded `ADD` / `CREATE` is forbidden.

## Can it be done from the panel (2026.4.8)?
- Settings -> İşler -> Yazılımı Onar (`software_repair.php`, admin only): the card "Veritabanı yükseltme adımlarını yeniden koş" writes the chosen version to `config.version` and goes to `install/index.php?automated_upgrade=true`. It refuses while the `install/` folder is missing (Repair Software puts it back).
- Choices come from `pg_upgrade_rerun_choices()` (`includes/fn/update.php`). It reads `versions.php` as text (`pg_upgrade_versions_from_source()`; the file is `INSTALL_OR_UPDATE`-gated and cannot be included), from the first version starting with `'2026'` up to the one before `VERSION`. Pre-2026 `legacy.php` steps write raw `ALTER ... ADD` and cannot be re-run.

## What does the "Yazılımı Onar" package card do?
- Downloads the channel's current package and overwrites all files (`api.php` `software_update` action, `repair: true`, role 0).
- It does not run if the server gives no version, or gives one lower than `VERSION` while `SOFTWARE_UPDATE_CHECK === false` (`pg_update_repair_decision()`, pure, tested).
- repair was removed from `download_assistant.php` (install / update / check remain).
