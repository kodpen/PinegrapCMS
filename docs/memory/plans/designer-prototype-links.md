---
name: designer-prototype-links
description: Plan (2026-10-24, not started, answer 1 given): a Figma/XD-like prototype view in the visual designer: pages as frames, menu items and links as hotspots, an arrow sets the page a click opens
---

## What is asked? (owner, 2026-10-24)
- Later: in the visual editor, link a menu item to the page it opens when clicked, like the prototype mode of Figma and Adobe XD, shown and linked on the Flows-style canvas.

## What is there today?
- Links: node trees (href in `props.href` or `props._attrs`), shared components, widget page settings, and classic menus and legacy regions outside the trees; `__page_link` already links by page id inside some widgets. Details, renames, the resolver's limits, editor state, rights, Publish: plans/designer-prototype-links-code.md.
- `seo_link` holds resolved links, refreshed on a design save, not on a shared component edit; the resolver is pure with PHP tests since #1579 (`pg_link_resolve()`), no JS twin.

## What is proposed? (not approved yet)
- A Prototype view inside the visual designer on plans/graph-canvas-core.md (slice 4 ports): each page a frame, each link a named port (hotspot) where it sits, an arrow is its href (`props.href`, or the `_attrs` href where the node keeps it). Bootstrap toggle links are no hotspots.
- An arrow from another page first opens that tab (`_pgTabsSwitch()`), so `saveState()`, `_sdMayEdit()` and the page lock answer for it (or these helpers get a per-page form); then `_sdSharedMarkEdited()` and the normal Save (`pg_designer_save_page()`), so lock, revision and `_pgPageUnchanged()` apply. A shared header draws once, as its own frame saying how many pages it reaches (`pg_shared_component_usage()`).
- Targets: design pages; other site pages, external, mailto, #anchor as end chips; an old href of a renamed page followed through its short link to the page. Broken (binned, deleted, unknown) has one definition: `pg_link_resolve()` (#1579) and a JS twin of it and its classes; at content level no hidden page name reaches the browser; drafts flagged.
- Frames start as hotspot lists; positions per browser first, later shared in `style.style_tab_layout` (both normalisers, no ALTER).
- Drafts played as a prototype, a view-only link to it, flow shares: plans/prototype-sharing.md.

## Slices (one PR each)
1. Read-only map: frames and arrows from every tab (`_pgTreeOf()`, shared components prefetched), classic menus (`pg_menu_tree()`) and legacy regions by a new read, the resolver's JS twin with tests (PHP split done in #1579), broken, draft and external marks; a hotspot selects its node.
2. Draw and re-point arrows within today's rights, undo, the shared and Publish warnings, Save.
3. Play: a hotspot opens the target tab's preview, unsaved tabs too (`tab:<key>`, as `__page_link` does).
4. Shared positions, thumbnails; links by page id that survive a rename, built from `__page_link` (outside widgets a new tree prop and renderer: ask first).
- Schema: none for slices 1-3; a new table or column is asked first.

## Which questions are answered? (owner, 2026-10-24: 1 a; 2-5 open)
1. Where: (a) inside the designer, (b) on the Workspace Flows screen → a: a Prototype view in the visual designer on the shared canvas core (plans/graph-canvas-core.md), with the designer's save, rights, undo.
2. Hrefs: (a) by page name, as today, (b) by page id → a now, b in slice 4.
3. Who draws: (a) as today: roles 0-1, roles 2-3 inside editable areas (opened shared components too), (b) roles 2-3 everywhere → a.
4. Classic menus, legacy regions, widget page settings: (a) read-only arrows, (b) editable → a.
5. Breakpoint: desktop width, collapsed navbars shown as their links.

## Where to start?
- After graph-canvas-core slices 2 and 4. Read designer/README.md, designer/designer-canvas-links.md, designer/record-value-page-links.md, designer/shared-component-model.md, designer/designer-content-roles-save-gate.md; claim before code.
