---
name: how-to-add-migration
description: Checklist for a DB schema change: open-version sub-step or new number, file template, install_note(), heavy tables, get_tables() for a new table, changelog, step label, rules, finishing checks.
---

## Where does a schema change go?
- Into the open (unreleased) version as a sub-step; `release-status.md` names it. If you cannot read it, ask; do not guess. A new number opens only after a release and is the owner's.
- The open version's file exists (`includes/migrations/2026.11.0.php`): add `upgrade_2026_11_0_<topic>()` and one call to it in `upgrade_to_2026_11_0()`, label beside it (`upgrade_2026_11_0_page_schedule();  // 11.0.17`). Never recreate the file.
- A new number (after a release): append it to `includes/migrations/versions.php`; if it touches the DB, write `includes/migrations/<version>.php` (copy a neighbour: header block, `if (!defined('INSTALL_OR_UPDATE')) { exit; }`, `function upgrade_to_2026_12_0()` calling its sub-steps). A code-only version gets no file until its first schema step.
- A dev DB that already ran the open version skips the new step: `rerun-upgrade-steps.md`.

## What is the checklist?
Each item was skipped once and broke something; go through them in order.
1. Idempotent helpers only (`migration-step-helpers.md`): never `db("ALTER TABLE ...")`, never `SHOW COLUMNS ... LIKE` (`_` is a wildcard: the wrong column counts as present).
2. End the step with `install_note()`: one plain sentence for the operator, shown under the version on the upgrade screen.
3. A step that rewrites a big table: `'<version>' => array('<table>')` in `install_heavy_tables()` (`runner.php`) for the preflight warning; a long step: `migration-long-steps.md`.
4. A NEW table goes into `get_tables()` in `install/index.php`, the software's tables a reinstall or restore drops when the loaded backup lacks them. Skipped: a reinstall into the same database keeps the old table with its rows and structure, the idempotent step says "already exists", and the site silently runs on the old schema. (`check_and_repair_database_tables()`, `includes/fn/system_status.php`, reads `information_schema`; nothing to add there.)
5. `pinegrap/changelog.txt`: the open version keeps ONE `[SCHEME]` sentence; widen it to name your area, never a second line (`upgrade-changelog-rules.md`).
6. The why, root cause, symptom and trade-off go into the matching memory file.
7. The label comment beside the call: taken labels are read from there, there is no other step table; finding a free one: `version-numbering.md`.

## Which other rules apply to a step?
- Data statements re-runnable; an enum-narrowing `MODIFY` reads the shape first (`migration-step-helpers.md`). No VARCHAR on `config` (`data/config-table-no-varchar.md`). Syntax that runs on MySQL 5.7 and MariaDB 10.2 (`database-server-compatibility.md`).
- A function the host may disable (`set_time_limit`, `ignore_user_abort`, `disk_free_space`, `getmypid`, `php_uname`, `error_log`, `mail`, `fsockopen`, `exec`/`proc_*`/`posix_*`) only behind `function_exists()`, and `install_log()` instead of `error_log()`: in PHP 8 a disabled function is undefined, `@` does not silence it and the upgrade screen goes white (`platform/disable-functions-rule.md`).

## How do I finish?
- `php -l` the file: a `ParseError` stops that version only and leaves its schema half done.
- Run it from the upgrade screen, no hand-written SQL, twice on a dev DB: the second run counts every statement as already there ("{n} were already in place", Turkish "zaten yerindeydi").
- The PR goes through the seven items and says which applied; one that could not be done is named with the reason, never skipped silently.
