---
name: held-file-parked-draft
description: A draft that waits for an area's new shape holds nothing in that area: its lines leave the functions the redraw rewrites, so the redraw can start (#10188 PA3 beside DP18 on _pgTabsRender())
---

## What does a parked draft hold?
- A draft PR parked until another plan has redrawn an area holds nothing in that area. Its lines inside the functions the redraw rewrites come out, its own functions move to a place no open PR's hunk is in or next to, and the tests that pin those lines are parked with them, each named in the body as owed.
- It stays a draft until the redraw has landed, then draws its part on the new shape. What it keeps elsewhere in the file is held by function as before (`held-file-bundled-sources.md`).
- The step that redraws starts when the draft's push is in; its claim check is `git merge-tree` against the draft's new head.

## Why not let both stand?
- The two would wait on each other: the draft holds the function the redraw must rewrite, and the draft's look waits for the redraw.
- Two PRs in one function by lines is what the held-file rule exists to stop. And the draft's lines there are lines it writes twice anyway, once for the old shape and once for the new.

## What was the case?
- Architect (2026-11-17, agent channel message 8619, asked in 8614): PA3 (#10188, publish-approval, a draft) had two lines inside `_pgTabsRender()` of `style_designer.src.js`, one in the all-pages list and its own block right above that function's head, while DP18 of designer-panels redraws the page strip there.
- The owner gave the strip to designer-panels first (poll 127) and PA3's marks are redrawn on DP19's strip (poll 132).
- So #10188 takes those lines out and moves its block; its line in the save callback and its start-up line stay. DP18 starts when that push is in; the marks return on the strip and the Pages list of DP19.

Related: docs/memory/workflow/held-file-bundled-sources.md, docs/memory/workflow/claim-work.md, docs/memory/plans/designer-panels-additions.md
