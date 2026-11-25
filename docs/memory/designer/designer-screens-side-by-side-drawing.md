---
name: designer-screens-side-by-side-drawing
description: The drawing of "Side by side" (plans/designer-screens-side-by-side.md, SS2 and SS3): the sheet, its six boards, which step builds which, the measures and the texts it fixes
---

## Where is the drawing?
- `designer/designer-screens-side-by-side-drawing/`: `side-by-side.html` with a `-dark.jpg` and a `-light.jpg` picture; `drawing.css` sits on top of `ui/look-sheet/sheet.css`. The designer's chrome is dark in both pictures; the page is a stand-in for a site's page.
- The sample is the design "Still Water Yoga", page "Home", in a 1440 px window. The side columns and the page bar are placeholders; the labels are the steps'.

## Which board belongs to which step?
- SS2: Y1 the row in the View menu, the first-use line, the command palette's row; Y2 the canvas area with its two mirrors; Y3's head and size list; Y5 the mirrors waiting while a text is typed; Y6 not enough room, an e-mail design, the narrow width.
- SS3: Y3's "Edit at this size" before and after; Y4 the chosen element framed in each mirror and the dashed frame under the pointer.

## What does it add to the bars and menus?
- One switch row, "Side by side" (`bi-layout-three-columns`), in the View menu under a line after today's four; the menu button's badge counts it. The command palette has the same switch as a row. No button in the canvas bar, the page bar or a panel's head.

## Which measures does it fix?
- The area: 12 px around and between. A mirror's box is 200 px wide for a width up to 576 and 280 px above it; the canvas gets the rest, never less than 400 px. Three need a canvas column of 928 px (a 1440 px window with both columns open has 930), an e-mail design 636 px.
- A mirror: a head 28 px high, 6 px, then the frame down to the area's foot; the page at its width scaled to the box, from the top, scrolled by the wheel on its own.
- The head holds two controls: the width (the bar's icon for it and "375 px") that opens the list, and the icon button "Edit at this size" (`bi-arrow-left-right`).

## What does it settle that the plan's words leave open?
- Turning the switch on fits the page to the canvas's new width as "Fit" does; off gives back the zoom from before.
- The list holds the bar's five widths under the bar's names; a width the canvas or the other mirror shows is dimmed and says "on the canvas" or "in the other mirror".
- "Edit at this size" swaps the two widths and nothing else; a canvas on "Full width" hands over 1200.
- The chosen element's frame in a mirror is 2 px with no name and no toolbar; a mirror scrolls only when the element is out of its view and brings it a third of the way down. An element that width hides has no frame. Under the pointer: a dashed frame.
- No mark while a mirror waits. A redraw keeps the scroll and swaps in place. Outline's lines and the grid are not drawn in a mirror.
- With too little room the row is dimmed with "Not enough room here. Close a side panel or use a wider window."; a switch that was on stays on, the mirrors leave and come back. The narrow width shows the row the same way.
- An e-mail design has one mirror (375 beside the canvas at 600); its head has no list.
- Prototype mode hides the mirrors; back in Design they return.

## Which texts are new?
- "Side by side", "Edit at this size", "on the canvas", "in the other mirror", the reason above, and the first-use line "Side by side shows this page on a phone and a tablet while you edit. Click anything there to select it here." with "Got it".
- Said to a screen reader: "Side by side on: phone and tablet.", "Editing at {var:1} px."

## Which keys?
- Tab reaches each head's width, then its button; the frames are no stops. The list is a menu: arrows, Enter, Escape.

Related: docs/memory/designer/designer-canvas-mirror.md, docs/memory/designer/designer-canvas-bar.md, docs/memory/designer/designer-light-and-simple.md
