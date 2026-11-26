---
name: designer-start-import-drawing
description: The drawing of the Figma and Canva starts (`designer-start-import.md`, built in ST3, ST5 and ST6): the three sheets, where each part is described, and which board belongs to which step
---

## Where is the drawing?
- `designer/designer-start-import-drawing/`: `connections.html` (part A), `figma.html` (part B) and `canva.html` (part C), each with a `-dark.jpg` and a `-light.jpg` picture; the sheets share `drawing.css` on top of `ui/look-sheet/sheet.css`.
- Connections and the New design window are panel screens: both pictures follow the theme.
- Sample names, files, colours and counts are examples; the labels are the steps'. Where the plan and a sheet differ the sheet wins (`workflow/architect-draws-new-part.md`).
- Part A: `designer-start-import-drawing-connections.md`. Part B: `designer-start-import-drawing-figma.md` (the link and the frame list), `designer-start-import-drawing-figma-import.md` (the variables card, the running import, the report) and `designer-start-import-drawing-home-line.md` (the line on the design's card of the designs home). Part C: `designer-start-import-drawing-canva.md`.

## Which board belongs to which step?
- ST3, part A: A1 the Figma page not connected, A2 connected, A3 the token no longer accepted, A4 a token Figma refuses, A5 the "Disconnect" question, A6 narrow.
- ST3, part B: B1 the link field, B2 Figma not connected, B3 the token no longer accepted, B4 busy, B5 the frame list, B6 a link that cannot be read, B10 narrow.
- ST5a (pictures, shared components, variables), part B: B7 the variables found in the file (with DV4).
- ST5b (the run, the home card's line, the report), part B: B8 the import running, B9 the report, B11 the design's card on the designs home, B12 the dialog "Stop" opens.
- ST6, part C: C1 the upload, C2 the pages, C3 busy and refused files, C4 the colours found (with DV4), C5 narrow.
- ST4, the converter, draws nothing: it has no screen of its own.

## Which parts are drawn from a Design example, and which are new?
- From the Design examples screen: the notice line is "Result line", only its tile's tone changing (amber for something missing) and with no rule of its own; a frame tile and a Canva page tile are the "Choice cards" card with the picture inside it; the report's counts are the pills of "Counts"; the question "Stop" opens is "Confirm with its effects".
- More than one frame is chosen, so the card's head carries the Fields example's tick box in place of the round mark; ST3 adds that card to the "Choice cards" example in its own PR.
- Not on that screen yet: the drop place (C1, C2) and the progress bar (B8, C3). One step after this drawing makes both Design examples from the sheets, before ST5b and ST6 use them; the sketch photo and the document plan use the drop place too.
