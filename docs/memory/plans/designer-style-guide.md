---
name: designer-style-guide
description: Plan (owner 2026-11-24, level 2): a style guide sheet drawn from a design's variables, text styles, controls and shared components, always current, printable; steps SG1 to SG4
priority: 6
---

## What is asked?
- Owner (2026-11-24): ten more features, in levels, the designer kept simple and quick (the decision part 108; `designer/designer-light-and-simple.md`). This one: what a design is made of on one sheet, for the designer, the team and the customer an agency hands the site to.

## What is there today?
- A design's colours and type live in the Theme pane; with `designer/design-variables-overview.md` they become named variables. Nothing shows them together with the buttons, forms and components built on them.

## What is built?
- "Style guide" as a row of the Preview menu (`designer/designer-preview-menu.md`): a sheet drawn for the design that is open, in a frame with the design's own stylesheets. Nothing is saved; it is always what the design is now.
- Its blocks, in this order: Colours (name, value, and for each text on surface pair whether it passes contrast), Type (every heading and text style at its size, with its name), Spacing (the scale as bars), Shape (corners and shadows), Controls (buttons, links, form fields in their rest, hover, focus and off states), Components (the design's shared components by name, each drawn once).
- A click on a colour, type or spacing name opens that variable in the Variables tab. A component's name opens the component.
- "Print or save as PDF" prints it as one clean sheet with the site's name and the day; light or dark follows the design's colour mode, with a switch on the sheet.
- A design with no variables yet shows the Theme pane's values under the same blocks and one line offering the start questions.

## What must it keep?
- It reads, never writes: no page, no file, no setting is made by opening it.
- Drawn only when opened, as a part; a design with a hundred components draws the first screen at once and the rest as it is scrolled to.
- Sharing it by a link is not in this plan: a person prints it. A link would change what a share link shows and is asked first.

## Which steps? (S under 400, M about 1,000 product lines; tests red first)
- SG1 (M) The sheet's model, pure: variables, the stylesheet's text styles and a design's components in, the blocks out; the contrast pairs. Done: PR #11540 (`designer/design-style-guide-model.md`).
- SG2 (M) after SG1 and DV2: the sheet drawn and opened from the Preview menu, as boards G1 to G5 and G7 of `designer/designer-style-guide-drawing.md` and `designer-style-guide-blocks-drawing.md` draw it. Done: PR #11656 (`designer/designer-style-guide-sheet.md`).
- SG3 (S) after SG2: the print sheet, the click through to a variable or component, as boards G6 and G7 draw them. The PR body carries a table of the boards: what was built for each, what differs and why. Done: PR #11678 (`designer/designer-style-guide-print-links.md`).
- SG4 (S) Close-out: memory in `designer/`, this file deleted, the `[NEW]` line named.

## What does every step keep?
- No table or column. The drawing decides where the two differ (`workflow/screen-follows-drawing.md`). Claim as `[plan:designer-style-guide] <step> — <topic>`.
