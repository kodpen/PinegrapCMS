---
name: designer-cleanup-window
description: The designer's "Clean up" window as built (#11668): what opens it, the four lists and what each counts as unused, "Show", the states, the endpoint and the part
module: design_cleanup_list
---

## What opens it?
- The command palette's "Clean up" (`act_cleanup`, hint "Design"), shown by `_sdCleanupReady()`: the part `cleanup` is handed (full designers), not a component alone, a saved design, the person may edit. No bar button, no menu row, no key.
- The Publish check's fold "Rules no page of the design uses" ends with a quiet "Clean up" (`K._sdCleanupOpen('publishcheck')`): the window opens over the Publish sheet (a second top-layer dialog), "Style rules" open; closing it gives the sheet back.

## Where does it live?
- Part `cleanup` (`includes/designer_parts/cleanup.php`, when `designer`): `assets/js/pg_design_cleanup.js` (pure: `PgDesignCleanup.lists`, `firstOpen`, `tickable`, `tickState`, `ticked`, `size`, `view`), then `assets/js/style_designer_cleanup.js` (the window). The core holds `_sdCleanupReady()`, `_sdCleanupOpen(from)`, `_sdCleanupHost()` (dirty, save, `_sdEmitNode()`, the canvas's stylesheets) and the palette row; CSS `.sd-cu-*` in `assets/css/style_designer.css`.
- Server: `designer/cleanup_list` (`style_id`; full designers only, read only) answers `pg_cleanup_design_lists()` (`includes/fn/design_cleanup_list.php`) with each file's address added.

## What does each list count as unused?
- Style rules: the finder (`design-cleanup-finder.md`) over the design's own sheets (`pg_cleanup_own_sheets()`: inline CSS rows and the handwritten Styles, never a linked or framework row). Known: Bootstrap 5.3.8's classes.
- Classes in use: every page's tree and code (all designs), every style's tree, code and head, shared components, system widget starters, regions (dynamic ones as scripts), product, event, campaign and ad texts where the column exists, `pg_fx`, the design's scripts.
- A linked script of the design that is not a file of the site may set any class: `script_unread`, nothing listed, a note names it. Each blocked or unreadable sheet gets one note at the list's end.
- Files: design files (`files.design = 1`, not in the Recycle Bin) whose name no text above, no style's assets, no setting, no page's meta tags or structured data (`pg_cleanup_add_page_row()`) and no text file of the site (`.css`, `.js`, `.svg`, … up to 8 MB, `pg_cleanup_refs_add_files()`: a design stylesheet's picture or font) refers to, read without folder and case. A name with a space or odd characters, the variables file and custom looks and palettes are never listed.
- Shared components: no page placement (`pg_shared_component_usage()`, bin and region tags included) and inside no other component. Variables: the design's own ("Your own") whose `--pg-var-c-<key>` nothing reads; built-in ones drive the look's tokens and are never listed.
- Order: rules and files the largest first, the others by name.

## What does the window show?
- Head "Clean up" and the design's name; the lead sentence; four lists in fixed order, the first with rows open (from the Publish check: the rules). A list head: fold, a box ticking the list (dash while some), name, count, size for rules and files. Past eight rows the list scrolls.
- A row: box, picture/swatch/icon, name (selector or token, fixed-width), why, size, "Show"/"Hide": a rule's text, a picture, another file in a new tab, a component in a sandboxed frame with the canvas's stylesheets. A variable has no Show.
- States: unsaved changes ("Save the design first", "Save and look again" saves, then reads); a spinner while reading; a failed read with "Try again"; "This design has nothing unused" with Close; under 768 px it fills the screen.
- Foot: "Nothing is ticked." or "{n} ticked · {size} lighter." with what can be brought back, Close, "Remove {n}" (off while nothing is ticked). What Remove does: `designer-cleanup-removal.md`.

## What does it cost?
- One request when the window opens or reads again.

Tests: `tests/design_cleanup_list_test.php`, `tests/designer_parts_test.php`.
