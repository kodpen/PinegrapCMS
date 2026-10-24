---
name: designer-prototype-viewer
description: prototype.php plays a design's saved drafts as a prototype (roles 0-2): session-less signed frame, link kinds, inert forms, no tracking, the resolver in includes/fn/link_resolve.php
---

## What does the viewer show?
- A design's drafts (`pg_proto_page_set()`: Drafts folder, scheduled until live, none binned) in tab order, as last saved. Opened from a saved draft tab's menu ("Preview as prototype", `_pgPrototypeUrl()`: not on a tab picked from another design and not saved yet; `_design.prototypeUrl` is '' for role 3) or `prototype.php?page=<id>` / `?style=<id>`; another page starts at the first draft with a notice.
- The bar (name, page list, previous/next, hotspots, close) is the viewer's, never the design's.

## Who may open it?
- Roles 0-2: `validate_user()` and `validate_area_access($user, 'manager')`. Outsider links are slice 3 of plans/prototype-sharing.md. get_page.php's private gate is untouched.

## How is the draft drawn signed out?
- Two requests. The viewer signs a frame address with ENCRYPTION_KEY (`pg_proto_frame_sign()`: design, page, person, expiry 15 min, item, record: it opens only that view). The frame (`?frame=1`) defines `PG_NO_SESSION` and empties `$_COOKIE` before init.php. The role is read again per frame; an unsigned address is logged (waf_log, firewall.log) and counted (`pg_proto_frame_offence()`).
- Not one request: USER_* constants keep the editor's role (`pg_designer_preview_widgets()`).
- `REQUEST_URI` is the page's own address (`pg_proto_public_uri()`), so switcher, calendar and sign-in links never carry the signed one. REQUEST_URL (init.php) stays the frame's: only the panel, error pages and the active-link fallback read it.
- `get_page_content()` under `pg_seo_rendering(true)`: no status, redirect, record view, 404 line; output_error() throws. Then `pg_tr_finalize()`, `pg_version_assets()`. No page cache, Set-Cookie; headers as workspace_guest.php.
- Site Log: one line per design and session.

## Does a prototype view reach any statistics?
- No. A background render prints no Google Analytics tag (get_page_content.php, also the SEO pass). The frame answers the cookie notice with no (`pg_proto_consent_refused()` in `$_COOKIE` and the shim's jar) and hides it: held tags stay held. Scripts the design writes itself run as for a visitor.

## What do links do?
- `pg_proto_link_target()`: prototype (a draft of the design: `prototype.php?page=`, target _top, `sub`, `r`, `anchor` = fragment); anchor (fragment of the view on screen: `#name`); site (live page, file, item, another language's `/de/...`: new tab); draft (another design's draft or an item under it) and broken (unknown, binned, an item under one): no href, `data-pg-proto`, title; external, mailto, tel: new tab; bare anchors, toggles, javascript: untouched. Other query values are dropped. `pg_proto_rewrite_links()` skips script, style, textarea, comments; only the page's names are read (`pg_link_maps_for_items()`).

## How are forms, cart and sign-in inert?
- Sandbox: scripts, popups that escape, top navigation on a click; no allow-forms, no allow-same-origin, so nothing sends and no site cookie goes. Costs: same-host fonts without CORS fall back; unguarded storage use throws; URL filters off; feeds see the Drafts folder; script-drawn links open a new tab.

## Where is the link resolver?
- `includes/fn/link_resolve.php`: `pg_link_parse()`, `pg_link_resolve_item()`, `pg_link_resolve()` (pure; `class` says what 'external' was), `pg_seo_link_maps()`, `pg_seo_form_address_names()`. `pg_seo_resolve_link()` delegates, same answers (tests/link_resolve_test.php).

Related: docs/memory/designer/page-drafts.md, docs/memory/designer/designer-live-preview.md
