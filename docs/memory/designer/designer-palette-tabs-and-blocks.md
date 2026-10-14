---
name: designer-palette-tabs-and-blocks
description: Designer palette has two tabs (Bileşenler building blocks, Bloklar ready sections): adding a block, shared list plumbing, recents strip, tab strip width.
---

## What is the split between the two palette tabs?
- **Bileşenler** tab = building blocks (Layout, Semantic, Bootstrap, Content, Form, Regions). **Bloklar** tab = finished page sections. The criterion is kind, not length: whatever explodes into a tree of dozens of nodes on drop is a block.

## How do I add a block?
- **Write it into the `_sdBlockPaletteItems()` list; do not touch grouping.** `_sdBlockPaletteGroups()` buckets blocks by `componentType` prefix (`navbar-`, `hero-`, `cta-` ...). A block matching no prefix appears under "Diğer", so no block is invisible in the palette.
- Opening a new family: add its prefix to that table; never hand-place a single block.

## What plumbing is shared?
- Three lists (component palette, block palette, recents strip) share `_sdPaletteItemHTML`, `_sdPaletteCategoriesHTML`, `_sdWirePaletteItems`, `_sdWirePaletteSearch`. When adding a fourth list, do not write your own copy.
- A wired row carries `data-sd-wired`, so wiring the same root twice does not duplicate listeners.
- **The recents strip is in the Bileşenler tab**; it writes there regardless of which tab the item came from.

## How wide may the tab strip get?
- `#sd-panel-tabs` is full with four titles. The rule scoped to it removes uppercase and shrinks text one step. Before adding a fifth tab, measure whether titles are clipped (`scrollWidth > clientWidth`).
- Do not shorten a title to fix it: they are shared `_sdT()` keys.
- Rail buttons derive from this strip: see docs/memory/designer/designer-sticky-panel-layout.md.
