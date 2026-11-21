---
name: legacy-editable
description: Plan (owner 2026-11-21): a classic page arrives in the visual designer as editable elements, not name boxes: regions as HTML, a true canvas for code, layouts by a piece map; LE1 to LE12
---

## What did the owner say?
- Owner (2026-11-21, the architect's window): "legacy sayfalar tasarımcıda açılıyor ancak sadece ortak bolge adları tasarımcı bolgesi adları vs. düzenleme mümkün değil ... geçiş olacaksa düzgün olmalı, şu anda geçiş başarısız durumda", and "ortak bolge tasarımcı bolgesi ve page region bolge kodları html olarak aktarılsa kurtarır ama layouts hep sıkıntı olacak php kodu içeriyor".

## What was found? (architect, 2026-11-21)
- "Open in the visual designer" converts no region: each conversion is a button on its own node (`designer/classic-style-to-design.md`), so the canvas is boxes with names.
- The bulk move converts common regions, page regions, the frame's designer regions and four widget kinds (`designer/legacy-to-designer-run.md`). It leaves the region nodes between two page regions, the placeholders inside a converted region, Login, Cart and Comments Block, and every design moved before the frame split.
- A region that stays draws a label box; only a System region with a custom layout shows what the site draws (`designer/legacy-region-preview.md`).
- 88 of the starting site's 180 pages draw their content by a custom layout file, each different from its template (`designer/legacy-layouts-census.md`). One becomes a widget one page at a time by a button; only a custom form's layout becomes design elements, the other 24 types get the stock widget and lose the theme's markup.

## What is the goal, and how is it measured?
- A classic page that arrives in the designer shows no name box for anything stored as HTML: its content, common and designer regions, menus and the small widgets are elements on the canvas.
- What stays code is drawn as the site draws it, with one line saying why and the one action that opens it.
- The measure, on the move screen and in each step's PR: "N of M pages fully editable" for both starting sites, and the reasons of the rest.

## Which parts?
- Regions as elements and one door into the designer: `legacy-editable-regions.md`.
- Layouts: `legacy-editable-layouts.md`. Screens in words: `legacy-editable-screens.md`. Steps and order: `legacy-editable-steps.md`.

## Which rules stay?
- Nothing classic is removed and nothing converts by itself: a designer (role 0-1) starts it under a dry run (#6006, `designer/legacy-to-designer-report.md`). No schema.
- A converted common region's component is its one source, on classic pages too; its inline editing on the site ends (owner 2026-10-27, `designer/classic-regions-in-designer.md`). The dry run says so before the designer presses Convert.
- A layout becomes real design elements, never a PHP area; a piece with no element is dropped and reported (owner 2026-10-27, `designer/layout-form-converter.md`).
- The nine page types with no widget stay System regions (`designer/canvas-closed-blocks-regions.md`). Who may write PHP does not widen. Back to classic keeps working (`designer/legacy-to-classic.md`).
- Every step that changes what a visitor gets is seen in a sandbox, classic and moved page side by side, wide and phone.

## Is anything asked of the owner?
- No: his words above and the decisions named cover it. A step that would drop a piece a visitor sees without the designer's confirm, or retire the Mobile Switch or PDF region, stops and asks the architect.

Related: docs/memory/designer/legacy-overview.md, docs/memory/designer/classic-regions-in-designer.md
