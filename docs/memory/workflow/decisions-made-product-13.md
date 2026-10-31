---
name: decisions-made-product-13
description: Decisions list, product part 13 (2026-10-31 on): Site Settings group headings, no notes secret feed, no hint on forgot password, IIS preflight nudge
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## Does the Site Settings list get group headings? (#3879)
- Decided (architect, 2026-10-31, settings recategorization R8): yes, four, drawn like the main menu's (`.pg-sm-group` label, not a link, no collapse, only over categories the reader may open, also over a single one; a group with no row is skipped): Site (General, Appearance, Features, SEO and sharing, Visitors and privacy, Languages and translation, Communication), Security (Accounts and sign-in, Firewall and HTTPS), Selling (Ecommerce, Accounting), Running the site (Integrations, Updates and maintenance, the System tools row). The groups follow today's list order, so no category moves and no `#settings/<key>/<card>` address changes; a `group` key per category in `registry.php`, labels through `tools/tr_add.php`. Refused: a flat list (13 rows plus a link without landmarks, most of all in the phone drawer).

## Do Workspace notes get a read-only secret-address feed? (#3880)
- Decided (architect, 2026-10-31, notes sync; `workspace/workspace-notes-sync-decisions.md`): no. A note holds private text and attachment links; a bearer address pasted into another app is a long-lived secret with no scope or expiry that the OAuth revocation hooks do not cut. The calendar feed exists only because calendar apps subscribe by address; note sync apps do OAuth 2.1 and have the REST notes routes (paging, `updated_since`, revisions, the 30-day deletions list, sources, all MCP tools) and the export vault. No schema; the plan closes (N4 done: Notion #3212, OneNote #3291).

## Does the forgot-password form still show the password hint? (#4044)
- Decided (architect, 2026-10-31; follows the owner's one-message rule of 2026-10-28, `core/sign-in-one-message.md`): no. `forgot_password.php` drops the `password_hint` screen (one post sends the reset e-mail and shows the confirmation every address gets); `get_forgot_password.php` and the designed forgot-password widget (`includes/fn/widgets.php`, its `password_hint` section, renderer and designer twin together) stop reading `user_password_hint`. Kept: the `PASSWORD_HINT` setting (the hint field on the register, membership, change-password and Edit User forms), the column; no schema step. Refused: the hint in the reset e-mail (whoever reads it holds the reset link), removing the feature (a schema and data decision nothing needs).

## IIS answers CORS preflights itself: does Pinegrap change the server configuration? (#4045)
- Decided (architect, 2026-10-31): no; detect and warn. IIS's PHP handler mapping lacks the OPTIONS verb, so a browser's preflight to `mcp.php`, `oauth.php` and `integration.php` gets IIS's own 200 without `Access-Control-*` headers (PHP answers them: `includes/mcp/protocol.php`, `pg_oauth_preflight()`, `includes/api/bootstrap.php`). System Status sends one OPTIONS to the site's own `mcp.php` (`pg_curl_run()`, short timeouts, cached like the other outward probes) and, with no `Access-Control-Allow-Origin` in the answer, shows a nudge row naming the change (OPTIONS added to the PHP handler's verbs; only browser-based clients need it). Refused: a shipped `web.config` handler entry (the host's PHP `scriptProcessor` differs per server; a wrong `<handlers>` entry is a 500 on every request, `api/external-api-iis-environment.md`), static `customHeaders` (would double the header PHP sends on GET and POST), a memory note alone.
