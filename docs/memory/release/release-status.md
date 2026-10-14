---
name: release-status
description: Last released version (2026.4.9), the open version (2026.4.10), its migration file and step-label ranges, how a release branch is built
---

## What is released?
- 2026.4.9: GitHub release `v2026.4.9`, PR #238, tag `1c3703e` (merge commit, 2026-10-13). Earlier: 2026.4.8 `348b8a2`, 2026.4.7 `8eb2716`, 2026.4.6 `a05c986`.
- A released version is closed: never touch its migration (`2026.4.9.php` schema), its `versions.php` row or its changelog heading. The tag never moves: the integrity check and the server package are built from that tag's tree.
- 2026.4.9 shipped as beta first (2026-10-12; pinegrap.com runs it). A site that ran the beta already has `config.version` 2026.4.9 and will not rerun 9.x steps, so post-beta fixes were written to self-correct in code (e.g. `pg_tr_render_rules_check()`).

## What is open?
- 2026.4.10 (opened 2026-10-13): `versions.php` row, `changelog.txt` heading, `includes/migrations/2026.4.10.php` → `upgrade_to_2026_4_10()`, first step OAuth (10.70). New steps are added there as calls; sub-step labels `10.x`.
- Label ranges keep the earlier pattern with the new prefix: general work from .1, ERP .58–.69 and after .90, API .70–.79, Workspace .80–.89 and .110–.119. Check `includes/migrations/2026.4.10.php` for taken numbers; history table: `docs/sema-adimlari.md`.
- The next version number is the owner's; ask. A version opens with its first schema step or changelog entry.
- Rerun on dev: set `config.version` back to the last released version (today `2026.4.9`); steps are idempotent, applied ones are skipped.

## How is a release built?
- Branch `release/<version>` = one "Release <version>" commit on top of `main` whose tree is `development`'s minus `CLAUDE.md`, `.claude/`, `docs/`, `tools/`, `tests/` (built with a temporary index + `commit-tree`, the working copy untouched). Then PR and tag. The owner does this. After a release this file is updated.
- CI PHP steps skip on branches without `tools/`.

## What never ships in public notes?
- Owner decision: hosting-platform features (pinegrap.site; `PG_HOSTED` hosted-site mode, `PG_DEMO` demo mode) never go into `changelog.txt`, README or GitHub release notes. Their files live in `dev/hosting/` (ignored). `pg_hosted()` / `pg_demo()` checks stay in the product; inert unless the constants are defined.

Related: docs/memory/workflow/git-branches-and-shared-files.md
