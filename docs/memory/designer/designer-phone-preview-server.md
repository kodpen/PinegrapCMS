---
name: designer-phone-preview-server
description: "Preview on a phone" on the server (PP1, #11541): the phone link as an 8-hour share link, found again per person, ended; the changed-since address prototype.php?watch=1, its pace, bounds and one read
module: prototype_phone
---

## What is the phone link?
- A `prototype_links` row as it is (`includes/fn/prototype_phone.php`): the design's link, no password, comments or approval, living exactly `pg_proto_link_phone_seconds()` (8 hours) from its making. No column marks it: 8 hours is none of the offered durations (1 hour to 90 days), so `pg_proto_link_is_phone()` reads the span `expires_at - created_at`. `pg_proto_link_issue()` takes `created_at` so both come from one second.
- "Share prototype" lists it with `phone: true` (`pg_proto_links_panel_rows()`); the API's link summary is unchanged.

## Who makes, finds and ends it?
- Designer sub-actions, roles 0-1 (not in `$content_ok`): `prototype_phone` (style_id, page_id, audience `anyone` default or `team`) finds the person's live phone link of that design and audience again (`pg_proto_phone_pick()`, newest), or makes one starting on the page being edited (the first draft when it is no draft).
- Answer: `link` (id, audience, start_page_id, start_page, hint, expires_at, seconds_left), `url`, `qr` (inline SVG by `pg_qr_svg()`, #11578), sent with `Cache-Control: no-store`.
- A page whose publish folder is not public or guest (`pg_proto_link_page_shown()`) answers `team_only: true` (with `start_page`, its name) for anyone and makes nothing; the window offers the team link.
- A found link whose address cannot be shown again (no key, key changed) is revoked and a new one made: one phone link per person, design and audience.
- `prototype_phone_end` (link_id) revokes it: only a phone link of the asking person. The design's delete revokes it as every share link.

## What is the changed-since address?
- `prototype.php?watch=1&w=<link id>&p=<page id>&t=<start>&sig=…&b=…&since=<save time>`, built by `pg_proto_phone_watch_query()` when the viewer draws the page. HMAC under its own prefix (`prototype-phone-watch:`) over link, page and start; `b=` binds it to the phone's session cookie (`pg_proto_frame_bind()`). No token in it.
- Runs with `PG_NO_SESSION`, the cookie kept only for the binding. One read (`pg_proto_phone_watch_sql()`): the link by primary key, the page joined by its key only when `page_style` is the link's design. Writes nothing, counts no use.
- Answer `{"ok":true,"changed":…,"at":<page_timestamp>,"next":10|30,"stop":…}`: changed when saved after `since` (0: nothing drawn, never changed). `next` 10 s, 30 s after ten minutes without a save; `stop` an hour after `t` (`pg_proto_phone_watch_window()`); a new watch comes with the page drawn again.
- Unknown, revoked, expired, a link not a phone link, a page of another design, a forged or foreign-browser address: one 404 `{"ok":false}`. A forged signature counts as a forged frame does (`pg_proto_frame_offence()`).
- The page's `page_timestamp` moves only when a save changed the page; a change to the design's theme or a shared component alone is not seen.

## What does it cost, and the firewall?
- PHP side 7 µs per ask (parse, signature, answer; PHP 8, no database), 65-byte body; the read is two primary-key lookups. `prototype.php` is not in `waf_sensitive_scripts()`: only the global limit (300 a minute per address) counts it; at most 6 asks a minute per phone.

Tests: `tests/prototype_phone_test.php`. No database or browser in the session that wrote it: the watch was not run over HTTP.

Related: docs/memory/designer/designer-prototype-share-links.md, docs/memory/plans/designer-phone-preview.md
