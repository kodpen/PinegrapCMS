---
name: version-numbering
description: Version numbers from 2026.11.0 on are year.month.patch (calendar versioning): what each part means, which number an open version takes, why three parts and no leading zero, step labels.
---

## What does a version number look like?
- From 2026.11.0 on: `YYYY.M.PATCH`. `YYYY` is the year, `M` the month the version is planned to ship (1-12, no leading zero), `PATCH` is 0 for the first release of that month and 1, 2... for later ones in the same month: 2026.11.0, 2026.11.1, 2026.12.0, 2027.1.0.
- Older numbers (2017.2 ... 2026.4.10) used year.series.update; they stay in `includes/migrations/versions.php` as they are (a line is never removed or renamed).
- Owner decision (Erdal, 2026-10-16): calendar versioning starts with the version after 2026.4.10; the layout was left to us.

## Why three parts and no leading zero?
- The runner follows the order of `versions.php`; the schema gate (`pg_schema_upgrade_pending()`), Repair Software (`pg_update_repair_decision()`) and the dashboard's update notice use `version_compare()`, which reads the parts as numbers: 2026.11.0 > 2026.4.10. A string comparison puts 2026.11.0 first, so versions are never compared with `<`, `strcmp()` or `sort()` (`tests/update_test.php` checks the CalVer cases).
- Dots become underscores in the entry function: `upgrade_to_2026_11_0()` in `includes/migrations/2026.11.0.php`. `2026.1.0` and `2026.01.0` would compare equal but name two different functions, files and tags, so the month is written without a zero.
- PATCH is always written, `.0` included: `2026.11` and `2026.11.0` are different versions for `version_compare()` (`2026.11` is lower), and the number has to match the tag, the file and the function.
- `pg_code_version()` and `install_migration_file()` accept digits and dots only; a beta is a tag (`v2026.11.0-beta`), never a version number.

## Which number does the next version take?
- One open version at a time (docs/memory/release/released-vs-open-version.md). After a release, the next version takes the month it is planned to ship in with PATCH 0, or the next PATCH when that month already had a release.
- Once opened (`versions.php` row, changelog heading, possibly a migration file and dev databases that ran it) the number is kept even if the release moves to another month: `config.version` on those databases must stay a number from the list, or the upgrade screen closes.

## How are schema step labels written?
- The comment labels beside the calls in `upgrade_to_<version>()` take month.patch as their prefix and keep the earlier ranges: for 2026.11.0 general work `11.0.1`-`11.0.57`, then from `11.0.120`, ERP `11.0.58`-`11.0.69` and from `11.0.90`, API `11.0.70`-`11.0.79`, Workspace `11.0.80`-`11.0.89` and `11.0.110`-`11.0.119`. Labels are names only; the order of the calls is what runs.
- Each version repeats the ranges under its own prefix (2026.4.10: ERP `10.58`-, API `10.70`-, Workspace `10.80`-). 2026.4.4 still numbered its steps in sequence, so its `4.70`-`4.79` hold ERP steps, not API ones: an old label names a step only together with its version.

## Which label is free?
- Read the call list of `upgrade_to_<open version>()` as it is on `origin/development` (`git fetch origin development`, `git show origin/development:pinegrap/includes/migrations/2026.11.0.php`), right before pushing; never a list in notes or memory, which goes stale within days.
- Labels of open PRs are taken too: list the open PRs and open `…/pulls/<n>/files` of those that change the open version's migration file. A gap in the numbers is usually such a label, not a free one.
- Take the next unused number of your area's range. Two PRs that end up with one label: the later renumbers before merging (`core/concurrent-agents.md`).

## Which tags mark a release?
- Stable `v<version>` (`v2026.11.0`), beta `v<version>-beta`, on the merge commit of the release pull request; the integrity check reads them (`pg_integrity_release_tags()`). Release flow: docs/memory/release/release-status.md.
