---
name: look-widths-and-switch
description: Plan (owner 2026-11-13, #9070): lists at least 1700 px with the details panel added beside, add and edit screens up to 1700 px, the switch with its thumb again; steps LW1-LW7
---

## What is asked?
- Owner (2026-11-12 and 13, #9070), on the real screens: the redesigned pages are narrowed too far, and a list's details panel squeezes the table. The Pages list is right: about 1700 px with the panel closed, the whole window with it open; "diğer sayfalarda container standart genişliği olmuş nedense".
- The settings switch has become "değişik bir checkbox türü": "ben bunu onaylamadım".
- Records: `workflow/decisions-made-panel-7.md` (widths), `workflow/decisions-made-panel-8.md` (switch).

## What is there today?
- `ui/look-page-widths.md`: tokens `--pg-measure-edit` 1120px, `--pg-measure-form` 760px, `--pg-measure-status` 336px, `--pg-measure-list-min` 1280px in the block "Look A: page widths" of `backend.src.css`; body classes `pg-measure-edit` and `pg-measure-list`; `tests/look_page_widths_test.php` pins them.
- A list's `#content` is `fit-content` and centred. The Pages list's columns need about 1670 px, so it sits near 1700 and grows to the window when the panel opens; a list of few columns sits at the 1280 px minimum and its table gives up the panel's width while the window's sides stay empty.
- The settings switch (`.pg-set-card .switch`, `ui/look-settings-screens.md`): the track is the look's, but a checked thumb takes `--pg-on-accent`, a dark ink meant for the old light blue track; on the accent gradient in the dark theme it cannot be seen.

## Which rules does every step keep?
- Width and colour through the tokens and the look's recipes, no rule per screen; `tests/look_page_widths_test.php` moves with the values.
- Nothing changes on a screen the look has not reached, and nothing in the File Manager, the designer or the Workspace.
- Each PR shows real panel pictures, light and dark, at 1920, 1366 and a phone.

## Which steps?
- LW1 (S) Lists as the Pages list. Done #9185 (`ui/look-page-widths.md`).
- LW2 (M) Add and edit screens up to 1700 px: `--pg-measure-edit` 1700px, status column 376, gap 24, the form column the rest. A field is as wide as its content asks, never the column's width only because there is room. The section rail group is centred as today.
  - First a proposal of how the cards use a form column of up to 1300 px (two cards abreast, or fields side by side) on Edit Product, Edit Page and the longest Site Settings card: the architect shows it to the owner before the rule reaches every screen. Proposed on #9269 (the measure is in it; A, B or C with pictures), asked on #9271; the step goes on with the answer.
- LW3 (S) The switch. Done #9197 (`ui/look-switch.md`).
- LW4 (S, no product code) The screens at another width. Done #9211: `ui/look-screen-widths.md` names each screen and its step.
- LW5 (M) Lists and views off the table driver take `pg-measure-list` (list: `ui/look-screen-widths.md`); a table that is not a list table keeps its own frame. Pictures of each. Done #9230.
- LW6 (M) Settings, tool and import pages (the list there) take `pg-measure-edit`, a form in a single card at the form column; `clean_up` drops its own `.container`. After LW2, at its numbers.
- LW7 (S) The ERP reports and the Search Console and audit screens (the list there) take `pg-measure-report`. Done #9249.
- Close-out: `ui/look-page-widths.md`, `ui/look-settings-screens.md` and the sheet's list and edit pages (`plans/panel-design-language-sheet.md`) are brought to the new numbers; this plan is deleted.

## In which order?
- LW1 and LW3 first, side by side; then LW2's proposal; LW4 any time; LW5 and LW7 any time, LW6 after LW2. Claim as `[plan:look-widths-and-switch] <step> — <topic>`.
