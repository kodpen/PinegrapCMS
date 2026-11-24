---
name: share-links-api
description: Share links of agent channel items (SL1, SL2): ws_share_links (11.0.275), make, find, state, take back, sweep, the API routes and MCP tools, the workspace_share.php address
---

## What is stored?
- `ws_share_links` (step 11.0.275, `upgrade_2026_11_0_ws_share_links()`): the columns of `workflow/decisions-made-schema-48.md`. `kind` is an ENUM of every kind the plan names (file, note, table, list), so later steps add no schema; `created_by_kind` is user or app.
- The token is `pg_short_link_new_token()` (43 characters), kept only as SHA-256; the shape check is `ws_approval_guest_token_shape()`.

## What does share_links.php hold?
- Pure: `ws_share_link_kinds()` (file, note, table, list), `ws_share_link_days_clean()` (1, 7, 30; nothing given is 7, anything else 0), `ws_share_link_expiry()`, `ws_share_link_state()` (revoked, then expired, then closed, else open), `ws_share_link_item_in()`, `ws_share_link_maker()`, `ws_share_link_made_by()`.
- Closed: channel gone or archived (a discussion's channel too), message gone or deleted, before the clear floor (`ws_message_in_past()`), or the item gone (a file: the message's `file_id`, with its `files` row; a note, a table, a checklist: `share-links-table-list.md`, `share-links.md`).
- `ws_share_link_make_check()` is shared by the API and its dry run (the panel in SL3): an agent channel or a discussion of one, `ws_can_post_channel()`, an application key needs an allowed `ws_channel_agents` row of an app agent, the kind, the item, the days, one open link an item (refused with its end), 200 open a channel.
- `ws_share_link_find($token)` gives the row with its state for `workspace_share.php`.
- `ws_share_links_sweep()` in `workspace_recurring_job.php`: rows taken back or expired more than 30 days ago, and rows of a channel that is gone.

## What does the address answer? (SL2)
- `workspace_share.php?t=<token>`: no session (`PG_NO_SESSION`), no cookie. `ws_share_link_answer()` gives the file, or the note, table or checklist for its page, of an open link; every fault (bad shape, unknown, expired, revoked, closed, a kind not answered yet, a withheld name, bytes gone) is one 404 page with the line "This link is not valid.".
- A token no row has calls `ws_guest_offence()` (a firewall offence, as a wrong guest or approval link); a link that ended does not. The page is in `waf_sensitive_scripts()`: tries are limited by address there.
- Headers: `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer`, `Cache-Control: private, no-store`, `X-Frame-Options: DENY`, nosniff.
- A file goes by `ws_share_link_file_headers()`: `ws_file_type()` (pictures, sound, video, PDF inline; anything else, HTML and SVG too, as an attachment), the name through `pg_http_content_disposition()`, the bytes through `pg_file_reader_path()` (an object-store file streams from its copy). No Range answers.
- An open sets `opened_at` and adds one to `opens` once a minute a link at most (`ws_share_link_count_open()`, the minute asked in the UPDATE).

## Which routes are there?
- `POST /workspace/messages/{id}/share-links` (`kind`, `item_id` = the message's `file.id`, a note's `note_card.id`, a table's or checklist's number in the text, `days`): 201 with `link` and `url`, dry run; `idempotency_redact` url, so a retry answers url null.
- `GET /workspace/channels/{id}/share-links` (`all` for ended ones too): never the address.
- `POST /workspace/share-links/{id}/revoke`: the maker, or a person who manages the channel; an application takes back only its own. Each route is an MCP tool.
- Test: `tests/workspace_share_links_test.php`.
