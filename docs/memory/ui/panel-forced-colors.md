---
name: panel-forced-colors
description: The panel in Windows High Contrast (forced colours): the one @media (forced-colors: active) block in backend.src.css, what it draws, how to add a new state to it, how it was checked
---

## Why does the panel need a forced-colours block?
- In forced colours the browser paints every author background as Canvas and drops `box-shadow`. A state drawn only by a fill, a `::before` background or mask, or a shadow ring vanishes. Bootstrap 6's dist has no forced-colours rules.
- Before, checked and unchecked `.check`/`.radio`/`.switch` looked the same, the `.btn-close` X vanished, the current tab, pager and menu row and pressed toggles could not be told apart, progress bars and stepper discs went, and the role card and chips field had no visible focus.

## Where is it, and what does it draw?
- One `@media (forced-colors: active)` block at the end of `@layer custom` in `assets/css/backend.src.css`, before the unlayered rules. Nothing outside it changes, so normal-mode pixels stay.
- The tick, dot and knob (`::before`) are CanvasText; a checked switch has a Highlight track and a HighlightText knob; `.btn-close` is ButtonText.
- Current and pressed items sit in one `:is(…)` list with `forced-color-adjust: none`, Highlight fill, HighlightText text and a Highlight border; their descendants inherit the colour, so icons and labels stay readable.
- `.progress` gets a CanvasText frame and `.progress-bar` a Highlight fill. Stepper discs get a frame; `.active` discs and track a Highlight fill; the `aria-current="step"` disc a ring too. The offer editor's `.pg-stepper-cards` items are all `.active` by design, so they all turn Highlight.
- `.pg-role-card` focus and `.pg-chips:focus-within` get a 2px Highlight outline.

## How do I add a new on state?
- Add its screen selector to both `:is(…)` lists. `:is()` takes the specificity of its most specific entry (`#header .pg-um-themebtn.active`), so the list beats any screen rule it repeats. A state shown only by a shadow or a `::before` needs its own rule.

## How was it checked?
- Playwright Chromium with `emulateMedia({forcedColors: 'active'})`, light and dark, old and new `backend.min.css` on a fixture of the controls: states visible after, gone before; with forced colours off the screenshots were byte-identical. Computed backgrounds of the theme switch, activity filters, visitor cells, sale switch and method tiles, Workspace rich-text and format tools, list-group and menu rows were Highlight. Real panel screens were not opened.
- `tests/panel_forced_colors_test.php` pins the block in the source.

Related: `panel-focus-ring.md`, `current-and-pressed-state.md`.
