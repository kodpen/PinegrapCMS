---
name: designer-home-tidy-drawing
description: Drawing of the New design window and the Page Design Studio home (2026-11-26): which board belongs to which step of designer-home-tidy, and what the sheets fix
---

## Where is it?
- `docs/memory/designer/designer-home-tidy-drawing/`: `new-design.html`, `home.html`, `drawing.css`, and a `-dark.jpg` and `-light.jpg` picture of each sheet. The HTML reads the look's `ui/look-sheet/sheet.css`.
- Drawn by the architect for plan designer-home-tidy (#11905, #11920, #11934). Owner (2026-11-26, agent channel message 14316): "ikonlar anlaşılmayacak karışık ve arayüzü modern değil … kargaşa azalmalı … liste ana sayfası dahil. bunları sen çiz".

## Which board belongs to which step?
- HT1: N1 the window with Blank chosen, N2 Template, N3 ZIP file and Pasted markup, N4 the icons and a row's states.
- HT2: H1 the home with designs, H2 the first visit, H3 a tile's states and the search, H4 the two menus.
- HT3: N5 and H5, both at 390 px.

## What do the sheets fix for the New design window?
- A window of at most 1120 × 720 px, one height whatever the start. Rail 256 px, in three labelled groups: "Start new" (Blank, Template), "From a design tool" (Figma, Canva), "From what you have" (From a document, ZIP file, Pasted markup). Sketch it and Describe it join "Start new" when their plans land.
- A row is one 32 px icon tile, the name and one line that never wraps. The small drawings are gone. Tiles are grey; the chosen row's is the accent.
- Icons: Blank `bi-plus-square`, Template `bi-grid-1x2`, Figma `bi-vector-pen`, Canva `bi-images`, From a document `bi-file-earmark-text`, ZIP file `bi-file-zip`, Pasted markup `bi-code-slash`, Sketch it `bi-pencil`, Describe it `bi-stars`. The same icon stands in the rail, the pane head and the home's first-visit cards.
- The pane head is a 44 px accent tile, a title that says the act ("Start with a blank page") and one sentence of what happens next. The import panes take this head and keep the rest of their own sheets.
- "Built on" is a label over two choice cards, "Bootstrap 5" and "Custom", each with one line and three ticks; "Recommended" is a grey pill on Blank's Bootstrap 5 card only; "This cannot be changed later." is one grey line with a lock.
- Template: a press chooses a card, the foot's filled "Use this template" uses it; no card has a button; "Preview" stays a link. Every start has the same foot.
- Narrow: the starts as one grouped list, then the chosen pane with the link "All starts".

## What do the sheets fix for the home?
- The head is one row: "Designs" with the count and the one sentence, "Find a design" from seven designs, "Shared components and system widgets" (ghost), "New design" (filled).
- The four start cards stand only on a site with no design, as three starting points: Blank, Template, "Bring your own" (opens the window on Figma), over one grey line of three steps.
- The large card: the preview in a browser frame at the left; the name and menu button, the state as a mark and words, the home page line, four facts in one row, one row of page chips then "+n more" (opens in place), today's buttons with "Edit" filled.
- A tile: the preview, the name and menu button, one line of state mark and words, page count and time. An empty design shows "No pages yet" in the picture's place. Four a row from 1400 px, three from 1000 px, two from 560 px, a list below.
- Marks: full circle in the accent for live, empty circle for a draft, half for some pages live, dotted for empty, a clock for a schedule. The words stay those of `pg_designer_list_status_cell()`.
- Menus keep their rows, in groups with dividers: Edit, Open in a new tab, Add page; View on site, Put on or Take off the site; Rename, Turn into a template; Delete in rose. No icons; a row that does not apply is left out.
- The right-click hint line is gone; right click and long press still work.

Related: docs/memory/designer/new-design-window.md, docs/memory/designer/designer-home-screen.md, docs/memory/designer/designer-home-colours.md
