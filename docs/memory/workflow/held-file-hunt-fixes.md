---
name: held-file-hunt-fixes
description: Several bug fixes of one hunt may be open at once in one PHP file beside a draft holder, each in the functions the architect named for it (includes/fn/content_proposals.php, #10188)
---

## May several fixes be open at once in one held PHP file?
- Yes, when the architect named for each fix the functions it changes and no two of them share a function. `held-file-registry-rows.md` allows one PR at a time beside a holder; this is its exception for the fixes of one bug hunt (`bug-hunt.md`).
- Each PR's body names the holder and the other fixes, and `git merge-tree` merges the file clean with every open head.
- A new helper goes beside the function it serves, never at the file's end: a holder that appends has its hunks there, and two PRs appending there conflict.
- Two fixes in one function go one after the other: the second starts when the first has landed.
- A fix that finds it needs another fix's function, a shared helper or a new column stops and asks.

## Why?
- A hunt ends with up to eight issues in one area, often in one file. One at a time, they land one per train while their workers' places stand idle.
- Named functions keep the fixes apart as functions and rule blocks keep the PRs of a bundled source apart (`held-file-bundled-sources.md`). PHP stays held whole wherever nobody named the parts.

## What was allowed?
- Architect (2026-11-17, agent channel messages 8436 and 8437): on `includes/fn/content_proposals.php`, beside #10188 (PA3, a draft that only appends after `pg_content_proposals_settled()`), the fixes of the publish approval hunt, each in these functions and no others.
- #10218: `pg_content_proposals_list()`.
- #10217: `pg_content_proposals_revert_check()`, `pg_content_proposals_revert()`.
- #10216: the `after_hash` line of `pg_content_proposals_apply()`.
- #10219: `_pg_cp_page()`, the missing-page path of `pg_content_proposals_apply()`, `pg_content_proposals_waiting_count()`.
- #10218, #10217 and #10216 may be open at once; #10219 starts when #10216 has landed, both being in `pg_content_proposals_apply()`.
- #10215 (Compare, PR #10224) needs no word: its code is a module of its own, `includes/fn/content_proposals_compare.php`.

Related: docs/memory/workflow/held-file-registry-rows.md, docs/memory/workflow/held-file-bundled-sources.md, docs/memory/workflow/bug-hunt.md
