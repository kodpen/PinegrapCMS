---
name: main-menu-icon-rows
description: Which drawing each main menu row takes (menu_icon name, the key in ui/main-menu-icons-drawing/icons.js), its idea and its hover move, by category
module: output
---

## How is this read?
- `menu_icon` name of `output_menu()`, then the key of `window.ICONS` in `ui/main-menu-icons-drawing/icons.js`, the idea, the move (the `data-m` parts). The SVG bodies in `pg_menu_icon()` and the keyframes in `backend.src.css` were copied from that file (#11202, #11233); the drawing stays the reference for a redraw.

## The untitled block (blue)
- `dashboard`, key `panel`: the tiles of a dashboard; they pop in one after another. No clock.
- `flows`, key `flows`: two boxes and the elbow connector between them; hover lights the second box; the live dot runs the connector.
- `workspace`, key `workspace`: the channel mark "#"; its two bars are drawn left to right.
- `notes`, key `notes`: a sticky note; its corner lifts.
- `mail`, key `inbox`: an inbox tray; the arrow drops into it.

## Content (teal)
- `pages`, key `pages`: a browser window; its lines are written.
- `folders`, key `folder`: a folder with a paper; the paper rises.
- `calendars`, key `calendar`: a calendar leaf with today's day; the leaf flips.
- `forms`, key `forms`: a label, a field and a submit pill; the field is written, the pill lights. My reviews (slot 28) shares it.

## Design (rose)
- `design`, key `design`: a selection frame with handles and a cursor; the cursor drags the corner, the frame grows. Row 21, Page Design Studio. `design_canvas` is the Artifact Canvas's own drawing (`design-canvas-icon.md`).

## Ecommerce (amber)
- `orders`, key `orders`: an order receipt with a tick; the receipt prints, the tick is drawn.
- `products`, key `products`: a cube; it hops.
- `catalog`, key `catalog`: a category tree; the children come in one after another.
- `offers`, key `offers`: a discount coupon with a per cent sign; it tilts.
- `shipping`, key `shipping`: a truck; it drives, speed lines show behind it.

## Accounting (lime)
- `erp`, key `erp`: a ledger with debit and credit columns; one line is written to each. The row's title stays "Resource planning (ERP)".

## Marketing (violet)
- `statistics`, key `visitors`: a pulse line; it is drawn left to right.
- `contacts`, key `contacts`: an address book with tabs; the tabs shift.
- `communication`, key `communication`: a speech bubble with one arrow out and one in; each arrow slides once (`main-menu-communication-icon.md`).

## System (grey)
- `users`, key `users`: two people; the second joins the first.
- `tools`, key `tools`: a toolbox; its lid opens on the hinge.

Related: docs/memory/ui/main-menu-icons.md, docs/memory/ui/main-menu-icon-motion.md
