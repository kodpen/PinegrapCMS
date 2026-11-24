---
name: bug-hunt-areas-workspace-look-regressions
description: Hunt 16, 2026-11-24: what the Workspace's move to the panel look (WL2-WL7) broke, what was fixed, what read clean
---

## What was the area?
- Hunt 16: the Workspace screens and scripts WL2 to WL7 (#11365, #11360, #11368, #11363, #11370, #11366) moved to look A's buttons (`plans/workspace-look-leftover-steps.md`). Did a handler, a busy or disabled state, a focus ring, an accessible name or the 390 px width break?
- Read from the six merge diffs, `backend.src.css` and the scripts; no browser run.

## What was fixed?
- A disabled `.pg-icon-button` or `.pg-seg-item` looked enabled: the recipe faded only `.pg-button`, while the old `.btn` faded every one. Icon buttons switched off while a request runs (an approval's guest Resend, a task link's Remove) and the locked Working days segments of Team settings showed no change. Now `.pg-icon-button:disabled`, `.disabled`, `.pg-seg-item:disabled` and a segment holding a disabled input sit at 0.5 opacity with no hover, as `.pg-button` (`ui/look-icon-buttons.md`).
- Command palette, "Only in #channel": a `.pg-seg-item` never wraps, so a long channel name ran past a 390 px screen. The name is cut (`text-truncate` span, whole in the title) and the control shrinks to the palette.
- Tables, Board view, "Group by": one `.pg-seg` of status column names ran off a phone; it is `pg-seg-wrap` now.

## What read clean?
- Every class and `data-` hook the scripts select on stayed in the markup; the one renamed selector (the E-mails views, `.pg-toolbar .pg-seg`) was renamed in the script too.
- Every converted icon-only button has `aria-label` or `title` (the flow arrows get theirs from `nameLabel()`); downloads carry `title`.
- `.pg-button` and `.pg-icon-button` keep a `:focus-visible` ring; no Workspace rule removes it. Menus still open through `pgUI` (Escape, arrows).
- `.active` is still read where it was kept: `.ws-side-tool.active` draws the Settings gear.

## What is left?
- The scheduled agents' state filter and a text file's Write / View segments show the chosen one by `is-active` only, no `aria-pressed`; it was so before WL2. In `workspace.js`, held by WL8 then.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/plans/workspace-look-leftover-steps.md, docs/memory/ui/look-icon-buttons.md
