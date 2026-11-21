---
name: settings-revealed-groups
description: Fields a switch opens inside a Site Settings card: no frame, tint or arrow, lined up with the label text, the indent dropped under 576 px; the divider over a nested row's first field and .pg-set-ruled
---

## How are the fields a switch opens drawn?
- Architect (2026-11-21, agent channel request 2372): inside a settings card or a folded row the group a switch opens (`.pg-set-card .collapse.popover`) is no callout: no frame, tint, shadow or arrow, on the card's own surface, one row gap under the switch's row.
- Why: the popover's frame and tint made a card inside the card on every pane that reveals fields (the owner's rule of request 2361, no card in a card).
- A help sentence that opens the group reads at the help size (13px, `--pg-fg-2`).
- Style: the block before `body.toolbar` in `backend.src.css`.

## Where does the group start?
- Lined up with the switch's label text. After a row with an icon tile (`.form-field:has(> .pg-set-tile)`) whatever follows in the cell (the fields it opens, a warning line, a note) starts past the tile: `calc(34px + 1.5rem)`.
- A group opened inside another adds no second indent. Under 576 px the indent is dropped and the fields take the full width.

## Where is a divider drawn that the plain rule misses?
- Settings one switch shows or hides together sit in a cell of their own holding only a nested row (one element to hide). Its first field was the first child of that row, so the row divider was never drawn on it: under Updates and Maintenance › Alerts, "Send alerts by e-mail" read as part of the master switch's help.
- A cell that holds only a nested row (`:has(> .row:only-child)`, not a `.collapse`) now hands the divider to its first field or checkbox. On the panes of 2026-11-21 it draws two lines: "Send alerts by e-mail" and the sales watch's first checkbox ("Orders stopped coming in").
- A cell that is not a field but a decision of its own is marked `.pg-set-ruled` and gets the divider too: IndexNow's connection under SEO (`#pgsub-indexnow`).
- The divider rules themselves: `ui/settings-card-row-layout.md`.

Related: docs/memory/ui/settings-card-shared-style.md, docs/memory/ui/settings-card-row-layout.md, docs/memory/ui/settings-fold-rows.md
