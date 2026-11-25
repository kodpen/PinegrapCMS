---
name: designer-options-effects
description: Filter, Background blur and Blend in Shadow and effect's "More" as built (DO15, #11700): the rows, how a state holds a filter, the glass hint, the slider preview, the part, the cost
---

## What do the effect rows hold?
- In "Shadow and effect", under the section's "More" after Skew and Origin (`_sdEffRowsHtml()`): sub-head "Filter" with seven sliders and their numbers: Blur (0 to 20 px), Brightness, Contrast, Saturate (0 to 200 %), Grayscale, Sepia (0 to 100 %), Hue (−180° to 180°). A typed number may pass a slider's end (Blur to 100, the three per cents to 300).
- Sub-head "Blend": "Background blur" (0 to 40 px; one info line once set: a see-through background makes the glass panel), "Blend" (mix-blend-mode) and "Background blend" (background-blend-mode), each a list of the sixteen modes, "Normal" first.
- "More" opens by itself while any of them holds a value in Normal or the state. Shown for one element whose custom style is on (`_sdStateNode()`), as Transform.

## Where is it written?
- `PGOptionStates.fxWrite(text, cls, st, key, v)` (part `states`) on `.pgs-xxxxxx` or the state's rule, no `!important`: one `filter` in the fixed order blur, brightness, contrast, grayscale, saturate, sepia, hue-rotate; `backdrop-filter: blur()` with its `-webkit-backdrop-filter` twin (Safari before 18); `mix-blend-mode`; `background-blend-mode`. Normal's defaults (0 px, 100 %, 0 %, 0°, Normal) are not written.
- A state's `filter` replaces Normal's, so a state's rule holds the whole list (Normal's values with the state's change); equal to Normal's it goes, and `fxRead()` on a state reads a value equal to Normal's as not set (grey). A later change of Normal is carried into each state on the values it took from Normal.
- A `filter` no row reads (a `drop-shadow()`, a `url()`, another unit) is left as it is, in Normal and in every state; its rows read empty.
- In `FAMILIES` the four are rows of "styling", so a state's count and the section's ring include them; the -webkit- twin counts with its row.

## What does the canvas show?
- While a Filter or Background blur slider is dragged the selected element shows the composed value inline (`!important`, `_sdEffPreview()`); it goes when the slider is let go or loses the focus. Under a state `#sd-state-view` draws the state's rule as for every state value.

## How does it load, and what did it cost?
- Rows are core markup; the model is in part `states` (`pg_option_states.js`), loaded when the User Styles hold one of the four properties with the element's class (`_sdEffOf()`, a regex look, no parse) or a row is first used.
- Core +4.8 KB minified (+1.5 KB gzip); part `states` 25.0 → 28.7 KB minified (8.9 → 10.0 KB gzip), terser 5.51.2. The Node edit-cycle timing (`designer-performance.md`) stayed within noise (undo snapshot 0.82 ms, baseline 1.12 ms).

## What is not built?
- The Options search finds the rows only while "More" is open (it reads the drawn panel), as for Skew and Origin.
- No browser run: the drag preview sets one inline property per input event.

## Where is it tested?
- `tests/designer_option_states_test.php` (`test_designer_option_states_effects`). The panel and canvas are not unit tested.

Related: docs/memory/designer/designer-options-transform.md, docs/memory/designer/designer-options-states-drawing-motion.md, docs/memory/designer/options-panel-census-look.md
