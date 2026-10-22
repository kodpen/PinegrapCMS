---
name: api-oauth-client-compat
description: What keeps real OAuth/MCP clients (Cursor, VS Code, Gemini CLI, Codex, mcp-remote, Claude) connecting: lenient registration, app schemes, loopback matching, unknown scopes, discovery, panel guide
---

## What must a rule change keep working?
- The real client bodies in `tests/oauth_test.php` (VS Code with its device code grant, Cursor's own scheme, Gemini CLI, ChatGPT's bare registration) and its redirect cases (claude.ai, vscode.dev, loopback with and without a port). Keep them passing when changing a rule; Codex and mcp-remote are served by the same rules.

## How lenient is registration? (RFC 7591, `pg_oauth_registration_validate()`)
- Extra `grant_types`/`response_types` are not refused: the client is registered for what the server has (`authorization_code` with `refresh_token`, response type `code`), and the answer says so. Only a body without `authorization_code` or `code` is refused.
- Auth methods `none`, `client_secret_basic`, `client_secret_post`; one to ten redirect addresses; ten registrations per address per hour.

## Which redirect addresses are accepted, and how are they matched?
- https anywhere, plain http only to the loopback, or a private app scheme, which may be a single word (`cursor://`, `vscode://`, `kiro://`) rather than a reversed domain; no fragment, no user part, no dangerous scheme (`pg_oauth_redirect_uri_is_acceptable()`). PKCE is what keeps a claimed scheme safe.
- Matched as exact strings, except a loopback http address, compared by path and query alone: its port and which loopback name it uses are ignored (`pg_oauth_redirect_uri_matches()`, RFC 8252).

## What about scopes the site does not have?
- `openid`, `offline_access` or a client's own word are passed over on the consent screen (`pg_oauth_scope_requested()`); a request naming no known scope lists everything available and the person chooses.
- On refresh unknown names are ignored the same way; asking for more than was granted is 400 `invalid_scope`.

## Where do clients find the server?
- `router.php` answers `.well-known/oauth-authorization-server`, `.well-known/openid-configuration` (the only name reachable for a site installed in a folder; it claims nothing OpenID Connect adds) and `.well-known/oauth-protected-resource/<path>`, each through `oauth.php`.

## Where is a new client documented?
- The panel guide `api_settings_oauth_guide()` (`api_settings.php`) gives each client's steps and the text to paste, with this site's address in it; a new client gets an entry there. The MCP file address in the software folder comes first, since it works on every server.
