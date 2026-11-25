---
name: look-workspace-recipes
description: Which look A recipe an old Workspace button (btn btn-) or field (form-control, formRow) takes, what to do when none fits, what the Workspace's look move left out; plan workspace-look-leftover WL1-WL11
---

## How did the Workspace move to look A?
- Owner (2026-11-14, `workflow/decisions-made-workspace-26.md`): new channel parts use look A; old parts are not redrawn in one piece, the rest is taken afterwards.
- Architect (2026-11-24, agent channel message 12602): that rest is a plan of its own (workspace-look-leftover), a look move only: no feature, schema, right or wording changes; each button and field keeps what it does, its name, keyboard use and place.
- Steps: WL1 field count #11339, WL2 `workspace.js` #11365, WL3 Flows #11360, WL4 writing box #11368, WL5 mail #11363, WL6 small scripts #11370, WL7 PHP screens #11366, WL8 `formRow()` #11391, WL9 #11417, WL9b #11481, WL9c #11511 fields, WL10 Data's bulk Delete #11404 (`workspace/workflow-tables-data-bulk-delete.md`). Closed 2026-11-25; what stayed: `look-open-work.md`.

## Which recipe does an old button take?
- Copied from Design examples (`design_examples.php`), Buttons; ranks as `look-marketplace-ranks.md` reads them.
- `btn-solid`: `pg-button pg-button-fill` when it is the view's one main action (a drawer's or dialog's Save, Send); a second solid in the view is `pg-button-quiet`.
- `btn-outline`: `pg-button pg-button-quiet`. `btn-text`, `btn-link`, `btn-subtle` with a label: `pg-button pg-button-ghost`.
- Icon only: `pg-icon-button`, its name as `aria-label` and `title` from the script's text table.
- `theme-danger`: `pg-button-danger`. `btn-sm`: `pg-button-sm`. An icon inside loses its `me-*`; the button's gap spaces it.
- A card's or row's actions share one rank (quiet small); a Cancel beside a fill is ghost.

## Which recipe does an old field take?
- The kit's form parts (`formParts` in `workspace.js`, `workspace/workspace-drawer-form-parts.md`): `field()`, `group()`, `input()`, `textarea()`, `select()`, `switchRow()`, `help()`, `error()`; class by class `look-workspace-fields.md`.
- The pickers (people, customer, page, date) are not rebuilt; a field joined to a button (`input-group`) keeps its joined corners.

## What when no recipe fits?
- Nothing of its own is built: the part stays as it is, named in the PR, and a design example is asked of the architect (`workflow/screen-described-in-plan.md`). Met: pressed labelled toggles, chips, menu rows.

## What was left out of the move?
- Status badges (`badge`), list rows (`list-group`), cards and menus (the context menu's `.ws-ctx-item` rows): not counted by `tools/check_look.php`.
- The conversation's messages and the side panels keep their drawing (`workspace/workspace-channel-screen.md`).
- Pages a person outside the panel opens (the guest page, an approval link): moved only where the panel stylesheet with the recipes is loaded.
- Architect (2026-11-24, message 12602): the canvas phone gaps are dropped: the Data list's compact row card and its groups, the 52 px tile, a second text line under a table's name (`workspace/canvas-phone.md`).
