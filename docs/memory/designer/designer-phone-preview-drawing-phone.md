---
name: designer-phone-preview-drawing-phone
description: The phone's view of "Preview on a phone" as drawn (phone-preview.html, boards V1 and V2, step PP3): the thin line, a redraw, the page list, Paused, and the phone's messages
---

## What does the phone show?
- The page at the phone's own width under one thin line, the viewer's whole chrome there: the page's name in bold, when it was last redrawn in grey, and the page list's button at the right end. The prototype viewer's bar of a desktop is not drawn.
- The line is 32 px high, dark on every site whatever the site's theme, and stays on top while the page scrolls. The list button's touch area is 44 px wide and reaches 12 px below the line.
- The grey words: "Updated just now", then "Updated 12 min ago".

## What happens on a save in the designer?
- The page is redrawn in place and keeps the place scrolled to. The line's lower edge is blue for two seconds and the words go back to "Updated just now". Nothing else moves and no message is shown.

## How does the page list work?
- A sheet dropping from the line, as wide as the screen, over the dimmed page: the design's pages in tab order, 44 px rows, the one shown ticked.
- A press draws that page; Back on the phone returns to the one before.
- With a link for anyone, a page for signed-in visitors stands in the list greyed with a lock. Pressed, the body says "This page is for signed-in visitors" and "Ask for a team link to see it on your phone."

## When does the line ask for a press?
- The phone asks only while its tab is seen, less often after ten minutes without a change, and stops after an hour: the grey words turn amber and read "Paused", with "Update" (filled, small) beside them. "Update" or a pull down asks once and starts the asking again.
- Coming back to the tab asks at once, with no press.
- No answer: "No connection. Trying again…" in amber; the page stays as last drawn and the words leave with the next answer.

## What does the phone say when it cannot draw?
- A link ended, run out or unknown: today's one answer, "This link no longer works.", with no line.
- A page removed from the design while it is shown: the line keeps its name, the body says "This page is no longer in the design." and the list opens. No board.
- A team link on a signed-out phone goes to the site's sign-in and lands on the page after it. No board: the sign-in is the site's own.

## Which states have no board?
- A phone turned on its side, and a tablet: the same line, the page at that width.
- The first drawing while the page loads: the browser's own blank page under the line.

Related: docs/memory/designer/designer-phone-preview-drawing.md, docs/memory/designer/designer-prototype-viewer.md
