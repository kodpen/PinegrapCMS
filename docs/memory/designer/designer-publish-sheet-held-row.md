---
name: designer-publish-sheet-held-row
description: The Publish sheet's held row: where the hold comes from (an open experiment's variant, a release bundle's draft), the bundle's label and text, the toast and box title naming the bundle
module: experiments_sheet
---

## Which pages are held rows?
- A draft an open experiment keeps as its variant, and a draft an open, scheduled or partly published release bundle holds (`pg_page_bundle_hold()`). Both rows have state `held` and a disabled box (`designer-publish-sheet.md`).
- The experiment comes first, as in `pg_experiment_variant_hold_message()`; a draft in both reads as the experiment's.

## Where does the hold come from?
- The page load: `_design.experiments.pages` from `pg_experiment_editor_config()`, one entry per tab page by `pg_experiment_editor_page_state()`. `includes/designer_screen.php` was not changed for it.
- A bundled draft's entry: role `bundle`, `held`, `bundle_id`, `bundle_name`, `label` ("Held by the bundle {var:1}") and `hold_text` (`pg_page_bundle_hold_text()` with the page's stored name). It has no `status`, so the top bar shows no experiment chip.

## What does the row say?
- `_pgPublishRows()` hands a held row's `label` and `note` (the hold text) to `pg_designer_publish.js`: the badge shows the label, the row's tooltip the note. Without a label the badge reads the markup's "Held by an experiment".
- `_pgHeldText()` gives the draft settings box's title and the toast of a refused Publish the bundle's text, or the experiment's. The row's words never name an experiment for a bundled draft.
- A pill that does not fit beside the page name goes under it, starting where the name starts, and its text breaks into lines; the name is cut only when it is longer than the row alone. One rule in the sheet's block of `style_designer.css` (the box out of the flow in the row's start padding), for every state's pill. Architect (2026-11-17, agent channel message 8148): accepted at 1440, this change at 390.
- The hold is read when the editor opens; a bundle changed meanwhile shows at the next load. The server refuses the publish with the bundle's name either way.

Built in #10191 (release bundle step RB2b). Test: `tests/experiments_editor_bundle_hold_test.php`.
