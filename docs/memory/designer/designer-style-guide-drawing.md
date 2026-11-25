---
name: designer-style-guide-drawing
description: The drawing of the designer's Style guide: the Preview menu row, the window with its head and strip, the Colours block, dark, a design with no variables, a phone's width (boards G1, G5, G7)
---

## Where is the drawing?
- `designer/designer-style-guide-drawing/style-guide.html` on `ui/look-sheet/sheet.css` with its own `drawing.css`; pictures `style-guide-dark.jpg` and `style-guide-light.jpg`. Seven boards, G1 to G7, for `plans/designer-style-guide.md`: G1 to G5 and G7 hold step SG2, G6 and G7 hold SG3.
- The blocks Type, Spacing, Shape, Controls and Components, the click through and the print sheet: `designer-style-guide-blocks-drawing.md`. The rows of every block come from the model (`design-style-guide-model.md`).

## How is it opened? (G1)
- "Style guide" is the last row of the Preview menu, under a line, and a row of the command palette. No button in the page bar, the canvas bar or a panel's head, and no key (`designer-light-and-simple.md`).
- It opens a window over the designer, 1040 px wide at most, 24 px from the window's edges, as high as the window allows. Escape closes it and focus returns to the Preview button.
- Loaded as a part when the row is pressed; until the frame is drawn the window shows its head and grey lines in the blocks' places.

## What is in the window? (G1)
- The head, in the designer's chrome: "Style guide", the design's name, the switch "Light | Dark", "Print or save as PDF", the close button.
- Under it a strip with the six block names in their order: Colours, Type, Spacing, Shape, Controls, Components. A name scrolls the sheet to its block; the block on screen is the marked one. With the keyboard the strip is one Tab stop and the arrows move over it.
- The sheet is a frame with the design's own stylesheets and fonts, so every sample is the real thing. It opens with the design's name, "Style guide" and the day.
- It is drawn from the design as it is on the canvas at that moment, unsaved changes included. Nothing is saved by opening it.

## How is the Colours block drawn? (G1)
- A card per colour: the light value at the left and the dark at the right, the name, both values. The designer's own colours come last, marked "Your own". The cards fill the row at 168 px or more each.
- "Text on the surface": a row per pair the model measures, with the sample, the name, the ratio and a verdict in words with an icon: "Passes" (4.5 and up), "Large text only" (3 to 4.5), "Too weak for text" (under 3). Never colour alone. Two rows side by side from 400 px each.

## What does "Dark" do, and a design with no variables? (G5)
- "Light | Dark" opens on the mode the canvas shows and changes the sheet only, never the canvas or the design. The pairs are measured for the mode shown.
- A design without a dark mode has no switch and one value per colour.
- A design with no variables shows the same six blocks filled from the Theme pane's values, names not underlined, and one line under the strip: "This design has no variables yet: these are its Theme's values. Four questions turn them into variables you can name and reuse." with "Answer the questions", which closes the sheet and opens the start questions (`designer-variables-drawing-questions.md`).
- That line is not a first-use tip: it stays while the design has no variables and has no "Got it".

## How does it look at a phone's width? (G7)
- Under 768 px the window fills the screen. The head keeps "Style guide", the switch, "Print" and the close button; the design's name is left out. The strip scrolls sideways and keeps the marked block in view.
- The sheet is one column: colour cards two in a row, pairs and components one, a type sample cut at the sheet's width with its name under it, a control's four states two by two under its name.
