---
name: designer-prototype-share-links
description: Share links of a design's prototype (prototype_links, 11.0.35): makers, audiences, what anyone sees, the password lock, the token kept out of logs and the frame, uses, revoked with the design
module: prototype_links
---

## What is a share link?
- A `prototype_links` row (`includes/fn/prototype_links.php`): design, start page (0 or no longer a draft: the first draft), token hash (unique), token sealed with the site key (`pg_proto_link_seal()`) so the address shows again, hint (6 characters). Address `prototype.php?link=<token>`; not `token` (validate_token_field() reads it). Drafts as last saved, scheduled too.
- Revoked with its design: every style delete path calls `pg_proto_links_revoke_design()` (a later design may get the id: InnoDB on MySQL 5.7 after a restart).

## Who makes, lists and revokes them?
- Roles 0-1: tab menu "Share prototype" (`_pgPrototypeShareDialog()`); designer sub-actions `prototype_link*` (not in `$content_ok`); API `GET|POST /design/designs/{id}/prototype-links`, `POST …/{link_id}/revoke` (designer owner, dry run, `'mcp' => false`). The API gives the address on create (and its replay) only; the password untrimmed (`api_design_prototype_link_password()`). A list, boolean, NUL byte or spaces alone are refused.
- Site Log names a link `#id (hint…)`, never the token.

## Who opens one?
- `anyone`: no sign-in, expiry required (at most 90 days). `team`: signed-in role 0-2, expiry optional; signed-out go to sign-in, role 3 gets 403.
- An anyone link draws a draft only when its folder on the site (`pg_page_draft_publish_folder()`) is public or guest (`pg_proto_link_page_shown()`), else a note: no folder gate runs in the frame, and record widgets would list what the folder keeps from visitors.
- Revoked, expired, gone and unknown: one 404, "This link no longer works."; only a guess (unknown, token-shaped) counts a WAF offence: a revoked token would ban those it was given to on each click.

## How does the password work?
- `password_hash()`; failures on the row, not the sign-in throttle (its address bucket would lock the office out). Each try is counted first by one conditional UPDATE (`pg_proto_link_attempt_sql()`: only while unlocked; the 10th locks 15 minutes at once, past 10 each wrong one relocks), so a burst gets no extra checks. Locks and tries while locked count an offence (429). A token holder can keep it locked: revoke, make a new link.
- A right one clears the count and sets `pg_proto_pass_<id>` (path prototype.php, HttpOnly, Lax, 12 hours, HMAC over id, password hash, expiry). Not in `pg_consent_catalogue()`.

## How is the token kept out of logs?
- prototype.php loads the module before init.php (gate `PG_PROTOTYPE_ENTRY`) and writes `link=***` into every copy of the address (`pg_proto_link_redact_server()`). Secure Mode's redirects use `PG_SECURE_MODE_REDIRECT_URL` (the address as it came). Not covered: the web server's access log; a team link's sign-in `send_to`.
- API create's Idempotency-Key record: `url` null (`'idempotency_redact'`), read back from the row on a replay (`api_design_prototype_link_restore()`: owner checked again; null when the key cannot read it); password HMAC-keyed (`'secret'`).

## How is the frame admitted?
- Signed by link id, never the token; its clicks, new tabs and a page that fails: designer-prototype-share-link-frame.md.

## When is a use counted?
- `uses`, `last_used_at` on a human GET (`waf_classify_bot()`) of the shared address, once per session; never HEAD, a bot or the password form.
- Not on a move inside (frame click, bar's list, previous/next: `page` named, `pg_proto_link_counts_use()`): each is a new GET, and with cookies refused every one counted. A copied `?link=…&page=N` counts nothing.

Related: docs/memory/designer/designer-prototype-share-link-frame.md, docs/memory/designer/designer-prototype-viewer.md, docs/memory/plans/prototype-sharing.md
