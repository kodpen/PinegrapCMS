---
name: look-open-work
description: Look A work still open after the look plans closed (2026-11-24/25): the Workspace's old buttons and fields left, phone widths, old buttons in cards, the editor's white page in dark, save bars
---

## What is left of the Workspace's look?
- Plan workspace-look-leftover closed on 2026-11-25 (`look-workspace-recipes.md`). Left by `tools/check_look.php`: 21 old buttons in 12 files, 65 old fields in 22 files.
- Pages without the panel stylesheet: `workspace_guest.php` (4 buttons, 1 field) with `assets/js/workspace_guest.js` (3), and `workspace_approval.php` (2 buttons, 3 fields). They need the recipes loaded first.
- `includes/workspace/notes.php`: the quick note's Save (`btn-solid`), held by #11351 during WL7; free now, a fill.
- Pressed toggles and chips with no recipe: My Tasks' and the timeline's Agents filter and the agent feedback marks (`look-workspace-main-script.md`); mail's First answer overdue (`mail_list.php`) and Plain text (`mail_pane.php`); the palette's kind chips, Repeat's weekdays (`workspace_recurrence.js`), the status choices (`workspace_status.js`), a template's task chips (`workspace_templates.js`).
- Menu rows: the context menu's `.ws-ctx-item` (`workspace.js`, `workspace_editor.js`).
- Fields: `form-text` help lines (15 in `workspace.js`, the WL9 scripts'), the writing box (`div.ws-rich form-control`), toolbar searches, Site Settings cards' rows and the colour field (`look-workspace-fields-scripts.md`, `look-workspace-fields-screens.md`).
- Badges, list rows, cards and menus were never in the plan.

## What was never drawn on the sheet?
- Phone widths and the Workspace (`look-sheet-pages.md`); the Workspace moved by recipe (`look-workspace-recipes.md`). The dashboard is not open work: it keeps its own drawn look (#8938).

## What did the architect note and leave for a later shared step?
- Architect (2026-11-17, looks of S3 #10314 and S4 #10316): old buttons inside a card, seen and left by the group steps. Example: Add Filter on Edit Form List View (`edit_form_list_view.php`, `button btn btn-solid theme-primary`). Not cut into a step; `tools/check_look.php` counts them under "old buttons".
- The rich text editor's white page in dark (CKEditor's editing area). Not cut into a step. What is dark already: `dark-theme-panel-css.md`.

## What did the shared steps leave?
- The bars in `includes/forms/*.php` draw the framed column by hand, not through `pg_save_bar()` (`save-bar-helper.md`).
- Old button order kept in `edit_comment.php`, `edit_erp_invoice.php` and Edit Product Group (`product_group_form.php`) (`save-bar-helper.md`).
- Older Summary cards not on `.pg-kv` (`pg_design_file_form_summary_card()`, `pg_order_view_row()`, `look-kv-list.md`).
- The kept screens' left parts: `look-kept-screens.md`.

## What counts did the plans close at?
- `tools/check_look.php` on 2026-11-25 (development after WL10): 476 font sizes off the scale, 314 weights, 564 colours outside the tokens, 1,079 old buttons, 4 old tab strips, 65 old Workspace fields. They only fall (`look-check.md`).
