---
name: designer-prototype-viewer
description: prototype.php plays a design's saved drafts as a clickable prototype (roles 0-2): session-less signed frame, link kinds, sandboxed inert forms, the link resolver in includes/fn/link_resolve.php
---

## What does the viewer show?
- A design's drafts (`pg_proto_page_set()`: its pages in the Drafts folder, scheduled ones until they go live, none binned) in tab order, as last saved. Opened from a draft tab's menu ("Preview as prototype", `_pgPrototypeUrl()`; `_design.prototypeUrl` is '' for role 3) or `prototype.php?page=<id>` / `?style=<id>`; a page outside the set starts at the first draft with a notice.
- The bar (name, page list, previous/next, hotspots, close) is the viewer's, never the design's.

## Who may open it?
- Roles 0-2: `validate_user()` (signed out goes to sign-in) and `validate_area_access($user, 'manager')`. Outsider links are slice 3 of plans/prototype-sharing.md. get_page.php's private gate is untouched.

## How is the draft drawn signed out?
- Two requests. The viewer signs a frame address with ENCRYPTION_KEY (`pg_proto_frame_sign()`: design, person, expiry 15 min). The frame (`?frame=1`) defines `PG_NO_SESSION` and empties `$_COOKIE` before init.php: USER_LOGGED_IN is false, no remember-me token signs anyone in. The person's role is read again on every frame; an address not signed by the site is logged (waf_log, firewall.log) and counted as an offence (`pg_proto_frame_offence()`).
- Not one request: USER_* are constants set by initialize_user(); a render inside the editor's request (`pg_designer_preview_widgets()`) keeps their role. The core renderer is unchanged.
- `get_page_content()` under `pg_seo_rendering(true)`: no status code, redirect, record view, 404 log line; output_error() throws and the frame shows the message. Then `pg_tr_finalize()` and `pg_version_assets()` as get_page.php does. No page cache, visitor stats or Set-Cookie; headers as workspace_guest.php, X-Frame-Options SAMEORIGIN (viewer DENY).
- Site Log: one line per design and session, not "User viewed private page" per click.

## What do links do?
- `pg_proto_link_target()`: prototype (a draft of the design: `prototype.php?page=`, target _top, a pretty address as `sub`, `?r=` kept), site (live page, file, item: new tab), draft (another design's) and broken (unknown, binned, empty): no href, `data-pg-proto`, aria-disabled, title; external, mailto, tel: new tab; anchors, toggles, javascript: untouched. `pg_proto_rewrite_links()` skips script, style, textarea and comments; only the page's own names are read (`pg_link_maps_for_items()`).

## How are forms, cart and sign-in inert?
- The iframe sandbox gives scripts, popups that escape it and top navigation on a click; no allow-forms, no allow-same-origin. No form sends, and the page's opaque origin sends no site cookie (a JSON POST to api.php fails its preflight). The frame script notes a submit click, Enter in a field, a broken link.
- Costs: same-host fonts without CORS headers fall back; a script reading document.cookie or localStorage unguarded throws; URL filters are off (background render); feeds and structured data see the private Drafts folder; links drawn by scripts later open in a new tab.

## Where is the link resolver?
- `includes/fn/link_resolve.php`: `pg_link_parse()`, `pg_link_resolve_item()`, `pg_link_resolve()` (pure; `class` says what 'external' was), and the moved `pg_seo_link_maps()` / `pg_seo_form_address_names()`. `pg_seo_resolve_link()` delegates, same type and id, maps still read lazily (tests/link_resolve_test.php).

Related: docs/memory/designer/page-drafts.md, docs/memory/designer/designer-live-preview.md
