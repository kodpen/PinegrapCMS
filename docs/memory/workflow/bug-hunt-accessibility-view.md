---
name: bug-hunt-accessibility-view
description: Bug hunt of the designer's Accessibility view (style_designer_accessview.js, PGA11yReader, includes/fn/a11y.php), 2026-11-26: the seven faults fixed in #12005, no issue opened, what read clean
---

## What was the area?
- The designer's Accessibility view: `assets/js/style_designer_accessview.js`, the reader `assets/js/pg_a11y_reader.js`, the audit `includes/fn/a11y.php`; the core's `_sdA11y*()` read, not changed.

## What did it fix? (PR #12005 names each with file and line)
- Shared components and system widgets: their content was left out as editor chrome (`div.sd-shared-inner`).
- Another screen size kept the old width's reading; a zoom or Fit left the marks at their old places.
- Each hover and scroll read the whole page again (the editor's toolbars and chips seen as page changes).
- An in-place text edit showed the edited element as a Tab stop and its label as missing.
- A name tip stayed after another kind, a scroll or leaving the canvas.
- The heading outline jumped to its top and the focus to its first row on each reading.
- The audit named a control by text a screen reader never reads, so the view marked faults the audit did not list.
- What each does now: `designer/accessibility-view-reading.md`, `designer/accessibility-reader.md`.

## What was opened as an issue?
- None.

## What read clean?
- Nothing enters the canvas's page: layers, card, pill, menu and tip live in the editor's document; "See as" is style on the frame and goes with `hide()`.
- `hide()` takes every listener, observer, timer and layer away; another page's document ends the old view and keeps only the filter.
- Fault codes and sentences equal the audit's for the six rules; the first heading skip only is marked, as decided.
