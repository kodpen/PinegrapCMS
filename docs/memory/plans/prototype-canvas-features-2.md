---
name: prototype-canvas-features-2
description: Plan prototype-canvas, features F15 to F25: the list of the selected page's links, the broken links found, drawing and changing an arrow, the bar
---

## What does the list at the right show?
- (F15) A panel right of the canvas for the selected frame. Its name; "Outgoing · n": one row per link, the link's label and "→ target" (a page's name or the chip's text), a broken one red with the reason; "Incoming · n": "source · label", a shared part's row saying "shared part"; last "Shared parts on this page", each a button that selects that frame. Nothing selected: one line saying to pick a page.
- (F16) A click on a row selects that link: its mark and arrow are emphasised and the canvas brings its other end into view. A click on a mark does the same and scrolls its row into view.
- (F17) An outgoing row ends with two buttons: "Change target" opens today's target menu (`_sdProtoMenuOpen()`); "Open in the editor" does what today's row click does (closes the view, opens the tab, selects the node).
- (F18) Under 1,100 px of width the panel is closed behind a button in the bar; open or closed is kept per browser.

## Which problems are found?
- (F19) The bar says "N links" and, when there are any, "N broken" in red. A click on it opens a list of every broken link of the design (frame, label, why); a row selects the frame and the link.
- (F20) A frame that holds a broken link carries a red count badge on its head, dimmed or not.

## How is an arrow drawn or changed?
- (F21) Dragging from a mark to a frame or a chip writes the link as today (`_sdProtoConnect()`: the tab opened first, the lock, `_sdMayEdit()`, undo, the shared and live page warnings). While dragging, what may take the arrow lights up; what may not stays as it is.
- (F22) Dragging the head of a drawn arrow to another frame or chip re-points it the same way.
- (F23) Keyboard: Tab reaches the frames, then the selected frame's marks, then the list; Enter selects; L, the menu key or Shift+F10 on a mark or a row opens the target menu; Escape closes the menu, then the view.

## What else is in the bar?
- (F24) "Play" opens the prototype viewer at the selected page when `_pgPrototypeUrl()` has an address for it, else at its first page; not shown when it has none.
- (F25) Zoom, Fit, "Read the links again" and Close as today.

## Where is what the owner answered about links finding their way?
- In `plans/prototype-canvas-features-3.md` (F26 to F34): he chose all three meanings on 2026-11-16, so arrows route themselves (F14), empty links are offered a target and further problems are found.
