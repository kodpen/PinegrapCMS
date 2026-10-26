---
name: designer-prototype-viewer
description: prototype.php plays a design's saved drafts (roles 0-2, or a share link): a session-less frame signed for one browser, link kinds, inert forms, no tracking, link_resolve.php
module: link_resolve, prototype
---

## What does the viewer show?
- A design's drafts (`pg_proto_page_set()`: Drafts folder, scheduled until live, none binned) in tab order, as saved. Opened from a draft tab's menu ("Preview as prototype", `_pgPrototypeUrl()`: saved tabs of this design; '' for role 3) or `prototype.php?page=<id>` / `?style=<id>`; another page starts at the first draft with a notice.
- The bar (name, pages, previous/next, hotspots, close) is the viewer's.

## Who may open it?
- Person: roles 0-2, `validate_user()`, `validate_area_access($user, 'manager')`. Share link: `prototype.php?link=<token>`, anyone or the team, maybe a password (designer-prototype-share-links.md). get_page.php's private gate is untouched.

## How is the draft drawn signed out?
- Two requests (one keeps the editor's USER_* role: `pg_designer_preview_widgets()`). The viewer signs a frame address with ENCRYPTION_KEY (`pg_proto_frame_sign()`: design, page, person, item, record, expiry 15 min; a link's `pg_proto_link_frame_sign()`: link id for person) bound to its session (`pg_proto_frame_bind()`, `b=`: page scripts read the address; a link's: the session that opened it, signed out too). The frame (`?frame=1`) keeps the session cookie's value, sets `PG_NO_SESSION`, empties `$_COOKIE` before init.php, reads the role (or the link) again per frame. A forged address is logged (waf_log, firewall.log), counted (`pg_proto_frame_offence()`); another browser's ('unbound') only refused.
- `REQUEST_URI` is the page's own address (`pg_proto_public_uri()`), so no switcher, calendar or sign-in link carries the signed one. REQUEST_URL stays the frame's (panel, error pages, active link).
- `get_page_content()` under `pg_seo_rendering(true)`: no status, redirect (My Account's sign-in too), record view, 404 line; output_error() throws. Then `pg_tr_finalize()`, `pg_version_assets()`. No page cache, Set-Cookie; headers as workspace_guest.php.
- Site Log: one line per design and session.

## Does a prototype view reach any statistics?
- No. A background render prints no Analytics tag (get_page_content.php, the SEO pass too). The frame answers the cookie notice with no (`pg_proto_consent_refused()`, also in the shim's jar) and hides it: held tags stay held. The design's scripts run as for a visitor.

## What do links do?
- `pg_proto_link_target()`: prototype (a draft of the design: `prototype.php?page=`, target _top, `sub`, `r`, `anchor` = fragment; a plain click posts to the viewer); anchor (fragment of this view); site (live page, file, item, `/de/...`: new tab); draft (another design's, or an item under it) and broken (unknown, binned, an item under one): no href, `data-pg-proto`, title; external, mailto, tel: new tab; bare anchors, toggles, javascript: kept. A bare `?query` keeps the view's page and item, takes its own `r` and fragment (catalog paging); other query values drop. Hotspots outline `data-pg-proto` links. `pg_proto_rewrite_links()` skips script, style, textarea, comments; tags read as a browser does (`pg_proto_tag_pattern()`: an `<a>` in an attribute is text). Only the page's names are read (`pg_link_maps_for_items()`).

## How are forms, cart and sign-in inert?
- Sandbox: scripts, escaping popups, top navigation on a click; no allow-forms or allow-same-origin: nothing sends, no site cookie reaches the page. Costs: same-host fonts sans CORS fall back; cookies and Web Storage in memory per view (shim), IndexedDB refuses; URL filters off; feeds see the Drafts folder; script-drawn links open a new tab.

## Where is the link resolver?
- `includes/fn/link_resolve.php`: `pg_link_parse()`, `pg_link_resolve_item()`, `pg_link_resolve()` (pure; `class` says what 'external' was), `pg_seo_link_maps()`, `pg_seo_form_address_names()`. `pg_seo_resolve_link()` delegates (tests/link_resolve_test.php).

Related: designer/page-drafts.md, designer/designer-live-preview.md
