---
name: designer-style-guide-blocks-drawing
description: The drawing of the Style guide's blocks Type, Spacing, Shape, Controls and Components, a name's click through to its variable or component, and the print sheet (boards G2 to G4, G6)
---

## Where is the drawing?
- `designer/designer-style-guide-drawing/style-guide.html`, boards G2, G3, G4 (step SG2) and G6 (step SG3). The window, the Colours block, dark, no variables and the phone's width: `designer-style-guide-drawing.md`.

## How are Type and Spacing drawn? (G2)
- Type: the heading font and the text font first, then a row per style: a sample sentence in that style at its real size, and at the right its name, the size as the stylesheet writes it, the size in px and the weight. A sample too long for the row is cut with an ellipsis, never wrapped.
- The rows follow the model's order. A built-in style the stylesheet changes carries the note "changed in the stylesheet", an own class "your own class".
- "Scale": "Aa" at each step of the type scale with the step's name and px, on one base line.
- Spacing: a bar per size at three times its length, the name at the left, the px at the right; the designer's own sizes last, in the accent colour.
- Sample sentences are the designer's translated texts, the same for every design; the design's own words are not read.

## How are Shape and Controls drawn? (G3)
- Shape: a tile per corner size, the line width and the shadow, each drawn with the value it names; the shadow by its option's label.
- Controls: a row per control, a column per state: Rest, Hover, Focus, Off. The design's own stylesheet draws each state with the state forced, so a missing focus ring shows as missing.
- A design that styles no outline button still shows that row: it is what a visitor would get.
- The samples take no click and no Tab stop; only the underlined names do.
- Under 720 px of sheet the four states stand two by two under the control's name.

## How is Components drawn? (G4)
- A card per shared component, two in a row, sorted by name: the puzzle icon, the name, "on 14 pages" or "not placed", and under it the component drawn with the design's stylesheets at the card's width. System widgets are not listed.
- A tall component is cut at 360 px with a fade and "Show all of it", which opens it to its full height in place.
- The first screen is drawn at once; a card further down shows three grey lines until it comes within one screen of the view.
- A component that cannot be drawn says "This component could not be drawn here. Its name opens it."
- A design with no shared component shows the block with "This design has no shared component yet."

## Where does a name lead? (G6)
- A name that is a variable is underlined. A click on a colour, type or spacing name closes the sheet and opens Settings, Theme, "Variables" with that variable's fold open and its row marked; the tip under the pointer says "Open in Variables".
- A component's name closes the sheet and opens that component for editing, as its row of the palette's "Shared" tab does; its tip is "Open the component".
- Names are links: Tab stops in the sheet's order, Enter opens. A person who may not change the design gets plain names.

## How is it printed? (G6)
- "Print or save as PDF" opens the browser's print window on the sheet alone: no head, no strip, every block at its full height, every component drawn first. While they are being drawn the button reads "Getting it ready…".
- Every page carries the design's name, "Style guide" and the day at the top, the site's address and "1 of 3" at the foot.
- A block starts on a new page only when less than a third of it fits; a row, a card and a control's row are never split.
- Colours are printed as colours, in the mode the sheet shows; a dark sheet prints dark only when "Dark" is chosen. The names' underlines are left out on paper.
