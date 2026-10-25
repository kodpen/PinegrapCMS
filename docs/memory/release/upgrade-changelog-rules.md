---
name: upgrade-changelog-rules
description: changelog.txt format (English, only plan-driven major features as [NEW], one [FIX]/[SECURITY]/[SCHEME] line per version); the detail goes to docs/memory.
---

## What goes into pinegrap/changelog.txt?
- English only. It ships with the product and site owners read it in the panel (release notes) and on the install/upgrade screen.
- Only major features that came from a plan (`docs/memory/plans/`) get their own line: one short `[NEW] Added …` sentence each.
- Everything else (fixes, small improvements, speed work) is covered by the version's single catch-all lines. Never one line per fix.
- Owner rule (Erdal, 2026-10-14); it had been forgotten once, so `php tools/test.php changelog` now checks the tags and the one-line limits in CI.

## What does a version look like?
```
==========================================================
2026.x.2
[NEW] Added Workspace Notes and the ability to share notes in relevant channels.
[NEW] Added a site setting to toggle performance logging.
[FIX] Miscellaneous bug fixes and new features.
[SECURITY] Implemented various security fixes.
[SCHEME] A browser information field will be added to the visitor table.
==========================================================
2026.x.1
```
- A rule of `=` signs, the version alone on the next line, then one line per entry at the left margin; no topic headings, no wrapped text, no NOTE lines. Newest version on top.
- Order inside a version: the `[NEW]` lines, then `[FIX]`, `[SECURITY]`, `[SCHEME]`. A new `[NEW]` line goes after the existing ones.
- `[FIX]` and `[SECURITY]` use exactly the wording above. `[SCHEME]` is one short sentence naming what the database change touches; widen that sentence rather than adding a second line.
- Only these four tags. Versions released together may share a heading (`2026.1.21 / 2026.1.22`).

## When do I touch it?
- A PR does not edit it (2026-10-25: every schema PR conflicted on the one `[SCHEME]` line). Its body names what the file needs and the merging session writes it when it lands the PR (`workflow/merge-train.md`):
  - a plan-driven major feature: its `[NEW]` line for the open version;
  - any fix: the open version's `[FIX]` line (or `[SECURITY]` for a security fix), added once if missing;
  - a schema change: the area to name in the open version's `[SCHEME]` line.
- In the open version the file describes the final state: a fix to something added in the same version changes nothing here.

## Where do details go?
- In the matching `docs/memory/` topic file (what changed, why, root cause, rejected alternatives, PR number); there is no developer change log. See docs/memory/workflow/owner-rules.md "How are changes recorded?".
- Write the symptom there too: "IPv6 bans did not work" alone is not searchable; "dozens of duplicate ban rows for one address while it is never blocked" is.

## Which code reads changelog.txt?
- `pg_changelog_sections()` / `pg_changelog_preamble()` in `includes/fn/system_status.php` (panel release notes, dashboard "update pending" check) and the separate copy in `install/index.php` (install and upgrade screens). Both accept the rule above or below the version; keep them in step.
- The upgrade screen shows each version's `[SCHEME]` text next to its step, cut at 150 characters.
