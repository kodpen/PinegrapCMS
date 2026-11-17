---
name: oauth-client-automation-platforms
description: Whether one OAuth client could serve an automation platform on every site (read 2026-11-17): the client metadata document a site reads, what Zapier, Make, Pabbly and n8n allow, why it is not used
---

## Could one client be known to every site?
- Yes, with no broker and no change here: a client may name itself by the https address of a client metadata document (`pg_oauth_client_id_is_url()`, `pg_oauth_client_resolve()` in `includes/oauth/`; `api-oauth-server.md`). One document at an address Kodpen keeps would be the same client on every site.
- The document must name its own address as `client_id`, describes a public client (no secret, `token_endpoint_auth_method` none) and lists one to ten return addresses, matched exactly; a return address may be on another host than the document.
- A site reads it only on the authorization screen, for a person signed in to the panel, keeps the copy a day and a stale one a week. Such a client signs in with PKCE (S256), and its refresh token turns over on every use.

## What do the platforms allow? (their pages, 2026-11-17)
- Zapier: one client ID and one return address for an integration, PKCE built in (`enablePkce`), a form before the authorization address for a "site URL for self-hosted apps"; the authorization and token requests are the developer's own. Its pages warn that a domain a user types receives the client secret; a public client sends none.
- Make: the return address `https://www.make.com/oauth/cb/app`, the authorize and token requests written by the developer, an example with PKCE whose token request carries no secret, parameters asked of the user.
- Pabbly Connect: the return address `https://connect.pabbly.com/callback-url`, a type "OAuth 2.0 (Authorization Code with PKCE)", the secret on the token call optional; whether the authorization address may hold the user's domain is not stated.
- n8n: no. The user types a Client ID and a Client Secret, and the return address is the instance's own (`http://localhost:5678/rest/oauth2-credential/callback` in its example).

## Why is it not used?
- An OAuth connection never holds `webhooks:manage` (`pg_oauth_ceiling()`; architect 2026-11-17, agent channel message 7524): an app signed in this way could not subscribe, so it would have no instant trigger. And the OAuth server is off until an administrator makes the site's OAuth application.
- So the automation apps sign in with an application's key and secret (`plans/automation-platforms.md`).
- Untried: nothing above was run against a platform.

Related: docs/memory/api/api-oauth-server.md, docs/memory/api/claude-connector-listing.md
