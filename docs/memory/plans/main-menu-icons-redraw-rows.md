---
name: main-menu-icons-redraw-rows
description: Plan main-menu-icons-redraw, the rows: which drawing of ui/main-menu-icons-drawing/icons.js each menu_icon name takes, its idea and hover move, the timing and the live parts
---

## How is this read?
- `menu_icon` name of `output_menu()`, then the key of `window.ICONS` in `ui/main-menu-icons-drawing/icons.js`, the idea, the move (the `data-m` parts). The SVG bodies and keyframes are copied from that file.

## The untitled block (blue)
- `dashboard`, key `panel`: the tiles of a dashboard; they pop in one after another. No clock.
- `flows`, key `flows`: two boxes and the elbow connector between them; hover lights the second box; the live dot runs the connector.
- `workspace`, key `workspace`: the channel mark "#"; its two bars are drawn left to right.
- `notes`, key `notes`: a sticky note; its corner lifts.
- `mail`, key `inbox`: an inbox tray; the arrow drops into it.

## Content (teal)
- `pages`, key `pages`: a browser window, today's idea; its lines are written.
- `folders`, key `folder`: a folder with a paper, today's idea; the paper rises.
- `calendars`, key `calendar`: a calendar leaf with today's day; the leaf flips.
- `forms`, key `forms`: a label, a field and a submit pill; the field is written, the pill lights. My reviews (slot 28) keeps sharing it.

## Design (rose)
- `design_canvas`, key `design`: a selection frame with handles and a cursor; the cursor drags the corner, the frame grows.

## Ecommerce (amber)
- `orders`, key `orders`: an order receipt with a tick; the receipt prints, the tick is drawn.
- `products`, key `products`: a cube, today's idea; it hops.
- `catalog`, key `catalog`: a category tree; the children come in one after another.
- `offers`, key `offers`: a discount coupon with a per cent sign; it tilts.
- `shipping`, key `shipping`: a truck; it drives, speed lines show behind it.

## Accounting (lime)
- `erp`, key `erp`: a ledger with debit and credit columns; one line is written to each. The row's title stays "Resource planning (ERP)".

## Marketing (violet)
- `statistics`, key `visitors`: a pulse line; it is drawn left to right.
- `contacts`, key `contacts`: an address book with tabs; the tabs shift.
- `communication`, key `communication`: a speech bubble with one arrow out and one in; each arrow slides once.

## System (grey)
- `users`, key `users`: two people; the second joins the first.
- `tools`, key `tools`: a toolbox; its lid opens on the hinge.

## How do the icons move?
- One move per icon: 0.5 s, `cubic-bezier(.4, 0, .2, 1)`, on the row's hover and keyboard focus, inside `prefers-reduced-motion: no-preference`. Nothing loops but the live part.
- Live: Calendars prints the day of the month (today's server value and script stay). Automation flows: a dot runs the connector while the row carries `data-pg-flows-running`, written as translate keyframes along the path as today (`offset-path` is not taken by SVG parts everywhere).
- The Dashboard row is tiles, so the clock's hands and their script go.

## What if a row has no drawing?
- The fallback of today stays: a `bi` icon on the tile, then an svg, then a letter.

Related: docs/memory/plans/main-menu-icons-redraw.md
