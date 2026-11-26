---
name: designer-use-round-options-panel
description: Use round UR4 (plan designer-use-round): the Options panel's DO2 to DO26 parts used in a browser at 1440, 390 and 240 px, light and dark, by keyboard; the eight faults fixed
---

## What was used, and how?
- A sandbox (`tools/setup_sandbox.sh`), Chromium through Playwright: the template "Klinik" (41 pages) and an empty design; a heading, a hero button (four states), a container made a grid, a container added from the palette by Enter.
- Style and Advanced with every section and "More" open: State, Text effect, Hide on, Z Index, Background layers, Transition, Easing, Transform, Filter, Blend, Cursor, Clip shape, grid Columns, Rows, Areas, Interactions' entrances, "Keep across pages".
- 1440 px; 390 px (the panel as the drawer, 240 wide); the column set to 240 at 1440. The OS theme light and dark: the chrome is dark in both (`designer/designer-chrome-tokens.md`), so a fault showed where Bootstrap's own colours follow the page's light theme. The keyboard: the options search, Tab through Style, "Add layer" by Enter.

## Which faults were fixed? (#11834)
- Enter in "Search or add an option…" opened nothing and left the focus on the page: the right column's capture-phase Enter guard (in `style_designer.src.js`, it stops a field's Enter submitting the editor's form) ran before the box's handler. It now skips `#sd-props-search`; `tests/designer_options_search_test.php` checks the order.
- "Hide on": Phone, Tablet, Desktop cut to "Telef… Tab… Masaü…" beside the label at 1440 and "T… T… M…" at 240. The row stands under its label at every width (`.sd-row-stack`, `style_designer_spacing.js`).
- The State row ran past the panel's right edge, "Pressed" cut, with four states in Turkish. The row wraps (the group goes under the label when it does not fit); under 300 px the segments go two to a line.
- Every slider of the DO rows (Letter spacing, Duration, Scale, Rotate, Filter, Background blur) drew no thumb: Bootstrap 6 sets a range's track and thumb variables on `.form-range`, which these inputs have no wrapper of. `#sd-properties .sd-trans-ms .form-range-input` sets them on chrome tokens.
- A unit or axis chip (`.input-group-text`: px, em, ms, °, %, X, Y) was white with 14 px text under a light OS theme; the Z Index field and its layer list too. Both on the chrome's tokens; a slider's number has room for four digits.
- Text's "Outline" crushed its width field to 21 px and cut the colour list; Transform's Move and Skew showed no field at 240. A two-field row shares its width, and stands under its label under 300 px (`sd-row-stack-n` from the row builders).
- "Add layer", "Play on the canvas" and "Edit the curve" (`.btn-outline.theme-secondary`) were white buttons under a light OS theme: on the secondary button's tokens.
- Interactions' help lines (`.form-text`) were Bootstrap's light-theme grey at 14 px, hard to read on the dark card: `--sd-text-muted` at 11.5 px.

## What could not be run?
- No AI key: nothing of UR4 needs one. Timing (`designer-performance.md`) was not measured: the fixes are CSS and two class names, nothing runs while a pointer moves.

Related: docs/memory/workflow/designer-use-round-options-panel-left.md
