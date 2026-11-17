---
name: designer-options-controls
description: Options panel controls of the Visual Page Editor: button groups, the changed dot and its reset, the screen-size badge, length fields that refuse a bad value, reset as one undo step
---

## When is a value list a button group?
- While the single element's panel is built (`_sdSectBuild` set), `row()`, `mkRespRow()` and `mkNonRespRow()` read a lone select (`_sdSelParse()`) and `_sdSegPlan()` decides: up to six values a group, seven or more (or none) a select. A group wider than the control beside its label (172 at 336 px) stands under the label (`.sd-row-stack`); one wider than the whole row (274) stays a select. Widths are estimated from the 11.5 px text.
- Under 300 px (`.sd-rcol-narrow`) a group of four or more written segments, or one wider than 104, stands under its label (`.sd-row-stack-n`); up to four icons stay beside it.
- The several-element panel and the per-size boxes keep their selects.

## How does a group write its value?
- `_sdSegGroup()` keeps the select, hidden (`select.sd-seg-src`, `hidden`, `aria-hidden`), with its `data-stylegroup` / `data-prop` / `data-util`: the change handlers, the changed marking, the property search and `focusClassControl()` read it as before.
- A segment click (`_sdControlsInit()`, delegated on `#sd-properties`) sets the select through `_sdSegNext()` and fires its change: a click on the selected segment removes the value when the select has an empty choice. `_sdSegSync()` marks the selected segment (`aria-pressed`) and names the group by its row's label.
- The alignment icons of Text Options follow the same rule (`_sdSegNext()`); their old "×" default button is gone.

## What is the changed dot?
- Every row keeps a 14 px place before its label (12 inside the 78 label column under 300 px). `markModifiedProps()` marks a changed row's label `.sd-prop-modified` as before; `_sdChgResetButtons()` puts a `button.sd-chg-reset` before it: a 7 px accent dot with its ring, an undo arrow under the pointer or keyboard focus, tip "Reset to default", name "Reset {var} to default". Disabled in a read-only panel.
- The label itself no longer resets on a click. The button calls `_sdRowReset(lbl)` inside `_sdUndoAsOne()`: everything the reset writes is one undo step.

## How is a reset one undo step?
- `saveState(key)` joins a call with the same key inside 700 ms to the previous snapshot (`_sdStateRunTakes()`). `_sdUndoAsOne(run, fn)` sets `_saveStateRun.one` to a fresh key while `fn` runs, so every `saveState()` in it, with or without a key, joins the first. Nested calls keep the outer key.

## When does a row show the screen-size badge?
- `_sdSizeBadge(cls, prefix, values)`: the first size (SM…XXL) holding a value when no value holds at every size ("MD"); "all" when a value holds at every size and a size changes it; nothing otherwise. `_sdSizeToggle()` draws it as the button that opens the row's per-size box (amber; "all" grey), or a quiet chevron when there is no badge. The box starts closed.
- The section's size chooser of the plan (D17) will take the chevron's place.

## How does a length field refuse a value?
- The Sizing rows' fields (top, right, bottom, left, width, height and their min/max) are `lenInp()`: `data-sd-len="len"`, a grey "px" at the right while the value is a bare number (`.sd-unit-off` otherwise).
- The `[data-prop]` input handler runs `_sdLenCheck()` first: a CSS length, a bare number (pixels, `_sdCssLen()`), a keyword (auto, none, inherit, fit-content…) or a CSS function with closed brackets passes; anything else is not written. `_sdFieldInvalid()` draws the field red and one line under the row (`.sd-field-err`, `aria-describedby`); a valid value clears it.

## Where are the measures?
- Plan sheet `measures-3-controls.jpg`: field and select 30, corner 9; group 28, segment 24, corner 7; badge 18 in a 24 target; label 12 px, column 92, gap 10, row 32, 6 apart.

Test: `tests/designer_options_controls_test.php` (the pure helpers under Node).
