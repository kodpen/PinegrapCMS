---
name: designer-prototype-share-links
description: Share links of a design's prototype (prototype_links, 11.0.35): who makes them, audiences, expiry, the password's own lock and cookie, the token kept out of logs, the link frame, uses, API without MCP
---

## What is a share link?
- A `prototype_links` row (`includes/fn/prototype_links.php`): design, start page (0 or one no longer a draft: the first draft), token hash (unique), token sealed with the site key (`pg_proto_link_seal()`, as calendar feeds) so the address shows again, hint (first 6 characters). Address `prototype.php?link=<token>` (`pg_proto_link_url()`); not `token`, which validate_token_field() reads as the form token. It shows the drafts as last saved, scheduled ones too.

## Who makes, lists and revokes them?
- Roles 0-1, like the Schedule section: tab menu "Share prototype" (`_design.prototypeShare`, `_pgPrototypeShareDialog()`); designer sub-actions `prototype_links`, `prototype_link_create`, `prototype_link_address`, `prototype_link_revoke` (not in `$content_ok`; `pg_proto_links_panel()` checks the role again); API `GET|POST /design/designs/{id}/prototype-links`, `POST …/{link_id}/revoke` (design scopes, designer owner, dry run, `'mcp' => false`: no AI app mints a public link). The API gives the address on create only.
- Site Log: shared (anyone or team), revoked, password locked; named `#id (hint…)`, never the token.

## Who opens one?
- `anyone`: nobody signs in; an expiry is required, one of `pg_short_link_durations()` (at most 90 days). `team`: a signed-in role 0-2, expiry optional; the signed-out go to sign-in with a `send_to` built from the token, role 3 gets 403.
- Revoked, expired, gone (design deleted or no drafts) and unknown answer one 404 page, "This link no longer works."; unknown and revoked count a WAF offence (`pg_proto_link_offence()`, rule `prototype-link`).

## How does the password work?
- `password_hash()`; wrong answers counted on the row, not in the sign-in throttle (its address bucket would lock the office out of sign-in, and it has its own switch). 10 in a row lock it 15 minutes; past 10 each wrong answer locks again. A lock and every try while locked count an offence (429, Retry-After). A right one clears the count and sets `pg_proto_pass_<id>` (path prototype.php, HttpOnly, SameSite=Lax, Secure on https, 12 hours): expiry and an HMAC over link id, password hash, expiry. The form carries get_token_field(). Not in `pg_consent_catalogue()`: never set on a site page.

## How is the token kept out of logs?
- prototype.php loads the module before init.php (second gate `PG_PROTOTYPE_ENTRY`) and writes `link=***` into REQUEST_URI, HTTP_X_REWRITE_URL, QUERY_STRING and the rest (`pg_proto_link_redact_server()`): waf_log, firewall.log, the error log and REQUEST_URL never see it. Not covered: the web server's own access log; Secure Mode's http to https redirect (links carry URL_SCHEME); a team link's sign-in `send_to`.

## How is the frame admitted?
- `pg_proto_link_frame_sign()`: design, page, sub, record, link id, expiry, prefix `prototype-link-frame:` (person mode `prototype-frame:`: one never opens as the other). The address carries `l` and the token (the frame's links need it); the frame checks the token's hash against the row and that the link still opens, instead of a person's role. The rest is the person mode's.

## When is a use counted?
- `uses` and `last_used_at` on a human GET of the viewer (`waf_classify_bot()`), once per session; never a HEAD, a bot or the password form.

Related: docs/memory/designer/designer-prototype-viewer.md, docs/memory/plans/prototype-sharing.md
