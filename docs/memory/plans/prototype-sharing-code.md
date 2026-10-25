---
name: prototype-sharing-code
description: What the code holds for plans/prototype-sharing.md: drafts and their revisions, how a draft opens today, what Preview draws, token links, the password throttle, MCP routes, flow and note rights
---

## What is a draft, and is it kept?
- A whole page in the private Drafts folder (`pg_page_draft_folder_id()`, `page_drafts`, 2026.4.7); a scheduled page is a draft with `page.publish_at` (11.0.17). A live page has no working copy.
- Every changing save writes a `page_revisions` row (11.0.15), drafts too: source 'publish', `settings_json` folder the Drafts folder (designer/page-revisions.md). A link could pin one.

## How does a draft open today?
- At its address, `get_page.php` `case 'private'`: signed out, a redirect to sign-in (not a 404); signed in, `check_private_access()`: roles 0-2, role 3 with unexpired aclfolder rights 1 or 2 on the Drafts folder or a parent. Each view logs "User viewed private page.". The designer gives role 3 a draft's rights from its publish folder; the address does not.
- "Open on Site" / "View page" (`#sd-vb-live`, `#sd-view-page`, `_pgPageViewUrl()`) open any saved tab there, drafts too: roles 0-2 already click draft to draft by name, widgets, forms, cart live. Gap: outsiders, unsaved tabs.
- Preview: a blob page, page links to the live site. `pg_designer_preview_widgets()` only sets `pg_seo_rendering(true)` (account, cart, edit chips, URL filters act as a background render); records still follow the editor's `USER_LOGGED_IN`/`USER_ROLE` (viewer-filtered lists show staff every row). A signed-out view needs a render with no session.
- No preview token; not in the sitemap or robots.txt.

## How do links name pages?
- An href is a page-name path; a rename rewrites no tree, a draft gets no 301 (plans/designer-prototype-links-code.md). `tab:<key>` is only in widget page settings and `__page_link` `_bindParams` (`pg_sw_page_link_spec()`, designer/designer-page-pickers.md): a plain link finds an open tab by page name, an unsaved tab's typed name too.

## Which token links exist?
- Short links once or timed (`pg_short_link_durations()`): a once link has a token (hash, hint; add_short_link.php); `router_short_link_gate()` spends it on the first human GET, not on bots or HEAD, counts uses, sends no-store, noindex, no-referrer. A once link to a draft still meets the private gate.
- Guests (`ws_guest_link_issue()`): a `short_links` row, token behind `#` claimed by POST, a hashed session cookie; one guest, one link (a new one closes the old links and sessions); a once link's session lives `WS_GUEST_IDLE` (30 days), a timed one at most `WS_GUEST_LONGEST` (90). Bad token: `ws_guest_offence()`. `ws_channel_share()` gives a view-only outside link (guest access 'read', 2026.4.8).
- The 'workspace_guest' `destination_type` needed `<> 'workspace_guest'` in about ten places.
- Shown again: `ws_calendar_feeds` keep `token_hash` and an encrypted `token_secret`, a new address ends the old; product feeds alike (`pg_product_feed_token()`). Guest tokens show once. Signed: `pg_fs_token()`.

## What counts a password's failures?
- `pg_login_throttle_guard()` only refuses a locked subject; `pg_login_record_failure()` counts; both are off with the throttle (`pg_login_throttle_enabled()`). The address bucket is the sign-in's (`pg_login_throttle_subjects()`): wrong passwords there lock the address out of sign-in.

## API and MCP?
- A new API route is an MCP tool unless `'mcp' => false` (`pg_mcp_route_is_offered()`). Flow publish has it and `ws_api_own_words_only()` (workspace/workspace-flows-store.md).

## Flows, notes, designer review?
- `ws_flow_access()`: edit for the owner while staff (`ws_can_schedule()`, role < 3), run for readers of a live flow's public or private home channel; runs use the owner's rights (plans/workspace-flows.md). No shares.
- `ws_note_shares`: person or channel, no link; a channel share is view only, `can_edit` only for one person.
- Designer review: view mode and node notes (`props._notes`, `designer/note_save`; designer/designer-collab-view-mode.md).
