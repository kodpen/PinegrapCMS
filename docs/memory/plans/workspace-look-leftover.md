---
name: workspace-look-leftover
description: Plan: the Workspace's old buttons (btn btn-) and old fields (formRow(), form-control) move to look A's recipes, file by file; Data's bulk Delete; what is left out
priority: 2
---

## Why is this a plan?
- The owner's line for the channel screen (2026-11-14, `workflow/decisions-made-workspace-26.md`): new parts use look A, the old parts are not redrawn in one piece, and what the touching steps leave is taken afterwards. panel-design-language named that rest DL5f.
- Architect (2026-11-24, agent channel message 12602): the rest is a plan of its own, because it is the size of the old-screens plan, not of a step; panel-design-language closes without it.
- It is a look plan: no feature, no schema, no right and no wording changes. Every button and field keeps what it does, its name, its keyboard use and its place.

## How much is left? (counted 2026-11-24 at `15124076`)
- Old buttons, by `pg_look_markup_counts()` of `tools/look_rules.php`: 569 in 51 Workspace files; 471 in 28 scripts (`workspace.js` 232, `workspace_flow.js` 56, `workspace_editor.js` 28, `workspace_mail.js` 25), 98 in 23 PHP files (`workspace_wtables.php` 26).
- `tools/check_look.php` already holds these counts in its baseline (`ui/look-check.md`); no tab strip is left.
- Old fields: 65 `formRow(` calls in 4 scripts and 161 lines with `form-control` in 21. No check counts them yet (WL1).

## Which recipe does an old button take?
- Copied from the Design examples screen (`design_examples.php`), section Buttons; the ranks as `ui/look-marketplace-ranks.md` reads them.
- `btn-solid`: `pg-button pg-button-fill` when it is the one main action of its view (a drawer's or dialog's Save, the writing box's Send); a second solid in the same view becomes `pg-button-quiet`.
- `btn-outline`: `pg-button pg-button-quiet`. `btn-text`, `btn-link` and `btn-subtle` with a label: `pg-button pg-button-ghost`.
- A button with only an icon: `pg-icon-button`, its name as `aria-label` and `title` through the script's text table.
- `theme-danger`: `pg-button-danger`. `btn-sm`: `pg-button-sm`. An icon inside loses its `me-*`; the button's gap spaces it.
- A card's or row's actions share one rank (quiet small); a Cancel beside a fill is ghost.

## Which recipe does an old field take?
- The kit's form parts (`formParts` in `workspace.js`, `workspace/workspace-drawer-form-parts.md`): `field()`, `group()`, `input()`, `textarea()`, `select()`, `switchRow()`, `help()`, `error()`.
- The pickers (people, customer, page, date) are not rebuilt; a field joined to a button (`input-group`) keeps its joined corners.

## What does a step do when no recipe fits?
- It builds nothing of its own: it asks the architect for a design example (`workflow/screen-described-in-plan.md`) and leaves that part as it is, named in its PR. Expected cases: a pressed toolbar toggle, the reaction chips, the message hover bar.

## What is left out?
- Status badges (`badge`, 150 lines in 16 scripts against 22 `pg-pill`), list rows (`list-group`), cards and menus: not counted here; `ui/look-open-work.md` names them.
- The conversation's messages and the side panels keep their drawing (`workspace/workspace-channel-screen.md`).
- Pages a person outside the panel opens (the guest page, an approval link, a meeting link, a calendar feed's page): a step moves them only where the panel's stylesheet with the recipes is loaded; otherwise it leaves them and says so.
- The three look gaps of `workspace/canvas-phone.md` (the compact row card with its groups, the 52 px tile, the second text line): dropped by the architect in the same message.

Steps: `workspace-look-leftover-steps.md`; the bulk Delete's description: `workspace-look-leftover-screens.md`.
