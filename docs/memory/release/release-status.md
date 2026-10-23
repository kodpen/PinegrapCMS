---
name: release-status
description: Last released version (2026.4.10), the open version (2026.11.0, first calendar-versioned number), its migration file and step labels, how a release branch, tag and GitHub release are made
---

## What is released?
- 2026.4.10: PR #501 (`release/2026.4.10`), merge commit `57cdd1f` (2026-10-16), tag `v2026.4.10`. Earlier: 2026.4.9 PR #238 `1c3703e` (2026-10-13), 2026.4.8 `348b8a2`, 2026.4.7 `8eb2716`, 2026.4.6 `a05c986`.
- A released version is closed: what its migration brings, its `versions.php` row and its changelog heading never change (a step body may be fixed for sites that have not run it: `released-vs-open-version.md`). The tag never moves: the integrity check and the server package are built from that tag's tree.
- A site that ran a beta of a version already has its number in `config.version` and will not rerun that version's steps, so post-beta fixes self-correct in code (2026.4.9: `pg_tr_render_rules_check()`).

## What is open?
- 2026.11.0 (opened 2026-10-16), the first number in the year.month.patch scheme (`version-numbering.md`), with its `versions.php` row and changelog section.
- Its file `includes/migrations/2026.11.0.php` holds `upgrade_to_2026_11_0()`; each schema step is a sub-step `upgrade_2026_11_0_<topic>()` called from there with its label; a new step adds a call, not a file (`how-to-add-migration.md`).
- A database that already recorded `2026.11.0` (dev, beta and demo sites that pulled `development` before a step was added) skips the steps added since: set `config.version` back to the last released version (today `2026.4.10`) and run the upgrade again (`rerun-upgrade-steps.md`).
- Step labels (`11.0.` prefix, area ranges) and finding a free one: `version-numbering.md`.
- The next version number is the owner's; ask. A version opens with its first schema step or changelog entry, always together with its `versions.php` row (the dashboard compares the changelog's newest heading with `config.version`).
- A pull that adds a `versions.php` row sends the panel to the upgrade screen once, also for a code-only version.

## How is a release built?
- Branch `release/<version>` = one "Release <version>" commit on top of `main` whose tree is `development`'s minus `CLAUDE.md`, `.claude/`, `docs/`, `tools/`, `tests/` (temporary index: `read-tree`, `rm -r --cached` those paths, `write-tree`, `commit-tree -p origin/main`; author Erdal Güral). The commit message is the short release notes, grouped by area.
- Pull request `Release <version>` to `main`, body = the GitHub release notes (`## Release v<version>`, schema note, sections with emoji headings, upgrade notes, compare link). Merge with a merge commit titled `Merge pull request #<n> from kodpen/release/<version>`, never squash; development's own history never reaches `main`.
- Tag `v<version>` on that merge commit and a GitHub release with the same body; publishing it pushes the Docker images to GHCR (platform/docker-image-publishing.md). A cloud session can push the release branch and merge through the GitHub API, but its git proxy drops tag pushes: the tag and the GitHub release are made from the owner's machine or the GitHub web page (creating the release there creates the tag).
- CI PHP steps skip on branches without `tools/`. After a release this file is updated.

## What never ships in public notes?
- Owner decision: hosting-platform features (pinegrap.site; `PG_HOSTED` hosted-site mode, `PG_DEMO` demo mode) never go into `changelog.txt`, README or GitHub release notes. Their files live in `dev/hosting/` (ignored). `pg_hosted()` / `pg_demo()` checks stay in the product; inert unless the constants are defined.
- Exception (owner request 2026-10-16): both READMEs link demo.pinegrap.site as a demo installation people can try. Only the link and a request not to enter real personal or payment data are named; whether the live demo resets or sends e-mail is unverified, so it is not claimed. Hosting features and the constants stay out.

Related: docs/memory/workflow/git-branches-and-shared-files.md
