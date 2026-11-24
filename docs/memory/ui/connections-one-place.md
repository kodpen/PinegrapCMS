---
name: connections-one-place
description: Connections is the one place an outside service is set up (owner polls 146, 148, 149, 222): what moved there, what stays on Site Settings, how a further move is done, the build's steps and PRs
module: connections_screen
---

## What is the rule?
- Everything typed to connect to an outside service (key, secret, token, account, address) sits on that service's page on Connections: connect, test, disconnect, where it is used. Owner decisions: `workflow/decisions-made-product-76.md` (poll 146), `-77.md` (poll 149, the sheet), `-100.md` (poll 222, Claude and MCP).
- A feature card holds no key: it shows the connection it uses and leads to it ("Manage in Connections"); its own settings (switch, model, texts) stay on the card.
- Exception (poll 148): the sending service's key and the SMTP server stay in Site Settings › E-mail sending (`mail_sending`); the Connections list folds the seven mail routes into one "E-posta gönderimi" row whose Manage opens that category, and each route's name still finds it in search.

## How is a further service moved?
- Its page is `connections.php?service=<key>` with the cards of `connections-service-page-layout.md`; a page needing what that layout does not show goes to the architect first.
- In the same PR: the registry row's `place` changes (`api/connections-registry.md`); the old card keeps its address and anchors through a `pg_settings_legacy_anchors()` row (`workflow/decisions-made-product-14.md`); the old card becomes a line leading to Connections.
- Each section keeps the gate of the place it came from; nobody gains or loses a right by a move.
- No table and no column without the architect; the connected accounts' rules stay (`api/api-connected-accounts.md`). A merge adds no service to `pg_connections_count()`.
- A shared provider key: one connection per provider, moved by an idempotent data step (`api/ai-connections.md`).

## Which steps built it? (all landed by 2026-11-24)
- CX2 voice card to Assistants and agents #10332; CX3 E-mail sending: logic #10334, category #10404, campaign texts and MailChimp #10434.
- CX7 the service page frame #10400; CX8 GitHub #10418 (`connections-service-page.md`); CX9 AI providers #10470, #10493 (`api/ai-connections.md`, `api/ai-connections-feature-cards.md`); CX10 Google and Microsoft #10512, #10529 (`connections-provider-page.md`).
- CX11, the census's groups: SEO #10553, Visitors #10562, Languages #10576, Communication #10621, SMS #10686, Mailboxes #10720, Accounts and sign-in #10785, payment #10919 #10973, shipping #10988, address and Merchant Center #11007, Accounting #11030, Google reviews #11117, backups #11193, chat targets #11215, Workspace check #11244, Claude #11265, MCP #11284.
- Each group's page file is `connections-<group>-page.md` in this folder, listed in `connections-service-page.md`.

## What was left out on purpose?
- Cron Jobs: nothing to move. S3, FTP, object storage: no outside service of the registry.
- A server added by its address keeps its settings in the MCP servers drawer (`connections-mcp-connector-page.md`).
- One-click connect through a broker waits for the owner's decision on Google's verification (`plans/reverse-oauth-leftovers.md`); plan `github-channel-connection.md` is separate.
- The names "Connections", "Connected accounts", "Connected applications" (CX5) wait for the pages; the public figure's wording stays the owner's (`workflow/decisions-made-product-69.md`).

Related: docs/memory/ui/connections-screen-layout.md, docs/memory/ui/connections-service-page.md, docs/memory/api/connections-registry.md
