---
name: legacy-one-door
description: One door for a classic style (LE3): "Open in the Page Design Studio" lands on the move screen ticked, dry run in three groups, Convert, "Open the design"
module: legacy_to_designer, legacy_to_designer_plan, legacy_to_designer_run
---

## How does a classic style reach the designer?
- All Page Styles' magic button "Open in the Page Design Studio", the move screen's own row button and the notice on Edit Style (`edit_custom_style.php`, roles 0-1) all link to `legacy_to_designer.php?style=<id>#ltd-dry-run`.
- `pg_ltd_door_choice($sets, $id)` (pure) ticks the set the style moves with, else the style alone; a refused style is ticked so its dry run says why it is skipped; one the report does not list ticks nothing. The screen draws that dry run on a GET, writes nothing, and the fragment scrolls to it.
- Why: two doors gave two results; the open that wrote nothing before Save (`edit_system_style.php?classic=1`) converted no region. Its handling stays (`pg_classic_style_design_open()` is the run's own function), but no button links to it.

## How is the dry run drawn?
- Each set or style: its name, then three group labels (`pg-group-label` with a count) over list rows (`pg-list-row`), from `pg_ltd_plan_groups()` (pure) and `pg_ltd_plan_groups_html()`.
- "Becomes editable": a row a kind (Page content, Common regions, Designer regions, Menus and widgets) with its count; five names, the rest behind "Show all N". Page content counts pages given a tree or whose page regions move in.
- "Stays code, drawn as on the site": a row a kind with its count, reason and names: Dynamic, System, Mobile Switch and PDF regions of the frame and the pages (`pg_ltd_stay_targets()`), designer regions holding PHP (Custom PHP elements), pages with a custom layout. A row a region made home-1's set eleven System region rows in the sandbox.
- "Skipped": held pages, refused styles, a set with nothing left to move.
- The old line-by-line list stays folded under "What is written, line by line".
- When a common region converts, one help-size line above the foot: from now on they are edited in the Page Design Studio, on classic pages too (`classic-regions-in-designer.md`).

## What are the buttons?
- Foot: "Convert" filled, "Cancel" quiet (back to the screen with nothing ticked).
- A run that ends sends the screen to `?ended=1#ltd-ended`: a card "The move has ended" with "Open the design" filled (`edit_system_style.php?id=<design>`; with several designs one button each, the first filled) and "Report" quiet, which jumps to the first of Earlier moves, opened. `pg_ltd_run_designs($run)` (pure) gives each set's design, then each style moved alone; the screen keeps those that are visual designs now.

## What did the sandbox show (#11227)?
- home-1 from Edit Style arrives with its set ticked: 148 pages, 2 common and 113 designer regions become editable; 11 System regions and 83 custom layout pages stay code. auto-dialog's set converted: "Open the design" opened the new design; the measure went from 0 / 0 to 0 / 2 fully editable, the System region left on both (LE5).

## Tests?
- `tests/legacy_editable_door_test.php`: stay targets, groups of a style and a set, rows, the door's choice, the run's designs, the wiring of the screen, the style list and Edit Style.

Related: docs/memory/designer/legacy-to-designer-report.md, docs/memory/designer/classic-style-to-design.md, docs/memory/plans/legacy-editable-screens.md
