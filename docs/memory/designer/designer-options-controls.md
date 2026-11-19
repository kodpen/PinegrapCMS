---
name: designer-options-controls
description: Options panel controls of the Visual Page Editor: button groups, the changed dot and its one-step reset, the size badge, length fields that refuse a bad value, rows stacked under 300 px
---

## When is a value list a button group?
- While the single element's panel is built (`_sdSectBuild`), `row()`, `mkRespRow()` and `mkNonRespRow()` read a lone select (`_sdSelParse()`); `_sdSegPlan()` decides: up to six values a group, seven or more a select.
- A group wider than the control beside its label (162 at 336 px) stands under it (`.sd-row-stack`); one wider than the row (262) stays a select, so no segment text is cut. Estimate: a capital 8.4, other characters 6.8, 14 a segment; segments grow from their text.
- The several-element panel and the per-size boxes keep their selects.

## When does a row stand under its label at 240 px?
- Under 300 px (`.sd-rcol-narrow`) `.sd-row-stack-n` stacks a row: a group of four or more written segments or wider than 104 (`_sdSegPlan()`; four icons stay beside); a size row, whose chevron or badge leaves the control under 104; a label that needs over two lines (`_sdNarrowStack()` measures it). No label ends in an ellipsis.
- `_sdNarrowStack()` runs in `markModifiedProps()`, when the column turns narrow and when a section opens. No `:has()` rule: `tests/css_has_restyle_test.php` refuses one that styles every child.

## How does a group write its value?
- `_sdSegGroup()` keeps the select hidden (`select.sd-seg-src`) with its `data-stylegroup` / `data-prop` / `data-util`: handlers, the changed marking, the search and `focusClassControl()` read it as before.
- A segment click (delegated in `_sdControlsInit()`) sets it through `_sdSegNext()` and fires its change; a click on the selected segment removes the value. `_sdSegSync()` sets `aria-pressed` and names the group by its label. The alignment icons follow the same rule.

## What is the changed dot?
- Every row keeps a 14 px place before its label (12 under 300 px). `markModifiedProps()` marks a changed label `.sd-prop-modified`; `_sdChgResetButtons()` puts `button.sd-chg-reset` before it: a 7 px accent dot, an undo arrow on pointer or focus, tip "Reset to default", name "Reset {var} to default"; disabled in a read-only panel.
- A size row is changed when any size in its per-size box holds a value (`_sdSizeCtrls()`, `_sdSizeHeld()`), its own control empty or not; its reset clears every size (`_sdSizesClear()`), then its own control.
- The button calls `_sdRowReset(lbl)` inside `_sdUndoAsOne()`: one undo step.

## How is a reset one undo step?
- `saveState(key)` joins a call with the same key inside 700 ms to the previous snapshot (`_sdStateRunTakes()`). `_sdUndoAsOne(run, fn)` gives `_saveStateRun.one` a fresh key while `fn` runs, so every `saveState()` in it joins the first.

## When does a row show the screen-size badge?
- `_sdSizeBadge(cls, prefix, values)`: the first size holding a value ("MD"); "all" when a value holds at every size and a size changes it; nothing otherwise. `_sdSizeToggle()` draws it as the button opening the per-size box (amber; "all" grey), or a chevron without a badge. The box starts closed; the plan's size chooser (D17) will replace the chevron.

## How does a length field refuse a value?
- The Sizing fields (top/right/bottom/left, width, height, min/max) are `lenInp()`: `data-sd-len="len"`, a grey "px" while the value is a bare number, placeholders through `_sdT()` ("örn. %100").
- The `[data-prop]` input handler runs `_sdLenCheck()`: a CSS length, a bare number, a keyword or a CSS function with closed brackets passes; anything else is not written. `_sdFieldInvalid()` draws the field red and one line under the row (`aria-describedby`).

## Where are the measures?
- `measures-3-controls.jpg`: field and select 30, corner 9; group 28, segment 24, corner 7; badge 18 in a 24 target; label 12 px, column 92, gap 10, row 32, 6 apart.

Test: `tests/designer_options_controls_test.php` (pure helpers under Node).
