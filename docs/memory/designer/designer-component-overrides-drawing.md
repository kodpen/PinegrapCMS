---
name: designer-component-overrides-drawing
description: The drawing of a component's open fields, "This place" and variants (component-overrides-overview.md, CO3 to CO5): the sheet, its seven boards and what each step builds from it
---

## Where is the drawing?
- `designer/designer-component-overrides-drawing/`: `component-overrides.html` with a `-dark.jpg` and a `-light.jpg` picture; `drawing.css` sits on top of `ui/look-sheet/sheet.css`.
- The designer's own panels, menus and canvas frame are dark in both pictures; the page on the canvas is a stand-in in the site's light theme.
- The sample is the component "Promo card" (Picture, Sale badge, Title, Text, Button) with the open fields Picture, Sale badge, Title, Button text, Button link, placed on "Home". Names, values and counts are examples; the labels are the steps'.

## Which board belongs to which step?
- CO3, part "In the component": C1 a field is opened from the element's menu (the list, the first-use line, the mark on the row, a field bound to data, the palette row); C2 Overview's mark and the list in the "Shared Component" section (a row's menu, the line after "Close the field", no field yet). Details: `designer-component-overrides-drawing-fields.md`.
- CO4, part "Where it is placed": C3 "This place" in the Options panel (nothing changed, three changed, closed, a refused value, the palette row); C4 on the canvas (outlines, typing in place, "Edit the component", an element that is not open, a component with no open field); C5 a content editor, a page they may not change, the panel under 300 px. Details: `designer-component-overrides-drawing-this-place.md`.
- CO5, part "Variants": C6 variants in the component's settings (the list, adding, a taken name, a variant's menu, editing one, refused, after "Delete"); C7 a place picks a variant (the row, the list, the dot, two places with two variants, a variant that is gone, a content editor's panel, the palette rows). Details: `designer-component-overrides-drawing-variants.md`.

## What does the drawing add to the designer's bars?
- Nothing. No button is added to the page bar, the canvas bar, a panel's head or a section's head (`designer-light-and-simple.md`).
- The feature enters by one row of the element's three-dot menu ("Can change per place"), one section of the Options panel ("This place") and rows of the command palette: "Can change per place", "Edit the component", "Add a variant", "Variant: name".
- The only buttons are quiet and small and sit inside the panel's body: "Edit the component" in the foot of "This place", "Add a variant" under the variant list, "Done" on the line over the canvas, "Undo" and "Got it" on a line.

## Which states have no board, and why?
- Busy: none. Opening a field, a place's value, a variant: all are saved with the component or the page by "Save"; nothing calls the server by itself.
- A component with no open field and no variant: the panel, the canvas and the menu outside a component are as today (shown once in C4).
- No dialog: closing a field and deleting a variant are answered by a Result line with "Undo".

## Which parts are drawn from a Design example, and which are new?
- From the Design examples: the Result line (after "Close the field" and "Delete") and the row menu.
- The designer's own parts, as they are today: the Options panel (336 px, identity row, tabs, search, 92 px label column, 32 px rows, the 14 px dot place with the 7 px dot that becomes the undo arrow), the "Shared Component" section, Overview's rows, the green outline and name tag of a shared component, the toast shown when a shared component is edited.
- New: the mark of an open field (a small green two-arrow sign after a row's label and at an Overview row's right edge), the dashed green outline of an open element in a selected place, the list rows with a grip in the "Shared Component" section, the line over the canvas while a variant is edited.
