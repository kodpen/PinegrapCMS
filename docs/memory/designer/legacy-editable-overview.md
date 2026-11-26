---
name: legacy-editable-overview
description: Start file for a classic page made editable in the visual designer (LE1-LE12, closed): the owner's goal, the rules kept, which file holds each part, the starting sites' last figures
---

## What is it?
- Owner (2026-11-21): a classic page moved to the visual designer showed only boxes with region names and nothing could be edited; a move that is offered must work, so common, designer and page regions arrive as HTML elements and layouts, which hold PHP, need their own way.
- Built: one door through the move screen, a run making region nodes and page types components and widgets, layout files converted, every part left drawn as the site draws it.

## Which rules bind it?
- Nothing classic is removed and nothing converts by itself: a designer (role 0-1) starts the run after the dry run (`legacy-to-designer-report.md`). No schema. Back to classic keeps working (`legacy-to-classic.md`).
- A converted common region's component is its one source, on classic pages too; the dry run says so before Convert (owner 2026-10-27, `classic-regions-in-designer.md`).
- A layout becomes design elements, never a PHP area; a piece with no element is dropped and reported (owner 2026-10-27, `layout-form-converter.md`). A run converts a file page only when nothing drops; otherwise the designer confirms in the Region panel.
- The nine page types with no widget stay System regions (`canvas-closed-blocks-regions.md`). Who may write PHP does not widen.
- Ask the architect before a change that drops a piece a visitor sees without the designer's confirm, a setting a widget cannot hold on more than a few pages, a column or table, or retiring the Mobile Switch or PDF region.

## Which file holds what?
- The measure, "Pages fully editable" and the census: `legacy-editable-measure.md`. One door and the dry run's three groups: `legacy-one-door.md`.
- The run's `page_parts` step (region nodes, Login, Cart, Comments Block): `legacy-page-parts.md`. Its `types` step and starters: `legacy-types-step.md`, `legacy-run-starters.md`; held settings: `legacy-stock-types.md`.
- The canvas's band over every region left and the Region panel's lines: `legacy-region-preview.md`. "Designs moved earlier": `legacy-earlier-designs.md`.
- A layout file into its widget, one file a type family: `layout-type-overview.md`. The offer beside a layout: `layout-widget-offer.md`.

## What stays code?
- Dynamic regions (PHP), Mobile Switch and PDF; the nine types with no widget (order receipt among them); a custom form page in a run (it converts from the Layout tab); a file page that drops pieces, until the designer converts it; a page holding a setting its widget cannot keep.

## What are the last figures?
- File pages over both starting sites that convert in a run with nothing dropped, from each step's PR: order form 20 (#11335), photo gallery 12 (#11362), catalog 10 (#11393), catalog detail 6 (#11436), shopping cart 3 (#11466), express order 5 (#11486), billing, shipping address and shipping method 8 (#11628), order preview 4 (#11667), login, password pages, profile and e-mail preferences 12 (#11710), address book 2 (#11748), search results 2 (#11764): 84.
- Form list view (52 pages): those with browse and search off and no header or footer, on the English site 437, 439, 508 and 1074; the rest stay (search or browse drops "Found N results", a header is held) (#11308).
- Stay System regions in a run: registration entrance 126 on both sites (the login form drops, #11730), my account 971 on both (14 pieces, #11748), custom form 26, order receipt 6. No starting page has a view order, form item view or membership entrance file. Stock-template pages convert unless a setting is held.
- The one sandbox run (#11227): Auto Dialogs' set went from 0 / 0 to 0 / 2 fully editable before the types step existed. "N of M fully editable" after the whole run was never taken on a running starting site: no step reached one.

Related: docs/memory/designer/legacy-overview.md
