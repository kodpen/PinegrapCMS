---
name: designer-panels-steps
description: Plan designer-panels, steps DP1 to DP5: the chrome's tokens, the right column, the Options panel's identity row and section cards, its controls and changed dot, the three tabs
---

## Which steps are there?
- DP1 (M) the chrome's tokens.
  - Existing files: `assets/css/style_designer.css`: the `--sd-*` block takes the values of `designer-panels-measures.md` and gets the missing tokens (raised grounds, the two lines, label and secondary text, strong accent, the three state colours, the soft accent); the rules of the Options panel, the Overview, the page strip and the canvas bar read tokens only. `tests/designer_chrome_contrast_test.php`: every pair of the measures. No JavaScript.
  - Pictures: the whole editor before and after, an element selected, the bottom panel open, beside `options-4-whole-screen.jpg`.
  - The PR body counts the colours still written outside a token and where.

- DP2 (M) after DP1: the right column.
  - Existing files: `includes/designer_screen.php` (`#sd-panel-right`, `#sd-properties`, the `#sd-assets-*` panel), `style_designer.css`, `assets/js/style_designer.src.js` (the column's two tabs, `_SD_RAIL_ICONS`, the bottom panel's remembered state).
  - The column's default width becomes 336 px above 1024 px; a width the user dragged stays; 240 px at 1024 and below stays, and under 300 px the panel is drawn as `designer-panels-measures-4.md` says.
  - Tests: the remembered state's reader as a pure function.
  - Pictures: Options over the whole column, the Design tab, the bottom strip closed and open, docked under 992 px.
  - Features: D1, D2. Keeps K5.

- DP3 (M) after DP2: the identity row and the section cards.
  - Functions: `renderProperties()`, `sect()`. New: a pure module for what counts as changed and for a section's summary, with its test.
  - Tests, red first: a row differing from its default counted; a section's and a tab's count; the summary of each kind (a colour, one value, none, default); a body not built while its section is closed.
  - Pictures: beside `options-1-content.jpg` and `measures-2-anatomy.jpg`, parts a, d, e, g.
  - Features: D3, D6, D7, D8, D9, D11. Keeps K2, K3, K10.

- DP4 (M) after DP3: the controls and the changed dot.
  - Functions: `row()`, `mkRespRow()`, `mkNonRespRow()`; `.sd-prop-modified` becomes the dot.
  - Tests: reset as one undo step; an invalid value not written; the selected segment clicked removes the value; the size badge only on a value that starts at a size.
  - Pictures: every state of `measures-3-controls.jpg`, one crop each.
  - Features: D10, D12, D13. Keeps K1, K2.

- DP5 (M) after DP4: the three tabs and the sections' new places.
  - Functions: `renderProperties()` (the order of sections), the tab remembered per element kind. The table of `designer-panels-measures-3.md` row by row.
  - Tests: every section of today reached in a tab for a heading, a row, a picture, a link and a form field; a content-level user's hidden rows still hidden and an empty tab not drawn; another kind opens Content at the top.
  - Pictures: the three tabs beside `options-1-content.jpg` and `options-2-style-advanced.jpg`.
  - Features: D4, D5, D14. Keeps K4.
  - Over 1,000 product lines: cut as DP5a (the tabs, Content) and DP5b (Style, Advanced), said before the claim.

## Which steps wait for each other?
- Each waits for the one before it. DP5 also waits for the drawing of a multiple selection (`designer-panels-rules.md`). DP3 to DP5 draw their parts under 300 px as `options-5-narrow-column.jpg` shows.
