---
name: designer-position-presets
description: Options panel Size and display: Position's "Fill the parent" and "Centre" presets of the four offsets, Z Index as a typed number with Bootstrap's layers by name (DO7, #11566)
---

## Where do the presets sit?
- Style › Size and display › Position: the arrow that opens Top, Right, Bottom, Left starts with "Preset" and two segments, "Fill the parent" and "Centre". No new button in a bar or a head.
- The segment the element matches stands on (`_sdPosPresetOf()`): all four offsets 0 (or 0px) without `translate-middle` is Fill; Top and Left 50%, Right and Bottom empty with `translate-middle` is Centre.

## What does a preset write?
- `_sdPosPreset(name, cls)` (pure), applied by `_sdPosPresetApply()` from `_sdControlsInit()`'s click, one undo step for one element or several (`_sdMcCommit()`).
- Fill: `top`, `right`, `bottom`, `left` props `'0'`, `translate-middle` dropped. Centre: `top` and `left` `'50%'`, `right` and `bottom` cleared, Bootstrap's `translate-middle` class added (Bootstrap 5.3's own centring of a positioned element).
- Offsets act on a positioned element only: an element with no Position class or `position-static` gets `position-absolute`; Relative, Absolute, Fixed and Sticky stay.
- The offsets are written as before (`getNodeExtraStyle()` and `_pg_node_sizing_decls()`); no new writer.

## How is Z Index set?
- A text field (`data-prop="zIndex"`, `data-sd-len="z"`) takes a whole number or `auto` (`_sdZValid()`); anything else is not written and says "Use a whole number or auto." The writers already wrote only those.
- Beside it a "Layer" list names Bootstrap's layers: Dropdown 1000, Sticky 1020, Fixed 1030, Modal 1055 (`_SD_Z_LAYERS`). Bootstrap 5.3 keeps them in Sass (`$zindex-*`), not in a `--bs-*` variable, so the list carries the numbers.
- A layer picked fills the field and fires its `input` (one element) and `change` (several); the list shows the layer the value names.
- Before DO7 Z Index was a fixed list up to 1080; a saved value such as 50 or 1080 shows in the field as typed.

## How is it found and tested?
- The Options search finds the rows by "Position" and "Z Index" / `z-index`; the preset words sit in the row's box and are not indexed.
- `tests/designer_position_presets_test.php` (pure block "Position presets and layers" under Node).
