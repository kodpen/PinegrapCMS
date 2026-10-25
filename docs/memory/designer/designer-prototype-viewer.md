---
name: designer-prototype-viewer
description: prototype.php plays a design's saved drafts (roles 0-2): a session-less frame signed for one browser, link kinds, inert forms, no tracking, the resolver in includes/fn/link_resolve.php
---

## What does the viewer show?
- A design's drafts (`pg_proto_page_set()`: Drafts folder, scheduled until live, none binned) in tab order, as last saved. Opened from a saved draft tab's menu ("Preview as prototype", `_pgPrototypeUrl()`: not on another design's tab or an unsaved one; `_design.prototypeUrl` is '' for role 3) or `prototype.php?page=<id>` / `?style=<id>`; another page starts at the first draft with a notice.
- The bar (name, page list, previous/next, hotspots, close) is the viewer's, never the design's.

## Who may open it?
- Roles 0-2: `validate_user()` and `validate_area_access($user, 'manager')`. Share links: plans/prototype-sharing.md slice 3. get_page.php's private gate is untouched.

## How is the draft drawn signed out?
- Two requests (one would keep the editor's role in USER_*, `pg_designer_preview_widgets()`). The viewer signs a frame address with ENCRYPTION_KEY (`pg_proto_frame_sign()`: design, page, person, item, record, expiry 15 min) bound to its session (`pg_proto_frame_bind()`, `b=`: page scripts read the address). The frame (`?frame=1`) keeps the session cookie's value, sets `PG_NO_SESSION`, empties `$_COOKIE` before init.php, reads the role again. A forged address is logged (waf_log, firewall.log) and counted (`pg_proto_frame_offence()`); one from another browser ('unbound') is only refused.
- `REQUEST_URI` is the page's own address (`pg_proto_public_uri()`), so switcher, calendar and sign-in links never carry the signed one. REQUEST_URL stays the frame's (read by the panel, error pages, the active-link fallback).
- `get_page_content()` under `pg_seo_rendering(true)`: no status, redirect (My Account's sign-in one too), record view, 404 line; output_error() throws. Then `pg_tr_finalize()`, `pg_version_assets()`. No page cache, Set-Cookie; headers as workspace_guest.php.
- Site Log: one line per design and session.

## Does a prototype view reach any statistics?
- No. A background render prints no Google Analytics tag (get_page_content.php, also the SEO pass). The frame answers the cookie notice with no (`pg_proto_consent_refused()`, also in the shim's jar) and hides it: held tags stay held. The design's own scripts run as for a visitor.

## What do links do?
- `pg_proto_link_target()`: prototype (a draft of the design: `prototype.php?page=`, target _top, `sub`, `r`, `anchor` = fragment); anchor (fragment of the view on screen); site (live page, file, item, `/de/...`: new tab); draft (another design's draft or an item under it) and broken (unknown, binned, an item under one): no href, `data-pg-proto`, title; external, mailto, tel: new tab; bare anchors, toggles, javascript: untouched. A bare `?query` keeps the view's page and item, takes its own `r` and fragment (catalog paging stays on the group); other query values drop. Hotspots outline every `data-pg-proto` link. `pg_proto_rewrite_links()` skips script, style, textarea, comments; tags read as a browser does (`pg_proto_tag_pattern()`: an `<a>` in an attribute is text). Only the page's names are read (`pg_link_maps_for_items()`).

## How are forms, cart and sign-in inert?
- Sandbox: scripts, popups that escape, top navigation on a click; no allow-forms, no allow-same-origin, so nothing sends and no site cookie reaches the page. Costs: same-host fonts without CORS fall back; cookies and Web Storage in memory per view (shim, nothing kept between pages), IndexedDB refuses; URL filters off; feeds see the Drafts folder; script-drawn links open a new tab.

## Where is the link resolver?
- `includes/fn/link_resolve.php`: `pg_link_parse()`, `pg_link_resolve_item()`, `pg_link_resolve()` (pure; `class` says what 'external' was), `pg_seo_link_maps()`, `pg_seo_form_address_names()`. `pg_seo_resolve_link()` delegates (tests/link_resolve_test.php).

Related: designer/page-drafts.md, designer/designer-live-preview.md
