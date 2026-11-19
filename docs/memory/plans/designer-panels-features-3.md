---
name: designer-panels-features-3
description: Plan designer-panels, features D29 to D44: the Overview tree for long pages: sections, folded chains, hints, search, the page map, wide drop targets, long drags, moving without dragging
---

## How is a long page read? (`overview-1-long-page.jpg`)
- (D29) The head: "Show the selection", "Collapse all" and the Notes tab as today; under it a search box, then the selection's path, sticky and clickable.
- (D30) The page's top-level parts are section rows with the count of what they hold. On opening, only the section of the selection is open (13 to 20 rows where 502 stood).
- (D31) A chain of wrappers with one child each is one line, "4 levels hidden: … › …"; a click opens the chain.
- (D32) A name that repeats gets a hint after it: the text, the picture's file name or the link's target ("Link · My orders").
- (D33) Row 28 px, indent 12 px, at most six indents and then a depth badge; the label never gets less than 150 px.
- (D34) The search ("Search the tree…", Ctrl+F inside the Overview) looks at the name, the text, the class and the id with Turkish letters folded, and lists the results with their path; Enter selects and shows the row.
- (D35) A page map at the tree's right edge: the sections by their size, the range in view and the selection; a click goes there.
- (D36) The Overview still follows the selection from every panel (`designer/designer-overview-follows-selection.md`) and a multiple selection is drawn as today.

## How is an element dragged? (`overview-2-drag-move.jpg`)
- (D37) The gap between two rows is one wide target (7 px each way): a 2 px line with an 8 px dot. The middle 14 px of a row means "inside" and frames the row.
- (D38) The pointer's horizontal place picks the level: 12 px is one level, only among parents that may take the element (`canDrop()`).
- (D39) A label follows the pointer and says the target in words ("Into Account menu, 2nd place"). A row that cannot take the drop is dimmed, the cursor says "not allowed", the label says why.
- (D40) Long ways: when a drag starts the tree folds to its sections; a closed branch opens after 600 ms under the pointer and stays open after the drop, the others close back; the top and bottom 48 px scroll the tree, from 6 to 24 px a frame; Esc or the drop gives the tree back as it was.

## How is an element moved without dragging?
- (D41) Row buttons: up, down, "Move…".
- (D42) The "Move…" window: the target is searched by name and listed with its path; Before, Into, After; "Move". The same window opens from the canvas toolbar and with M.
- (D43) Keys on a row: Up and Down walk the rows, Left and Right close and open; Alt+Up and Alt+Down among siblings, Alt+Left and Alt+Right one level out and in; Ctrl+X then Ctrl+V.
- (D44) Every move, dragged or not, is one undo step; the moved element stays selected and in view; a move `canDrop()` refuses does nothing and says why.
- On a 390 px phone the "Move…" window is a sheet from the bottom: `designer-panels-measures-6.md` (`phone-pages-move.jpg`).
