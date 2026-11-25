---
name: designer-parts-registry
description: Plan (owner 2026-11-25): each designer part described in a file of its own, so a step adding a part edits no shared list; step PR1
priority: 1
---

## What is asked?
- Owner (2026-11-25): steps that add a designer part keep meeting in the same lines of shared files and clash in the merge trains; a part is to be added without touching a list other steps also edit.

## What is there today?
- A part (`designer/designer-parts-on-demand.md`) is written in four shared places: its bundle in `pg_js_bundles()` and its name in `pg_designer_parts()` (both `includes/fn/output.php`), its name and condition in the one-line `'parts'` list of `includes/designer_screen.php`, and a line in the memory file's "Which parts are there?". Nearly every designer step edits the same line of the screen and the end of the same arrays.
- The screen's list is kept by hand and drifts: `focus` (IF2, IF3), `overrides` (CO3) and `sketchread` are in `pg_designer_parts()` but not handed to the editor, so `_sdPartLoad()` refuses them and their buttons show the part-failed toast. `mirror` is left out on purpose until its switch.
- The core's part kit (`_sdPartKit()` in `style_designer.src.js`) gains a key at its end in many steps, the same clash.

## What is built?
- `includes/designer_parts/<name>.php`, one file per part, with the header block and gate constant, returning `array('sources' => array(...), 'when' => '<condition>')`. The sources are the part's files in load order; the bundle is `assets/js/style_designer_<name>.min.js`.
- `pg_designer_parts()` and the part bundles of `pg_js_bundles()` are read from that folder (names sorted, kept for the request). No part is listed by hand in `output.php` any more.
- `when` is one of a short closed list the screen evaluates: `always`, `designer` (full designer access), `ai`, `comments`, `phone`, `publishcheck`, `clicks`, `off`. `includes/designer_screen.php` hands every part whose condition holds (`pg_designer_parts_for($conditions)`), in place of the hand-kept list; the condition rules now in that line move there unchanged.
- `focus`, `overrides` and `sketchread` get their condition from their memory files (whoever may use the button); `mirror` is `off`.
- The kit's keys are kept in alphabetical order, one per line with a trailing comma, so two steps adding keys rarely touch the same lines; `tests/designer_parts_test.php` checks the order.
- The memory list "Which parts are there?" is replaced by one line: each part's file names its memory file in a comment.

## What must it keep?
- The editor gets the same `sdDesign.parts` as today for every part already handed (same names, addresses, order not relied on), plus the three now handed. Built bundles keep their names; `tools/build_js.php` finds them as before through `pg_js_bundles()`.
- A part file with an unknown `when` or a missing source fails `tests/designer_parts_test.php`; so does a part in `pg_designer_parts()` with no file.
- No table or column.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- PR1 (M) The folder, the reader, the screen's conditions, the three missing parts handed, the kit order, the memory file's list; this file deleted and the rule moved to `designer/designer-parts-on-demand.md`. Lands in a train of its own; open steps that add a part the old way are turned into a part file by the merging session at their merge.

## What does every step keep?
- Claim as `[plan:designer-parts-registry] PR1 — designer parts in files of their own`.
