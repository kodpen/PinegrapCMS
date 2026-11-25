---
name: designer-my-library-drawing
description: The drawing of My library (plans/designer-my-library.md, ML2 to ML4): the sheet, its eight boards, what each step builds from it and what the sheet settles
---

## Where is the drawing?
- `designer/designer-my-library-drawing/`: `my-library.html` with a `-dark.jpg` and a `-light.jpg` picture; `drawing.css` sits on top of `ui/look-sheet/sheet.css`. The designer's own parts are dark in both pictures.
- The sample is the library of the site "Studio Calm" with six items in the groups "Heroes", "Pricing", "Footers" and one with no group. Names and counts are examples; the labels are the steps'.

## Which board belongs to which step?
- ML2: L1 the row "Save to my library" in the element's menu and in the command palette; L2 the save window; L3 "My library" as the first group of the Blocks list (with items, the hover card, search, empty); L4 the lines after a copy is added.
- ML3: L5 the "Manage library" window, nothing chosen and three chosen; L6 rename, "Move to group", order by dragging, the delete question.
- ML4: L7 the export line, the import being checked, its report, a refused file and the reasons' words.
- L8: the save window (ML2) and "Manage library" (ML3) at phone width.

## What does the drawing add to the designer's bars and menus?
- No button anywhere (`designer-light-and-simple.md`). One row in the three-dot menu of a section, row, card or group; three command palette rows ("Save to my library", "Manage library", "Import to my library"); "Manage library" as the last row of the library's group.
- The roles that may save, manage, export and import see those rows; a content role sees the group and its items only (`designer-library-item.md`).

## What does the sheet settle that the plan left open?
- A name the library already has is saved beside it, on save and on import: both are kept, one line says so, nothing is replaced.
- An empty group field saves the item above the groups; a group that loses its last item is gone. The library's order is the order of the Blocks list.
- Nothing is said after a copy is added unless a class was renamed or a widget lost what it was bound to. Added rules and variables are not announced.
- A widget with a cleared binding keeps an amber mark on the canvas and in Overview, and a line in its Options panel, until a form, collection or group is chosen.
- An imported file with one failing item is refused whole; the reason names the item. The export file is named after the site, with "-library".
- Only a delete asks. There is no bin.

## Which states are drawn, and which have no board?
- Busy: the import's check (L7). Filled: L3, L5. Empty: L3. Error and refused: L7. Narrow: L8. A second value: L2 (a name in use), L4.
- No board: a design without Bootstrap has no Blocks list, so "My library" is the first group of the Components list there, drawn the same way.
- No board: an item whose tree uses Bootstrap classes, in a design without Bootstrap, says in its hover card "Made with Bootstrap, which this design does not load." It can still be added.
- No board: a search with no match says "No item matches" under the field of "Manage library". A save or an import that fails on the server says the server's reason in the window and keeps the window open.
- The empty group's line is the feature's first-use line; no other first-use line is shown.

## Which parts are drawn from what exists, and which are new?
- As they are today: the palette's tabs, the Components / Blocks switch, a group's head and a row (`designer-palette-tabs-and-blocks.md`), the hover card, the element's menu, the command palette, the designer's window with its foot, its confirm.
- New: the group names inside "My library", an item's row in "Manage library" (grip, checkbox, drawing, name, count), the foot that changes with the choice, the import report's rows, the amber mark and tag of a widget with a cleared binding.
