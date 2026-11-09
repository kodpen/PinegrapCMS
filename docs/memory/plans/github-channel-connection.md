---
name: github-channel-connection
description: Plan (owner 2026-11-08): connect GitHub with OAuth from Settings › Connections, the site sets its own webhook on chosen repositories and writes CI, pull request and label lines into a channel
---

## What is it for?
- A site owner connects a GitHub repository to a Workspace channel without secrets or a workflow file: "Connect GitHub", pick the repositories and the channel, done (owner 2026-11-08, `workflow/decisions-made-workspace-9.md`). Today this repository's CI writes `[ci]` / `[ready]` lines with four repository secrets (`workflow/agent-channel-experiment.md`); that setup is too hard for users.
- The Workspace's GitHub MCP connector is no fit: it reads repositories with a personal access token and receives no events (`workspace/workspace-mcp-servers-card.md`).

## How does it work?
- Built in G1 and G2: the GitHub provider, model A, scopes: `api/api-connected-accounts-github.md`; links, webhook, receiver, lines, card: `api/api-github-channel-links.md`. Model B later goes through `connect.pinegrap.com` with Kodpen's one GitHub app, so the user registers nothing (`plans/reverse-oauth-leftovers.md`).

## Steps (claim as `[plan:github-channel-connection] G<n> — <topic>`; after A4 of `plans/agent-channel.md`)
- Done: G1 #8247 (provider, model A), G2 #8271 (links, webhook, receiver).

- G3 (S) Model B for GitHub once the broker exists.

- Then this repository's CI moves to the connection and the four `PG_WS_*` secrets and the `channel` jobs can go (asked first: the merging session reads those lines).

## Open questions (asked when the step is claimed)
- A GitHub App (fine-grained, one central webhook address, a Kodpen relay) instead of an OAuth app, for model B.
