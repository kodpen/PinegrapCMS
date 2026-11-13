---
name: legacy-bootstrap-trial-design
description: The Bootstrap 5 trial design of a Legacy design (LB4): what Make a trial design copies and converts, the asset rows, the record file, Delete while unused, the side-by-side preview
module: legacy_bootstrap_trial
---

## Where is it?
- The "Towards Bootstrap 5" card (`legacy-bootstrap-card.md`): beside Report, "Make a trial design" (POST, token, designers; refused while a move runs). Then "Trial design: <name>" (editor link), Preview, "Delete the trial design" (a second button confirms; none while used). Module `includes/fn/legacy_bootstrap_trial.php`.
- Only for a Legacy design on Bootstrap 3 by the card's test (`pg_lbs_design_assets()`); one trial per design.

## What does Make a trial design write?
- A style row (`save_system_style()`): framework `bootstrap5`, name "<name> (Bootstrap 5)" (`pg_lbs_trial_name()`, unique), the Legacy row's settings, fonts, body classes, its own tree converted.
- A copy of every shared part the pages (bin left out) and that tree place, nested too: tree through `pg_lbs_tree()`, places pointed at the copies (`pg_lbs_trial_refs()`), category `legacy_bootstrap:<trial>:<part>` (never `classic_style:`, which Back to classic reads). A widget's config is copied as it is.
- A copy `<name>-bs5.<ext>` (same folder, design and theme flags) of each site file whose text changes: sheets through `pg_lbs_css()`, scripts through `pg_lbs_trial_script()`. CDN and software files stay.
- Theme file: the design's, else the activated theme, as `theme_id` (its copy when renamed); none when a row links it, so it is not loaded twice.
- A failure half way takes all back. The Legacy design, pages, parts and files are never written.

## Which asset rows (`pg_lbs_trial_assets()`, pure)?
- CSS: every `bootstrap(.min).css` row goes; at the first one's place the editor's `bootstrap-css` and `bootstrap-icons` rows, then the compatibility sheet `{path}{software_directory}/assets/css/themes/legacy-bootstrap3.css`, so the theme still wins. Inline rows converted.
- JS: the `bootstrap-js` bundle row where Bootstrap 3's script was (first when none), that script kept `enabled: false` (open question in the LB4 PR). Inline JS converted. Copies are managed `_file` rows; `pages` limits stay.

## What does the script conversion do?
- A tooltip or popover call on `$('<quoted selector>')`/`jQuery(...)`, no option or a plain object, becomes `getOrCreateInstance()` per element found; `pg_lbs_data_names()` become `data-bs-*` (whole names). Other plugin calls stay, listed as warnings. Starting `scripts.js`: one line changes.

## What is the compatibility sheet?
- The Bootstrap 3 names the page types' layouts print (form-group, text-right, help-block, img-responsive, btn-default, checkbox, input-group-addon/-btn, visible-xs-inline below sm as the converter, glyphicon drawn with the Bootstrap Icons twin's code point). A software file: rules leave as `legacy-layouts-bootstrap.md` converts the layouts.

## Where is a trial recorded?
- `data/legacy_to_bootstrap/trials.json`: Legacy id => trial, map (part => copy), files (ids), made, user. `pg_lbs_trial_of()` drops one whose row is gone (deleted or binned in Visual Designs).
- Delete (`pg_lbs_trial_delete()`): refused while pages (bin too) or folders use it or the editor has it open; deletes the row, the copies of its category no tree places, the files no other style links.

## How is a page previewed?
- `legacy_to_designer.php?preview=<legacy>&page=<page>`: page picker, Legacy and trial live pictures at 1280×800 and 390×844, without scripts or site-served icon fonts.
- `design_preview.php?page_id=&bootstrap5=<trial>`: full designers, the trial of the page's own design, `no-store`; `pg_lbs_preview_override()` gives `get_page_content()` the trial's row and `pg_lbs_trial_page_code()`.

## What did the sandbox show?
- home-2 seti: made and deleted in 0.5 s each (194 parts, two files). Page home-2 with scripts: no error either side; the blog masonry strip stacks in one column in the trial.

Tests: `tests/legacy_bootstrap_trial_test.php`.
