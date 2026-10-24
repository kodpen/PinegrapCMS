---
name: designer-prototype-links
description: Plan (2026-10-24, not started, answers open): a Figma/XD-like prototype view in the visual designer: pages as frames, menu items and links as hotspots, an arrow sets the page a click opens
---

## What is asked? (owner, 2026-10-24)
- Later: in the visual editor, link a menu item to the page it opens when clicked, like the prototype mode of Figma and Adobe XD, shown and linked on the Flows-style canvas.

## What is there today?
- Design menus are node trees: the Navbar component breaks into nav > ul.navbar-nav > li.nav-item > content link with `props.href`. The top menu and footer usually sit in a shared component (`shared_components.tree_json`, drawn per request). Classic `menus` / `menu_items` (by `link_page_id`) only through the legacy `<menu>` region.
- Other links: btn component (`btnElement` a), semantic `a`, `<a>` inside text (`_sdIlParse()`), bound hrefs (`__page_link` by page id), widget `*_page_id` settings.
- An href is a page-name path (editor_select_page_or_file.php). A rename rewrites no tree (`_pgFollowRename()`; a 301 short link from `pg_short_link_page_renamed()` keeps the old address); a binned page gets a parked name; drafts sit in the Drafts folder; one page serves every language (`pg_tr_rewrite_links()`).
- The editor holds every tab's tree (`_pages`) and components (`_sharedCache`); a node `_id` is unique per tree only.
- `pg_seo_resolve_link()` and `seo_link` resolve and store links (refreshed on design save, not on a shared component edit; no JS twin, no test).
- Rights: roles 0-1 change any link; 2-3 only inside an editable area (`pg_designer_merge_restricted_tree()`, `_sdCanEditNode()`), though `<a>` inside text passes. Shared components: roles 0-1, no lock, no revision. A published page goes live on save; drafts are the safe sandbox.
- No page thumbnails (only `pg_design_thumb_svg()`); `generateHTML()` takes a tree. Canvas links never navigate; Preview sends page links to the live site.

## What is proposed? (not approved yet)
- A Prototype view inside the visual designer on plans/graph-canvas-core.md (slice 4 ports): each page a frame, each link a named port (hotspot) where it sits, an arrow is its href.
- An arrow writes the href through `saveState()`, `_sdMayEdit()`, `_sdSharedMarkEdited()` and the normal Save (`pg_designer_save_page()`), so lock, revision and `_pgPageUnchanged()` apply. A shared header draws once, as its own frame saying how many pages it reaches (`pg_shared_component_usage()`).
- Targets: design pages; other site pages, external, mailto, #anchor as end chips. Broken (binned, deleted, unknown) has one definition: a JS twin of `pg_seo_resolve_link()` with PHP tests; drafts flagged.
- Frames start as hotspot lists; positions per browser first, later shared in `style.style_tab_layout` (both normalisers, no ALTER).

## Slices (one PR each)
1. Read-only map: frames and arrows from the trees, the resolver twin and tests, broken, draft and external marks; a hotspot selects its node.
2. Draw and re-point arrows within today's rights, undo, the shared warning, Save.
3. Play: a hotspot opens the target tab's preview, unsaved tabs too.
4. Shared positions, thumbnails; links by page id that survive a rename (new tree prop and renderer: ask first).
- Schema: none for slices 1-3; a new table or column is asked first.

## Open questions (recommendation)
1. Where: (a) inside the designer, (b) on the Workspace Flows screen → a (its save, rights, undo).
2. Hrefs: (a) by page name, as today, (b) by page id → a now, b in slice 4.
3. Who draws: (a) as today, roles 0-1, roles 2-3 inside editable areas, (b) roles 2-3 everywhere → a.
4. Classic menus, widget page settings: (a) read-only arrows, (b) editable → a.
5. Breakpoint: desktop width, collapsed navbars shown as their links.

## Where to start?
- After graph-canvas-core slices 2 and 4. Read designer/README.md, designer/designer-canvas-links.md, designer/shared-component-model.md, designer/designer-content-roles-save-gate.md; claim before code.
