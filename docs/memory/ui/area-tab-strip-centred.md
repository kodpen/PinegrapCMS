---
name: area-tab-strip-centred
description: An area's tab strip sits centred in the window on every tab whatever the screen's width (owner 2026-11-19, poll 153); Communication since TS1; how, and the notices under it
module: messages_area
---

## Where does an area's tab strip sit?
- Centred in the window's content area on every tab, whatever width the tab's screen takes (owner, 2026-11-19, poll 153, `workflow/decisions-made-panel-10.md`); the lists' and edit screens' widths stay. Before, the strip sat at `#content`'s left edge, so it jumped between a full-width tab and a centred list.
- How: the strip is a flex row and its `.pg-seg` has `margin-inline: auto`. Every tab screen's `#content` is centred (full width, or the list measure's fit-content box), so centred in `#content` is centred in the window. Auto margins, not `justify-content: center`, keep a strip wider than a phone scrollable from its first tab; under 576 px the strip fills the line.
- A tab screen prints the strip first in `#content`, notices under it, so a notice never moves it down.
- `tests/look_page_widths_test.php` and `tests/messages_area_test.php` pin it.

## Which areas have it?
- Communication (`.pg-messages-tabs`, TS1). The other areas' `.pg-seg` strips follow in TS2 of `plans/area-tab-strip-place.md` through one shared class.

Related: docs/memory/ui/look-page-widths.md, docs/memory/ui/messages-area-shell.md
