---
name: github-channel-connection
description: Plan (owner 2026-11-08): connect GitHub with OAuth from Settings › Connections, the site sets its own webhook on chosen repositories and writes CI, pull request and label lines into a channel
---

## What is it for?
- A site owner connects a GitHub repository to a Workspace channel without secrets or a workflow file: "Connect GitHub", pick the repositories and the channel, done (owner 2026-11-08, `workflow/decisions-made-workspace-9.md`). Today this repository's CI writes `[ci]` / `[ready]` lines with four repository secrets (`workflow/agent-channel-experiment.md`); that setup is too hard for users.
- What exists is no fit: the Workspace's GitHub MCP connector reads repositories with a personal access token and receives no events (`workspace/workspace-mcp-servers-card.md`); connected accounts have no GitHub provider (`api/api-connected-accounts.md`).

## How should it work?
- A GitHub provider in connected accounts (`includes/connect/providers/github.php`, a purpose for channel links). Model A first: the site owner registers a GitHub OAuth app (a short setup page); model B later through `connect.pinegrap.com` with Kodpen's one GitHub app, so the user registers nothing (`plans/reverse-oauth-leftovers.md`).
- A link: repository → channel, the events (CI result, pull request opened/merged/closed, a label such as `ready`). On save the site creates the repository's webhook pointing at itself with a secret of its own (sealed); removing the link or the account deletes the hook.
- A root receiver (as `carrier_webhook.php`, `mail_webhook.php`) checks `X-Hub-Signature-256`, finds the link and writes the line as the "GitHub" application, in today's formats (`[ci] green|red #<n> <branch> <sha7> … <run url> <title>`, `[ready] #<n> …`) so wake rules on those prefixes keep working. Idempotent per delivery id; failures on the link's card.
- Who may link: who may post in the channel and manage connections; a private channel only by a member.

## Steps (claim as `[plan:github-channel-connection] G<n> — <topic>`; after A4 of `plans/agent-channel.md`)
- G1 (M) GitHub provider and purpose (model A), setup page, connect and disconnect; no schema. Done (#PR): `channel_links` purpose, `api/api-connected-accounts-github.md`.
- G2 (M, schema) Links, webhook creation and removal, the receiver, the lines, the card. Schema asked when claimed (a link table or rows in an existing one).
- G3 (S) Model B for GitHub once the broker exists.
- Then this repository's CI moves to the connection and the four `PG_WS_*` secrets and the `channel` jobs can go (asked first: the merging session reads those lines).

## Open questions (asked when the step is claimed)
- Scopes (decided in G1, least privilege): only `admin:repo_hook`; public repositories may be listed, a private one is typed as `owner/name` (no `repo` scope).
- A GitHub App (fine-grained, one central webhook address, a Kodpen relay) instead of an OAuth app, for model B.
