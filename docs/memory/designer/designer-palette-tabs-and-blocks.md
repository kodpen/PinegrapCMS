---
name: designer-palette-tabs-and-blocks
description: Designer palette Components tab with a Components / Blocks list switch (building blocks vs ready sections): adding a block, shared list plumbing, recents strip, tab strip width.
---

## How are the two lists laid out?
- One tab title, Components ("Bileşenler"), holds two lists behind a switch at its top: **Components** = building blocks (Layout, Semantic, Bootstrap, Content, Form, Regions), **Blocks** ("Bloklar") = finished page sections. The criterion is kind, not length: whatever explodes into a tree of dozens of nodes on drop is a block.
- Owner (2026-11-11): the Blocks tab moved into the Components tab behind a second-level switch drawn like the panel's view switch (Till / Document on the Sales screen, `.pg-sale-switch`); one tab title for both lists.
- `_sdPalSwitchHTML()` draws it (`role="tablist"`, two `role="tab"` buttons with `aria-controls` on `#sd-pal-components` / `#sd-pal-blocks`, both `role="tabpanel"`); `_sdPalSwitchWire()` wires click, Arrow keys, Home and End (choose at once, roving `tabindex`), and opens on the list stored in `localStorage` `pinegrap_designer_palette_list`.
- `.sd-pal-switch` in `style_designer.css` copies the panel switch's rules with the editor's dark tokens; `.sd-pal-list[hidden]` needs its own `display: none`, since `display: flex` beats `hidden`.
- A design without Bootstrap has no Blocks list: the switch and `#sd-pal-blocks` are removed and the Components list stands alone.
- "My library", the site's own saved sections, is the first group of the Blocks list (of Components without Bootstrap): `designer-my-library-overview.md`.

## How do I add a block?
- **Write it into the `_sdBlockPaletteItems()` list; do not touch grouping.** `_sdBlockPaletteGroups()` buckets blocks by `componentType` prefix (`navbar-`, `hero-`, `cta-` ...). A block matching no prefix appears under "Diğer", so no block is invisible in the palette.
- Opening a new family: its own file `style_designer_blocks_<family>.js` with its group and prefix ([[designer-block-family-files]]); never hand-place a single block. Only a block of a family already in the core goes into the core list.
- A core block also gets its one-line description in `_sdBlockInfo()` and its Turkish through `tr_add.php` in the same PR; `tests/designer_block_info_test.php` fails without it ([[palette-block-info]]).

## What plumbing is shared?
- Three lists (component palette, block palette, recents strip) share `_sdPaletteItemHTML`, `_sdPaletteCategoriesHTML`, `_sdWirePaletteItems`, `_sdWirePaletteSearch`. When adding a fourth list, do not write your own copy.
- A wired row carries `data-sd-wired`, so wiring the same root twice does not duplicate listeners.
- Each list keeps its own search box, its placeholder naming it (Search components, Search blocks).
- **The recents strip is in the Components list**; it writes there whichever list the item came from. Its head is a quiet label "Recently used" with the link "Clear" (`.sd-pal-label`), not a folding group head.

## How wide may the tab strip get?
- `#sd-panel-tabs` is the palette's head: a tab holder with three words (Components, Shared, System) and the dock button, no title row above it (`designer-left-column-heads.md`, #11973: the palette's and the Overview's title rows left, the list switch stays). At 250 px the three words fit at 12.5 px; under 230 px a tab that is not open is its rail icon. Before adding a tab, measure whether titles are clipped (`scrollWidth > clientWidth`).
- Do not shorten a title to fix it: they are shared `_sdT()` keys.
- Rail buttons derive from this strip, so the rail has no Blocks button: see docs/memory/designer/designer-sticky-panel-layout.md.

## What does every palette row show on hover?
- Owner (2026-11-11): every look, ready block and Components row shows a small drawing and one line of description in the tip card: looks [[design-theme-look-drawing]], blocks [[palette-block-sketch]] and [[palette-block-info]], Components rows [[palette-component-drawings]].
