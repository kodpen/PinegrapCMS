---
name: image-editor-rail
description: The image editor rail's group items Photo (Fotoğraf) and Vector (Vektör): which tools they hold, the mode switch, the mode reopened, letters and hints, M.railItems()
module: image_editor
---

## Which rail items hold several tools?
- `M.RAIL_GROUPS` in `pg_image_editor_model.js`: `photo` (Crop, Adjust, Redact, Select area, Frame) and `vectors` (Pen, Scissors, Shape builder, Warp, Free deformation), owner 2026-11-03/04 (`workflow/decisions-made-product-27.md`). A new mode joins a group by adding its tool name to the group's list.
- `M.railItems(list)` turns a call's tools into rail items `{ id, modes }`: a group stands where its first tool stands in the profile's list, its modes in the group's order; a group with one tool of the list is that tool's own item (the canvas profile's Frame). Photo rail: Select, Photo, Canvas, Draw, Text, Sticker, Vector, Animate, Layers at the foot; it keeps its labels at 1280×800. The phone's bar is the same list.
- The mini bar (a caller's `tools`, annotate) keeps a button per tool.

## How does a group behave?
- `this.tool` stays the mode's own tool, so each mode keeps its panel, hint, letter (`M.toolKey()`) and pointer handling; only the rail button is the group's (`toolButtons[groupId]`, pressed in each of its modes).
- A press opens the mode shown last (`this.railLast`, recorded in `render()` so a letter or leaving Crop counts), Crop the first time (`M.railOpen()`); pressing it again while one of its modes shows toggles the sheet as any tool does.
- `modeSwitch()` draws the switch (`.pgie-seg.pgie-modes`, role group, label `railMode`) at the top of the panel body; a mode's label is `mode_<tool>` when defined (`mode_vector` is Pen), else `tool_<tool>`. A switch goes through `pickTool()`, which keeps the selection.
- The switch wraps (flex, each button at least its label's width): at 1280×800 the five photo modes in equal columns overflowed the 304 px panel ("Alan seç" on two lines, "Çerçeve" past its button); now four sit on the first row and Frame on the second, and the phone's wider sheet keeps one row. A longer language wraps the same way.

## Where is it tested?
- `tests/image_editor_rail_test.php`: the profiles' items, the modes, the reopened mode, the letters. The DOM part was checked in a headless browser at 1280×800 and 390×780 (#6819).
