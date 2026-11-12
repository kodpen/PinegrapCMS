---
name: legacy-to-designer-run
description: The run of the bulk move to the visual designer (LD2b, #6421): Convert under a dry run, the steps per style, cursor, lock, screen requests, job tick, kept reports, what a resume skips
module: legacy_to_designer_run
---

## How does a run start?
- Only under a dry run: the dry run card on `legacy_to_designer.php` carries a Convert form with the sets and styles it did not skip (POST, form token). `pg_ltd_run_start()` refuses a role above 1 and a second run while a cursor exists, writes the cursor, logs a line and sends the screen back to its progress card.
- A set moves into one new Legacy design with its own steps: `legacy-set-run.md` (LG5). The rest of this file is a style moving alone.

## What does it do per style?
- `pg_ltd_steps()`, in this order:
  - `regions`: the style's common regions through `pg_common_region_component()`.
  - `design`: `pg_classic_style_design_open()` (the frame split, `legacy-frame-split.md`), each converted page, its page regions taken in, through `pg_designer_save_page()` (a dry run first; a page somebody holds skipped, `pg_collab_may_edit_page('', id)`).
  - Then in `design`: the folder pages through `pg_classic_style_convert_folder_pages()`; the design-wide body classes folded onto the pages and cleared as the editor's Save does; then `pg_classic_style_mark_design($id, 'legacy', ...)` (the `legacy` key since LG3: `legacy-design-framework.md`).
  - `page_regions`: page regions still in a page's tree (a page that had its own, a folder page) get the blocks spliced in; skipped when some page region sits in a shared component.
  - `frame`: the frame's parts (`pg_ltd_frame_rows()`): their designer regions through `pg_designer_region_component()`, one widget row per region with `pg_ltd_widget_tree()`, `pg_ltd_widget_config()`, a unique `pg_ltd_widget_name()`, spliced into each part row at once.
- A refused or missing style is skipped with its reason; a style already a visual design skips `regions` and `design`. A step that skips its style marks `skip` and `pg_ltd_state_next()` moves on; before LG5 a failed `design` also skipped the next style's `regions`.

## Where is its state, who works it?
- Cursor `data/temp/legacy_to_designer.json` (`pg_ltd_state_*()`), written after every step through a temporary file. It holds the run id, the designer's id and name, the styles, the sets, the index and step, the counts and up to `PG_LTD_REPORT_LINES` (2000) report lines.
- `pg_ltd_run($budget)` under `GET_LOCK('pg_ltd', 0)`: the screen's POST `ajax=1`, `step=run` with the form token, session closed first, 8 s; the general job (`job.php`) 20 s per tick through `pg_ltd_job_tick()`, loading the module only when the cursor file exists. It works as the designer who started it, whose role is read each time; a role above 1 stops the run.
- Ended: the cursor is kept as `data/legacy_to_designer/<run id>.json` (outside `temp`, which the clean up tool empties), one log line; the screen lists the last five runs.

## What if a request dies inside a step?
- The step runs again: every converter returns the row it made before, a page with a tree keeps it, a frame with no region nodes left makes no widget. A lost cursor ends the run; starting again is safe for the same reason.

## Tests?
- `tests/legacy_to_designer_run_test.php`: the cursor, its steps and progress, report lines, body classes, placed ids, the wiring of the screen and the job. The sandbox runs of sets: `legacy-set-run.md`.
