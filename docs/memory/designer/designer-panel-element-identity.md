---
name: designer-panel-element-identity
description: Designer panel must identify the selected element correctly: propsSemantic() tag groups, HTML defaults, attribute rows, panel refresh, canvas selection.
---

## What must the panel never get wrong?
- The panel states WHAT the selected element is. If wrong, the designer tries to fix the wrong thing and usually breaks it.
- Every supported tag must have its own group. `propsSemantic()` drops a tag into one of the groups; a tag without a group falls to `structural`, and since its tag is not in the list the `<select>` shows the first option. Selecting `<label>` showed "Section" and one click really turned it into `<section>`, losing the form's label. Groups: `structural` / `text` / `interactive` / `list` / `formish` (label, fieldset, legend, output) / `optionish` (option, optgroup).
- Do not redefine HTML defaults in the panel. `isButtonInput` had `''` in its list; an `<input>` with no type is `text` in HTML. Imported markup often has no type and such inputs got the Button panel and lost Name / Value / Required rows.
- An attribute's row must exist on EVERY element carrying that attribute. The "Value" row existed on 8 of 16 input types; a default typed in the Attr panel found no row and the panel read "value empty". A textarea's default is not an attribute but a text node (`props.text`); it needs a row too.
- If two panels write the same place, the other redraws when one changes: `_sdSchedulePropsRefresh()` delays 400 ms and SKIPS if focus is inside `#sd-properties` (else the caret jumps while typing).

## Does canvas selection call `renderCanvas()`?
- No. An input/button click is caught on `mousedown` in the capture phase and runs `renderSelectionOnly()`: flips classes, draws side panels, leaves the browser's current interaction alone.
- The `click` branch deliberately keeps returning early for form controls: redrawing the node there makes Chromium drop its synthetic `change` (the theme selector is fed by it).
