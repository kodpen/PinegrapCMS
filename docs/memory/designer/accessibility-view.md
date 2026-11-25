---
name: accessibility-view
description: The designer's Accessibility view as built (AV2): the View menu switch, the part accessview, the card, the tab order, heading and name marks, the fault's card
---

## Where is it turned on?
- "Accessibility view" (`#sd-vb-a11y`) is the last switch row of the canvas bar's View menu, counted in the View button's badge (`_sdCanvasBarSync()`), and "Show accessibility view" / "Hide accessibility view" in the command palette (`act_a11y_view`). No bar button, no key. Design mode only: Prototype mode hides it (`_sdCanvasBarMode()`).
- Kept per browser in `localStorage` `pg_sd_a11y` as `{on, kind, fold}` (`_sdA11yStateOf()`: off, Tab order, unfolded by default; kind `''` is no marks).

## How does it load?
- The part `accessview` (`includes/designer_parts/accessview.php`, `when: always`): `assets/js/pg_a11y_reader.js` then `assets/js/style_designer_accessview.js`. The core keeps `_sdA11yState()`, `_sdA11yKeep()`, `_sdA11ySet()`, `_sdA11yHost()` and `_sdA11ySync()`; the part is run the first time the switch is on (`show(host)`, `hide()`).
- `render()` ends with `_sdA11ySync()`: while on, the part reads the page again 300 ms after the last draw. A MutationObserver on the canvas body does the same for in-place redraws and typing; scroll and resize only move the marks. While off the part holds no listener, timer or observer.

## What does the card show?
- 258 px, 12 px from the canvas's bottom right corner, in the canvas wrapper (never in the canvas's page). Head: the name, fold (the kind chosen in grey when folded), close (turns the switch off; Escape inside the card too).
- Three rows Tab order, Headings, Names: one choice; pressing the chosen one again clears the marks. Each row: the count and, in amber with the warning icon, the marked places.
- Counts: Tab order = stops, marked = steps that jump back (`jumps()` on the measured boxes); Headings = headings, marked = the first skip only; Names = regions + pictures, marked = pictures without text + controls without a name.
- Foot: "15 stops. One step jumps back on the page.", "5 regions, 4 pictures. 3 places have no name.", or the nothing-found sentence; Headings shows the outline instead (a row per heading, indented by level, scrolls past eight rows; a row chooses its heading and scrolls the canvas to it).
- Under 768 px (window width) the card is a bar at the canvas's foot: the kind as a select, the marked count, close.

## How are the marks drawn?
- In `.sd-av-layer`, an editor layer over the visible frame (the same geometry as the guides part); fuchsia `#c026d3`, amber `#f59e0b` dashed with the warning icon for what is marked.
- Tab order: a number on each stop's top left corner, "1 · tabindex 1", amber "2 · jumps back"; an SVG line joins them, amber and dashed for a jump. Under the pointer a tip "8 · Link “See the timetable”".
- Headings: a frame and an "H2" tag above; "H2 · assumed"; amber "H4 · skips H3".
- Names: dashed region outlines tagged "Navigation “Main menu”" (above), "Main" (inside top right), others inside top left; picture chips (text in quotes, italic "decoration", amber "no text"); amber "Button · no name", "Link · no name", "Field · no label", "Frame · no title" under the control; a named control's name as a tip under the pointer.
- A mark's click chooses the element (`_sdFindNodeForEl()` on the nearest `data-sd-id`). An amber mark also opens the fault's card: the audit's sentence (`pg_a11y_issue_label()`, the same English in `_avFaultSentence()`) and "Show in the audit" (opens the audit panel at its Accessibility section). No "Fix": the audit has none for the six rules the reader raises.

## What is not in it yet?
- "See as" (AV3, board V5) and its select in the card and the narrow bar.

Tests: `tests/designer_a11y_view_test.php`. Plan: `plans/designer-accessibility-view.md`; drawing: `designer-accessibility-view-drawing.md`.
