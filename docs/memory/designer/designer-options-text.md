---
name: designer-options-text
description: Text shadow, Letter spacing, Font family, "Limit to lines", Gradient fill and Outline in the Text section's "More" as built (DO16, #11720): the rows, what they write, the part, the cost
---

## What do the Text "More" rows hold?
- At the end of the Options panel's "Text" section (`propsTextOptions()`, `_sdTxRowsHtml()`), a quiet "More" row drawn as in "Shadow and effect" (`.sd-tx-more`). Open, under the sub-head "Text effect":
- "Text shadow": None, Soft, Medium, Strong, Glow (Glow mixes `--bs-primary`).
- "Letter spacing": a slider from −0.1 to 0.5 em with its number (a typed value may go from −0.5 to 2 em). A heading (h1–h6, content Heading) shows the look's `--pg-heading-tracking` in grey until set, read from the canvas; nothing is written for it.
- "Font family": Default, "Body font · <family>", "Heading font · <family>" (`var(--pg-font-body)` / `var(--pg-font-heading)`, names read from the canvas), so a change of the design's fonts follows.
- "Limit to lines": 1 to 10, empty for no limit.
- "Gradient fill": None, Primary to info, Primary to secondary, Success to info, Warning to danger; one info line once set (it takes the place of a background picture of the element).
- "Outline": a width in px (0 takes it out) and a colour: Text color (`currentcolor`), Primary, Secondary, Dark, Light, White, Black.
- "More" opens by itself while one of them holds a value. Shown for one element whose custom style is on (`_sdStateNode()`).

## Where is it written?
- `PGOptionStates.txWrite(text, cls, key, v)` (part `states`) on the element's `.pgs-xxxxxx` rule in User Styles, no `!important`, Normal only: the Text section takes no state (it is dimmed under Hover, Focus, Pressed).
- Declarations: `text-shadow`; `letter-spacing: <n>em`; `font-family`; the line limit as `display: -webkit-box`, `-webkit-box-orient: vertical`, `overflow: hidden`, `-webkit-line-clamp` and `line-clamp`; the gradient as `background-image: linear-gradient(90deg, var(--bs-a), var(--bs-b))` with `-webkit-background-clip`/`background-clip: text` and `-webkit-text-fill-color: transparent` (`color` stays the fallback); the outline as `-webkit-text-stroke` with `paint-order: stroke fill`.
- Taking the limit out takes out `display` and `overflow` only while they hold the limit's own values. A Bootstrap `d-*` class (`!important`) wins over the limit's `display`.
- A value no row writes (a text-shadow of its own, letter spacing in px) is left as written and reads as `'own'`: the list shows "Your own", the field its placeholder; choosing a value replaces it, the reset arrow takes it out.

## How does it load, and what did it cost?
- Rows are core markup; the model is in part `states`, loaded when the User Styles hold one of the properties with the element's class (`_sdTxOf()`, a regex look, no parse) or a row is first used.
- While Letter spacing is dragged the canvas element shows the value inline (`!important`, dropped when let go), as DO15's sliders.
- Core +5.8 KB minified (+1.4 KB gzip); part `states` 28.7 → 32.2 KB minified (10.1 → 11.2 KB gzip), terser 5.51.2. The Node edit-cycle timing stayed within noise (undo snapshot 0.79 ms, baseline 1.10 ms).

## What is not built, and where is it tested?
- Text wrap balance for headings (census, no step). The Options search finds the rows only while "More" is open.
- `tests/designer_option_states_test.php` (`test_designer_option_states_text`). The panel and canvas are not unit tested; no browser run.

Related: docs/memory/designer/designer-options-effects.md, docs/memory/designer/designer-options-states-drawing-motion.md, docs/memory/designer/options-panel-census-look.md
