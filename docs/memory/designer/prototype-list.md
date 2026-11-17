---
name: prototype-list
description: The Prototype view's links panel (PV6): the selected frame's links out and in and its shared parts, one picked link, Change target and Open in the editor, N broken and its list, the frame badge, Play
---

## What does the panel show?
- `aside.sd-proto-list` right of the stage inside `section#sd-proto` (`div.sd-proto-body` holds both). Drawn by `_sdProtoListDraw()` from `PGProtoHotspots.listOf(layout, id)`, never from the canvas, on every change of the selected frame and of the picked link.
- The frame's name; "Outgoing · n": the row's label (a menu's link as "Menu › item"), "→ target" (a frame's name or a chip's text), a broken one in red with why; "Incoming · n": "source · label", a shared part's link saying "shared part"; a page's "Shared parts on this page" (nested ones too, `layout()` keeps them as `parts`), each a button selecting that frame. Nothing selected: "Select a page to see its links."
- Why a link is broken, `brokenWhy(row)`: `menu` (a menu or region the read does not know), `kept` (kept by a page id whose page is gone), `address` (no address), `page` (no page at its address). The definition of broken stays `pg_link_resolve()` and its twin (K5).

## What does a click pick?
- One state, `_sdProto.link = { frame, port }`, from a panel row (`_sdProtoPick()`) or a link's mark on a picture (a press: `_sdProtoPickRow()`, which scrolls its panel row into view). Its frame is selected unless the selected frame is the link's target; its mark (`sd-proto-hot-picked`) and arrow (`sd-proto-edge-picked`) are marked and `canvas.view.reveal()` brings the other end into view.
- "Open in the editor" on the panel row opens it (`_sdProtoOpenRow(frame, port)`, the tab opened, the node selected). "Change target" (drawable rows only, K2) and L, the menu key or Shift+F10 on the panel row open today's target menu (`_sdProtoMenuOpen(frame, port, anchor)`), placed at the panel row and giving the focus back to it.
- A change of the selected frame lets go of a picked link that is neither the frame's own nor one into it; a redraw lets go of one whose row is gone.

## Where are the problems?
- `brokenOf(layout)`: every broken row in the frames' order and each frame's count. The bar says "N links" and, when there are any, a red "N broken" button (`.sd-proto-broken`) opening `.sd-proto-problems`, whose "Broken links" group has one button per broken link (frame · label, why and address), which picks it. A press outside closes it.
- A frame with broken links carries a red count (`.sd-proto-badge`) on its head, also when dimmed.
- Links without a target, pages no link leads to and "N to check": designer/prototype-problems.md.

## When is the panel open, and what does Play do?
- The bar's "Links" button opens and closes it; the choice is kept in `localStorage` `pg_proto_list`. Without one it is open from 1,100 px and closed below; under 1,100 px it opens over the stage's right side.
- "Play" opens `_pgPrototypeUrl()` of the selected tab, else of the first tab that has an address, in a new tab; it is hidden when no tab has one.

## How is it checked?
- `test_proto_hotspots_list_and_problems` (the list for pages with links out, in and from a shared part, nothing selected, a chip; every broken link and each count), `test_proto_hotspots_view_list_and_problems` (the wiring and the CSS).
