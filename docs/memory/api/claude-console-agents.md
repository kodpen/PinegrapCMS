---
name: claude-console-agents
description: Claude Console's agents (Claude Managed Agents) as Anthropic's pages state them (read 2026-11-20): MCP and vault credentials, the sandbox, waking a session, long runs; what was not found
---

## What is the Console's agent product?
- "Claude Managed Agents" (`platform.claude.com/docs/en/managed-agents/overview`, read 2026-11-20); every endpoint needs `anthropic-beta: managed-agents-2026-04-01`. The Console builder is beta and "walks you through each field of an agent definition"; the form's own field list is not on the pages (not found).

## What does it ask to connect an MCP server?
- The agent names a server by `type` `url`, `name` and `url`: "You don't provide authentication tokens at this stage" (`/mcp-connector`). Session creation names vaults; a credential is matched by the server's URL, and "If none matches, the connection is attempted unauthenticated".
- A vault's MCP credentials (`/vaults`): `static_bearer` (`mcp_server_url`, `token`: "a fixed bearer token (API key, personal access token, or similar)") or `mcp_oauth` (`access_token`, `expires_at`, optional `refresh` with `token_endpoint`, `client_id`, `refresh_token`; `client_secret_basic` is for the refresh call only).
- Not found: a header name to choose, HTTP Basic for an MCP server, a sign-in run by Anthropic (the token is supplied), an application identity. "A vault is the collection of `credentials` associated with an end user."

## Why did the owner's try fail on a Pinegrap site?
- `api_authenticate()` (`includes/api/auth.php`) reads any `Authorization: Bearer` as a person's token (team devices session or OAuth); an application's key and secret come only as HTTP Basic or `X-Pinegrap-Key` / `X-Pinegrap-Secret`. So `static_bearer` cannot carry an application, and an OAuth token is a person: the owner's `@console` entered as the owner (2026-11-20).

## Can the agent run curl with a key itself?
- Cloud sandboxes are Linux containers with a Bash tool and curl (`/overview`, `/cloud-sandboxes-reference`). Networking `limited` (the Console form starts there, no host allowed) or `unrestricted`; hosts by `allowed_hosts` (`/environments`).
- A vault `environment_variable` (`secret_name`, `secret_value`, `networking.allowed_hosts`, `injection_location` header/body): the sandbox holds "an opaque placeholder" "substituted with the real secret at egress"; it works "for clients that send the secret value verbatim". `X-Pinegrap-Key` / `X-Pinegrap-Secret` send them verbatim; HTTP Basic (base64) is not on the page. Not tried.
- A plain environment-variable setting for the sandbox: not found.
- Custom tools: the agent emits `agent.custom_tool_use` and the caller's own code answers `user.custom_tool_result` (`/reference`).

## Can the site start or wake a session?
- Only through Anthropic's API with an Anthropic API key (`x-api-key`): `POST /v1/sessions` (`initial_events` starts the loop); `POST /v1/sessions/{id}/events` with a `user.message` resumes an idle one (`/sessions`, `/events-and-streaming`). Scoped keys: not found.
- Webhooks only go out: Anthropic calls an HTTPS address on port 443 on session, vault and deployment events, three tries, "aren't a durable log" (`/webhooks`). An inbound hook that wakes a session: not found.
- Cron: scheduled deployments, minute grain, jitter up to 15% of the interval and 9 minutes; `POST /v1/deployments/{id}/run` runs one now (`/scheduled-deployments`).

## Can it stay open and poll a queue?
- "Tasks that run for minutes or hours" (`/overview`); an idle session waits for input; a `budget` cap pauses it (`budget_reached`). A maximum run time, an idle timeout or a sleep loop: not found.

Related: docs/memory/api/claude-connector-listing.md, docs/memory/api/api-mcp-server.md
