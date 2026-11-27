---
name: designer-bottom-panel-heads
description: The Page Design Studio bottom panel heads (30 px, one chevron at the right end, tags, "No element selected", the narrow Styles head) and the status bar line, Help and Validate
---

## How are the bottom panel's heads drawn?
- Three parts side by side, each with a 30 px head in sentence case at 12.5 px: HTML (and its code view switch, "Edit"), Attributes, Styles. Board F5 of `designer-frame-tidy-drawing.md`, step FR3.
- The chevron that shuts the panel (`#sd-bp-collapse`) is the last control of the Styles head, the panel's right end; the shut strip shows it at its right end too. Before FR3 it stood in the HTML head, in the middle of the panel.
- The selected element's tag stands beside Attributes and Styles as the bare tag (`h1`), or "3 elements" for a multi-selection. The Styles one is written by the live `renderStylesPanel()`; it was never written before.

## What do Attributes and Styles say with nothing selected?
- One line at the top of the part, "No element selected" (`_sdBpNoneHtml()`, class `sd-bp-none`); the large icons in the middle are gone.

## What does the Styles head do when it is narrow?
- Under 460 px of the part's own width (`_sdBpStylesNarrow()`, a ResizeObserver on `#sd-bp-quad-tr`, class `sd-bp-styles-narrow`) "Create" keeps its word, "Export to a File" is its icon and the rules' search is a button (`#sd-styles-find-btn`) that lays the field over the head (`sd-find-open`). Escape, or leaving it empty, shuts it; a typed search keeps it open. Tests in `tests/designer_right_column_test.php`.
- Why: the head ran out of its part and its last control was cut.

## What does the status bar show?
- 24 px: the selection's path; "Saved 19 hours ago by admin" (`lang('Saved {var:1} by {var:2}')` in `designer_screen.php`; after an AJAX save the date and the user with the same words); Help with its word (`#sd-status-shortcuts`, the first tour's last stop); Validate.
- Validate shows its result in its place: "Validate" until the first check has run, then "Valid", "1 problem" or "N problems" (`updateValidationChip(ran)`; `scheduleValidation()` passes `true`).
- The design Settings summary shows the saved line as it stands, no longer behind "Last saved:".

Related: docs/memory/designer/designer-right-column.md, docs/memory/designer/designer-frame-tidy-drawing.md
