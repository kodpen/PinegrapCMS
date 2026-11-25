---
name: designer-component-overrides-drawing-variants
description: Boards C6 and C7 of the component overrides drawing (step CO5): the "Variants" list in the component's settings, adding, editing and deleting a variant, the "Variant" row of a place
---

## Where are a component's variants listed?
- In the "Shared Component" section, under the small head "Variants", after the "Can change per place" list and before "Detach": "Default" first, then the variants in the order they were added, each with the count of what it changes ("6 changes") and a three-dot button. The ticked row is the one the canvas shows. With no variant the list holds "Default" alone.
- Under the list the quiet small button "Add a variant"; the command palette has the row "Add a variant".

## How is one added, renamed or deleted?
- "Add a variant" puts a name field ("Name of the variant") in the list: Enter adds it and starts editing it, Escape drops it. A taken or empty name keeps the field, with "This name is taken." under it.
- A variant's menu: "Rename", "Duplicate", "Delete". "Default" has no menu.
- "Delete" asks nothing: a place holds only the variant's name and shows the default when the name is gone. The answer is a Result line: "The variant “Dark” was deleted. Places that used it show the default." with "Undo".

## How is a variant edited?
- Choosing its row shows the component in that variant and starts editing the component (today's notice, once). A line across the top of the canvas says "Editing the variant “Dark” of “Promo card”. Classes and options you change are kept in it." with the quiet button "Done"; "Done" or Escape returns to "Default".
- A variant keeps classes and options of the component's nodes, and nothing else. A row the variant changes carries the dot; the tip of its undo arrow is "Reset to the component".
- Adding, removing or moving an element while a variant is edited is refused with an amber line: "A variant keeps classes and options only. Choose “Done” to add, remove or move an element." Texts and pictures are the component's and stay so.
- Variants are saved with the component, by "Save"; nothing is sent by itself.
- Keyboard: Up and Down move in the list, Enter chooses a row, F2 renames, Delete deletes the focused variant (never "Default").

## How does a place pick a variant?
- When the component has a variant, "This place" starts with the row "Variant": a plain list of "Default" and the component's variants. It is not shown for a component with no variant.
- A place on a variant other than "Default" carries the dot on the row and counts as a change; the undo arrow returns it to "Default".
- The variant changes how the component looks; the place's own texts, pictures and links are drawn in every variant.
- A variant that is gone: the place shows the default and the section says so once, in an amber line, "The variant “Dark” no longer exists. This place shows the default.", until another variant is picked or the row is reset.
- The row is not in a content editor's panel: a variant is a matter of the design.
- The command palette has one row per variant for the selected place: "Variant: Default", "Variant: Dark", "Variant: Compact".
