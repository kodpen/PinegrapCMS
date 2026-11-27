---
name: bug-hunt-areas-cleanup
description: Bug hunt of the designer's Clean up (finder, lists, Remove, window; 2026-11-27): the six faults fixed, what was read clean, what was left
---

## Which area was it?
- The designer's "Clean up" (`designer-cleanup.md`): `includes/fn/design_cleanup.php`, `design_cleanup_list.php`, `design_cleanup_remove.php`, the sub-actions `cleanup_list` and `cleanup_remove` in `includes/panel/designer.php`, `assets/js/pg_design_cleanup.js` and `style_designer_cleanup.js`. `style_designer.src.js` (`_sdCleanupHost()`) was read, not changed.

## What was fixed?
- A file named only inside a text file of the site (a design stylesheet's `url()` picture or font, a script's picture) was listed and went to the Recycle Bin: text files are now read for names (`pg_cleanup_refs_add_files()`).
- A file named only in a page's meta tags (a share picture) or structured data (a logo) was listed: both columns are read (`pg_cleanup_add_page_row()`).
- A class a script builds after a value (`tone + '-card'`, `` `${size}_box` ``) was counted unused: such an end now covers every class ending with it.
- A name built in an event attribute (`onclick="…classList.toggle('tab-' + n)"`) had only its words read: event attributes are read as scripts.
- A list with a row that stayed after Remove had a head box that never showed ticked, so its click ticked again and never unticked: the box counts only rows with a box.
- Closing the window while "Removing…" kept the ticked rules and variables though files and components were gone: the window cannot be closed while it runs.

## What was read clean?
- Rights and CSRF: both sub-actions go through `validate_token()` and the full-designer gate of `pg_panel_designer()`; files go to the bin with `check_edit_access()` and the design-file role check; `cleanup_remove` is a dirty page-cache sub-action.
- Remove re-reads the lists and takes only rows still listed; a rule is known by sheet place, start and text hash, so a rule another tab moved or changed is answered `changed`.
- The snapshot is written first and nothing is removed without it; a failed save puts the fields back.
- System widget output: its non-Bootstrap classes are `pg-*` (never listed).

## What was left?
- Two tabs of the same design: a tab holding unsaved work that puts a class to use is not seen by Remove in the other tab; when it saves, the rule may be gone. The same holds for every design-wide asset saved from two tabs; not a Clean up fault.
