---
name: rerun-upgrade-steps
description: How to re-run an open version's upgrade steps on a dev DB (config.version rollback, config field, Yazılımı Onar card) and the package repair card.
---

## A feature I added does not show up / the Upgrade option is missing. Why?
- The dev DB version has already passed that version. Pull `config.version` back to the previous released version and re-run the upgrade (e.g. `UPDATE config SET version = '2026.4.4'`). Do NOT open a new number.
- Without DB access: Configuration screen field "Veritabanı Şema Sürümü" (`edit_config.php`, admin only). It accepts only numbers present in `versions.php`, because a number not in the list closes the upgrade screen completely.
- Safe because every step is guarded "exists -> skip"; applied steps are skipped (also the function's double-run test). An unguarded `ADD` / `CREATE` is forbidden.

## Can it be done from the panel? (2026.4.8)
- Settings -> İşler -> Yazılımı Onar (`software_repair.php`, admin only): the card "Veritabanı yükseltme adımlarını yeniden koş" writes the chosen version to `config.version` and goes to `install/index.php?automated_upgrade=true`.
- Choices come from `pg_upgrade_rerun_choices()` (`includes/fn/update.php`). It reads `versions.php` as text (`pg_upgrade_versions_from_source()`; the file is `INSTALL_OR_UPDATE`-gated and cannot be included), from the first version starting with `'2026'` up to the one before `VERSION`. Pre-2026 `legacy.php` steps write raw `ALTER ... ADD` and cannot be re-run.

## What does the "Yazılımı Onar" package card do?
- Downloads the channel's current package and overwrites all files (`api.php` `software_update` action, `repair: true`, role 0).
- It does not run if the server gives no version, or gives one lower than `VERSION` while `SOFTWARE_UPDATE_CHECK === false` (`pg_update_repair_decision()`, pure, tested).
- repair was removed from `download_assistant.php` (install / update / check remain).
