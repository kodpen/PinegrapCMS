---
name: prototype-sharing
description: Plan (2026-10-24, not started, answers open): a design's drafts play as a clickable prototype shared by a view-only link; flows shared with the team (F7), a link later
---

## What is asked? (owner, 2026-10-24)
- Share a prototype or a flow; going further, visual designs saved as drafts count as prototypes by themselves.

## What is there today?
- A draft is a whole page in the private Drafts folder (`pg_page_draft_folder_id()`, `page_drafts`, 2026.4.7); a scheduled page is a draft with `page.publish_at` (11.0.17). No working copy of a live page; `page_revisions` (11.0.15) keep only published states.
- Seen in the editor's Preview (blob page; widgets drawn signed out, `pg_designer_preview_widgets()`; page links to the live site) or at its address: `check_view_access()` lets in roles 0-2, role 3 with folder rights. No preview token; not in the sitemap or robots.txt.
- Draft links are page-name hrefs (`__page_link` by id only in widgets); a rename rewrites no tree and gives a draft no 301 (plans/designer-prototype-links-code.md).
- Token links: guests (`ws_guest_link_issue()`: a `short_links` row, SHA-256 `token_hash`, `token_hint`, once or timed by `pg_short_link_durations()`, token behind `#` claimed by POST, a hashed session cookie read each request); approval guests (own table, workspace_approval.php); `router_short_link_gate()` sends no-store, noindex, no-referrer; a bad token is `ws_guest_offence()`. Signed: `pg_fs_token()`. Notes: `ws_note_shares` (person or channel), no link.
- Flows: `ws_flow_access()`: edit for the owner while staff, run for readers of a live flow's home channel; no shares (F7 of plans/workspace-flows.md).

## What is proposed? (not approved yet)
- Drafts are the prototype: a design's draft pages (scheduled ones with their time) and their links; a link to a live page opens it on the site, a broken one is marked. Opened from the Prototype view (plans/designer-prototype-links.md) or "Preview as prototype"; a toggle outlines hotspots.
- Viewer prototype.php: the saved draft drawn signed out, links resolved by the split `pg_seo_resolve_link()`, forms, cart and sign-in inert; headers as workspace_guest.php; no page cache.
- Link: token as the guests', expiry, revoke, optional password (`pg_login_throttle_guard()`), or team only; shows the latest saved draft; roles 0-1 make it, Site Log lines, an API route with `dry_run`.
- Flows: F7 shares like `ws_note_shares` (person or channel; view, run or edit) read by `ws_flow_access()`; a read-only outside graph link (people, channels as kinds) and run state later.
- Schema, ask first: `prototype_links` (style, start page, token hash, hint, expiry, password hash, audience, maker, revoked, uses) on a general label (11.0.1 on); `ws_flow_shares` on a Workspace one (11.0.80-89, 11.0.110-119); labels: release/version-numbering.md.

## Slices (one PR each)
1. Preview as prototype, no schema: a link to an open tab plays its preview (`tab:<key>`), others open the site in a new tab; hotspots toggle; designer-prototype-links slice 3 brought forward.
2. prototype.php for signed-in roles 0-2, no schema.
3. Share links (schema): dialog, list, revoke, password, WAF offence.
4. F7 flow shares (schema).
5. Later (ask): outside flow link, run state, comments.

## Open questions (recommendation)
1. Pages: (a) the design's drafts, scheduled too, (b) ticked per link, (c) every draft of the site → a.
2. Who opens a link: (a) anyone holding it, expiry required, (b) team only, (c) chosen per link → c.
3. Who makes links: (a) roles 0-1, like the Schedule section, (b) roles 0-2, as drafts open today → a.
4. Flows: (a) F7 team shares first, outside link later, (b) a graph link now, (c) with a run's state → a.
5. Own table, or a new `short_links.destination_type` (ENUM) → own: short link screens and checks untouched.

## Where to start?
- Slice 1; slice 2 after designer-prototype-links slice 1 (resolver). Read designer/README.md, designer/page-drafts.md, designer/designer-live-preview.md, workspace/workspace-flows-store.md; claim first.
