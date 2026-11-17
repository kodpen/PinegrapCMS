---
name: integrations-census-machinery
description: Census (2026-11-17) of what an outside program can do with a site: API size, webhooks as REST hooks, the OAuth and MCP servers, no address shared by all sites, what an automation app would miss
---

## How big is the API?
- Counted from the route tables with every module on, `development` at f1586738: 668 routes on 528 paths (core 342 rows, ERP 84, Workspace 285, translations 13; 724 rows with the POST twins of PATCH and DELETE), 60 scopes in 36 groups.
- Three callers: an application's key and secret over HTTP Basic, a team device's token, an OAuth connection's token. Cursor paging, `updated_since` on some lists, 120 calls a minute for each application, `Idempotency-Key` on writes, a dry run on 433 routes.
- OpenAPI 3.0.3 at `integration.php/openapi.json`, built from the route table: it names Basic and Bearer only, has no `oauth2` block and no webhook payloads. Without credentials: `/.well-known/pinegrap.json`.

## Can a platform subscribe to events by itself?
- With a key, yes: 137 events (76 core, 32 ERP, 29 Workspace; `order.created` …). `POST /webhooks` makes a subscription of up to 20 events and answers its secret once, `DELETE /webhooks/{id}` ends it; deliveries are signed and tried six times.
- With OAuth, no: `pg_oauth_ceiling()` takes `webhooks:manage` out of every OAuth connection.
- No sample payload for an event, only `webhook.test`; no log of delivered events; the panel cannot make a subscription.

## What does the OAuth server give?
- Authorization code with PKCE and refresh tokens (one hour, thirty days), metadata under `/.well-known/`. A client registers itself at `oauth.php/register` or names a client-ID metadata document; nobody registers it beforehand.
- It is off until an administrator makes the site's one "Connected apps (OAuth)" application in Application Access: until then every endpoint answers 404.

## And the MCP server?
- `mcp.php`, Streamable HTTP: the API's routes as tools, up to 562 (260 of them reads), each with a title, `readOnlyHint` and `destructiveHint`. Callers as the API's.

## Is there one address for all sites?
- No. Every site is its own server on its own domain, and nothing central knows a third party: `connect.pinegrap.com` is in two plans and in no code. A third party needs the site's address first; the rest is found from it.
- The Claude plugin bundle (`claude-plugin-bundle.md`) lives with this by asking for the address; a hub server is not decided.

## How does the product reach out?
- Connected accounts: each site registers its own OAuth application at Google, Microsoft, Dropbox or GitHub and pastes its ID and secret; one vendor application through a broker is not built.
- A flow has "Call a webhook" (POST, three fixed JSON shapes), "Check a web address", "Run a tool of an MCP server", mail, SMS and WhatsApp boxes, and no general HTTP request box; it starts from outside by "When a web address is called" or `POST /workspace/flows/{id}/runs`.

## What would an automation platform's app still miss?
- Instant triggers under OAuth (the ceiling above); or the app signs in with a key and secret.
- A sample of each event's payload and a newest-records list for each trigger, which the platforms' editors ask for.
- A switch a site owner finds: the OAuth server is off by default.
- What each platform asks: `integrations-census-platforms.md`.
