---
name: prototype-view-decisions
description: What was decided for the designer's Prototype view (owner 2026-10-24, #2446, 2026-11-01): where it lives, hrefs by name or page id, who draws, read-only sources, breakpoint, how an arrow saves
---

## What was asked? (owner, 2026-10-24)
- Link a menu item or link in the visual editor to the page it opens, like the prototype mode of Figma and Adobe XD, on a Flows-style box-and-arrow canvas. Built in #5401-#6376, rebuilt in #9864-#10243: designer/prototype-view.md.

## Why was it rebuilt, and which looks did the owner give?
- Owner (2026-11-15): forty pages in one row with every arrow drawn was "koca bir kargaşa" (a big mess); he wants Figma's idea, clearer. The engine is the graph canvas core strengthened, not a second one (`workflow/decisions-made-product-57.md`).
- Owner (2026-11-16, agent channel poll 58): all three of arrows routing themselves, a target suggested for a link without one (nothing written before he applies), and pages no link leads to and links without a target found as problems.
- Owner (2026-11-16, poll 89, message 6541): No to PV5's sheet: arrows leave the link itself, an unselected page shows no detail, a link is joined by dragging it onto a page (`workflow/decisions-made-product-63.md`). Architect (2026-11-16, agent channel message 6548): PV5 landed as the base; his look came again after PV10.
- Owner (2026-11-17, poll 119, message 7680): Yes to the view after PV10 (#10101), the links panel staying (`workflow/decisions-made-product-70.md`).
- Owner (2026-11-17, poll 137, message 8763): Yes to the "Suggest targets" window (#10243) (`workflow/decisions-made-product-72.md`).

## Where does it live?
- Inside the visual designer (not the Workspace Flows screen), on the shared graph canvas core (ui/graph-canvas-core-module.md), so the designer's Save, rights, undo and page lock apply.
- Each page is a frame, each link a named port (hotspot) where it sits; an arrow is its href (`props.href`, or the `_attrs` href where the node keeps it). Bootstrap toggle links (dropdown, collapse, tab, modal) are no hotspots.
- A shared header draws once, as its own frame saying how many pages it reaches (`pg_shared_component_usage()`).

## How does an arrow save?
- An arrow from another page first opens that tab (`_pgTabsSwitch()`), so `saveState()`, `_sdMayEdit()` and the page lock answer for it; then `_sdSharedMarkEdited()` for a shared frame and the normal Save (`pg_designer_save_page()`): lock, revision and `_pgPageUnchanged()` apply. No save path of its own.

## What are targets and what is broken?
- Design pages are frames; other site pages, external, mailto and #anchor links are end chips; an old href of a renamed page is followed through its short link to the page.
- Broken (binned, deleted, unknown) has one definition: `pg_link_resolve()` (#1579) and its JS twin (platform/link-resolve-js-twin.md). At content level no hidden page name reaches the browser; drafts are flagged.

## Which questions were answered? (1 owner 2026-10-24; 2-5 #2446)
1. Where: inside the designer, not on the Workspace Flows screen.
2. Hrefs: by page name as before, and by page id since #6233 (designer/page-link-by-id.md, decision in workflow/decisions-made-product-23.md).
3. Who draws: as for any link: roles 0-1 everywhere, roles 2-3 only inside editable areas (opened shared components too); not roles 2-3 everywhere.
4. Classic menus, legacy regions, widget page settings: read-only arrows, never editable from the view.
5. Breakpoint: desktop width; collapsed navbars are shown as their links.

## Where are positions kept?
- Frame and chip positions first per browser, then shared on the design in `style.style_tab_layout` (both normalisers, no ALTER): designer/prototype-places.md.

## Schema?
- None was added for the view; a new table or column is asked first (CLAUDE.md, Working mode 10).
- Playing drafts as a prototype, a view-only link and flow shares: designer/designer-prototype-viewer.md, designer/designer-prototype-share-links.md.
