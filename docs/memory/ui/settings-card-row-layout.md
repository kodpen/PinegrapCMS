---
name: settings-card-row-layout
description: How a settings card lays out one setting per row via stylesheet only: shape-based :has() rule, dividers between rows, name+control grid, single vertical rhythm.
---

## How are settings cards arranged?
- Cards stack at full width, not two columns: `.pg-set-card` is a CSS container.
- One setting is one row: name and description on the left, the control on the right, a line between two decisions. The rule lives in the stylesheet and fires by SHAPE (a cell with a label + ordinary control, or a cell with a single checkbox/switch); cards were NOT rewritten.
- Untouched (they want full width anyway): textarea, code field, tag field, table, button group, nested row. Radio groups are also outside: spreading each option to the two ends of the card stops the group being a group.
- The criterion is `:has()`, deliberately. An unsupporting browser sees the old stacked layout, not a half-applied one; for a stylesheet that is the right behaviour for a missing feature.
- A transformed cell is always full width; the half-row grid below applies only to untransformed cells. Three side by side is cramped at any card width (Merchant Center block once had six, session restriction three).

## Where are dividers drawn?
- BETWEEN rows, not under them (`border-top` on `* + <predicate>`). "Do not draw under the last one" asks a question a stylesheet cannot answer: the last VISIBLE row. A switch that opens a block keeps itself on the page while the block is closed, so the last row on screen is almost never `:last-child` (Giriş with Google card hung a line under its only setting).
- Cards never draw their own dividers: eight `<div class="col-12"><hr/></div>` cells were removed; a hand-placed `<hr>` adds a second line and puts a gutter + its own margin in between, breaking the rhythm. Group heading (`<h6>`) stays (a name, not a divider). Never put `<hr>` in a new card.

## How are name, control and description placed?
- Name and control share one row; the description drops below both. Grid: `"pgname pgctrl" / "pgnote pgnote"`.
- The control once spanned both rows ("centre it between name and description"), and that is what made the card look crooked: an item exceeding one row distributes its height over the tracks it covers, so even an empty description row takes a share of the control's height and lifts the name off centre (measured in eight panes: plain fields 7px, input groups 19px, icon picker 28px, varying with the control). Sharing one row makes the row as tall as the taller of the two and both centre inside it.

## What gives a card its vertical rhythm?
- One rhythm: `--bs-gutter-y: 0` + the same `padding-bottom` on every cell, taken back from the row's bottom with `margin-bottom`.
- Bootstrap's gutter model gives each cell a top margin and pulls the row up by the same amount; a transformed cell dropping the gutter left that negative half unpaid and the card's 1rem body padding vanished (0px between title and first setting in EVERY card of eight panes). The mix also gave one card two different gaps depending on the next cell's type.

Related: docs/memory/ui/settings-card-layout-pitfalls.md
