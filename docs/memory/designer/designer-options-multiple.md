---
name: designer-options-multiple
description: The Options panel with several elements selected (#10468): identity row with the count, tabs by kind, "— Multiple —" per control, the ring dot, the counts, a change written to all as one undo step
---

## How is the panel of several elements built?
- `renderMultiProperties()` (Shift or Ctrl) builds as the single element's panel: `_sdSectBuild` with every body built (`lazy: null`), `_sdSectTakeKeyed()`, `_sdOptPlan()`; the open tab drawn, the others held in `_sdOptHeld` and counted on a detached copy.
- The sections come from a copy of the first element holding only the classes all share (`_sdMcProxy()`), standing in for `selectedNode` meanwhile. Text is drawn when every element has it (`_sdMcHasText()`), without display sizes when one is not a text element.
- Kinds alike (`_sdPanelKind()`): Content holds `renderMcOptions()`, a section named after the kind with its shared options by role (`_sdMcRole()`) and a line saying text, link, image address, translation and data binding belong to each element; its head keeps "shared options" while open (`data-sd-sum`).
- Different kinds: no Content (`_sdMcPieces()`), Style opens. The tab is kept per kind of selection (`_sdMcKind()`: `several:<kind>` or `several:mixed`), never shared with one element's.

## What stands in the head?
- `_sdPropsHead(node, shown, counts, mc)`: `_sdMcIdRow()` (the kind's icon, or layers, with the count on its corner; "3 elements selected"; the kind or "mixed"; the shared parent or the kinds), the tabs, then `_sdMcStrip()` "A change is written to all 3 elements at once". No menu, no path steps.

## How is a mixed value drawn?
- `_sdMcMark()` reads each row's controls (`_SD_MC_CTRL`, with its per-size box) per element (`_sdMcSpecOf()`, `_sdMcValOf()`); values that differ go to `_sdMcDrawMixed()`.
- A select: a first option "— Multiple —" (`data-mc-mixed`). A button group: no segment on, each value present framed (`.sd-seg-has`), on a `.sd-prop-row` a muted line "— Multiple —: 2 × H2, 1 × H3". Alignment icons framed; a colour's swatch split in two; a field gets the word as placeholder; a switch half set; the spacing box "~" in an accent frame, its core the count (`designer-spacing-box.md`).
- The row carries `data-sd-mixed`: a closed section whose first changed row is mixed reads "multiple".

## What do the dot and the counts mean?
- A row is changed for an element as `markModifiedProps()` sees it (`_sdMcChanged()`, defaults from `_sdPropDefaults()` and `_SD_DS_DEFAULTS`). Changed in all: the full dot; in some: a hollow ring (`.sd-chg-ring`), tip "Changed in 2 of 3 elements". Badges count rows changed in at least one element.
- The dot's reset (`data-mc-reset`, `_sdMcRowReset()` from `_sdControlsInit()`) writes the row's controls back to their defaults in every element.

## How is a change written?
- Every control writes on `change`: `_sdMcCommit()` runs `_sdMcWrite()` on each element inside `_sdUndoAsOne()` through `_sdMcApply()`, one snapshot, one undo step; shared components touched are marked dirty. A length is checked first (`_sdLenCheck()`); sizing props sync their classes (`_SD_SIZING_PROPS`).
- At content level the panel is locked when one element may not be edited (`_sdPanelLockReason(nodes)`).
- Before #10468 it was one list with no head or tabs, and the fields of Size and display, Tooltip and Accessibility, the switches of Shadow and effect and the spacing box wrote nothing.

## What do the brush, the quick strip and the review do here?
- The brush's "Paste style" writes to all, "Copy style" is dimmed; the quick strip is drawn when the kinds are alike (`designer-options-copy-style.md`). "Only what I changed": `designer-options-review.md`.

Test: `tests/designer_options_multiple_test.php` (pure block "Several elements selected: pure helpers" under Node).
