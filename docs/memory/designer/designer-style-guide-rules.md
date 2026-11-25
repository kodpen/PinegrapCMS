---
name: designer-style-guide-rules
description: What binds the designer's Style guide (Preview menu, "Style guide", "Print or save as PDF"): why it exists, what it may never do, which file holds each part; landed in SG1 to SG3
module: design_style_guide
---

## Why is there a Style guide?
- Owner (2026-11-24): one of ten designer features in levels (level 2), kept simple and quick (`workflow/decisions-made-product-108.md`, `designer-light-and-simple.md`); what a design is made of on one sheet, for the designer, the team and the customer an agency hands the site to.
- Before it, colours and type lived in the Theme pane and the Variables tab; nothing showed them with the buttons, forms and components built on them.

## What must it keep?
- It reads, never writes: opening, switching "Light | Dark" or printing makes no page, file or setting. Nothing is stored; the sheet is always the design as it is on the canvas, unsaved changes included.
- Drawn only when opened, as a part (`styleguide`); with a hundred components the first screen is drawn at once and the rest as it is scrolled to.
- One Preview menu row and a command palette row; no bar button, no key. Designers only: opening it to content roles widens a right and is asked first.
- Owner (2026-11-24): sharing it by a link is not built; a person prints it. A link would change what a share link shows and is asked first.
- No table or column.

## Which file holds what?
- The model, pure, with the blocks and contrast pairs (SG1 #11540): `design-style-guide-model.md`.
- The window, the frame, the blocks, dark and no variables, the `designer/style_guide` read (SG2 #11656): `designer-style-guide-sheet.md`.
- The names as links to a variable or component, and the print sheet (SG3 #11678): `designer-style-guide-print-links.md`.
- The drawing, which wins where it and a text differ: `designer-style-guide-drawing.md` (G1, G5, G7) and `designer-style-guide-blocks-drawing.md` (G2 to G4, G6).

## How is a new block or row added?
- The model first: a new block or row comes out of `pg_style_guide_model()` with its test, in the sheet's order; then the part `styleguide` draws it with `pgsg-` classes, never `h2`, so the design's own rules leave the sheet alone. A sample stays `inert`; only a name is a link, and only when it is a variable or component.
