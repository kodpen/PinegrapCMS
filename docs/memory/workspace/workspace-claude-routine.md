---
name: workspace-claude-routine
description: "@Claude" in Workspace channels through one site-wide claude.ai routine: how it runs, limits, setup on claude.ai, security rules, what was never tested live
---

## How does @Claude in a channel work?
- `includes/workspace/claude.php`. Someone writes `@Claude` (the `<@app:N>` tag of the Claude application) or `/claude …` in a channel where Claude is allowed. The request waits in `ws_ai_requests`; the site fires the one site-wide claude.ai routine through its API trigger; the routine runs in Anthropic's cloud, reads the queue through the external API with the application's key, answers each request as the application and stops when the queue is empty.
- The trigger carries only the site address, so a leaked trigger token can start a run and nothing more. One run serves every waiting request; a new run starts only when none is busy.
- Request state is shown on the request (queued, sent, working, answered, failed) with 👀 / ✅ from the application. Proposed tasks and record changes wait as drafts until someone in the channel opens or dismisses them: Claude never assigns work or writes records by itself.
- Constants: a run silent for 15 min is given up (`WS_CLAUDE_STALE`), a request not started in 24 h is cancelled (`WS_CLAUDE_QUEUE_TTL`), at most 5 waiting requests per person, beta header `WS_CLAUDE_BETA` (`experimental-cc-routine-2026-04-01`).

## Which scope decisions bind?
- Uses the owner's Claude subscription (routine + API trigger), not a Console API key. One connection for the whole site: every call counts against the daily allowance of the admin who set up the routine, and sessions and connector actions appear under that person.
- Claude reads records only as far as the application key allows; it writes records only through approved drafts. No delete endpoint.
- Private channels are off by default (`ws_channels.claude_access`): their content goes to Anthropic under the admin's account, so the channel owner must turn it on.
- The routine token is stored encrypted in `config.ws_claude_token`; the settings card never shows it back.

## What limits come from Anthropic?
- Daily runs (April 2026, may change): Pro 5, Max 15, Team/Enterprise 25; API triggers count. With usage credits on, extra runs are billed.
- Each trigger opens a new cloud session: answers take 1–3 minutes. It is "hand over a job", not chat.
- The `/fire` endpoint is a research preview; breaking changes come with a new dated beta header.
- The site must be reachable from the internet (no localhost); the WAF must not block Anthropic's egress (it blocked cURL on dev once).
- API credential proxy (key never visible to the session) exists only on Pro/Max; on Team/Enterprise the key sits in an environment variable every user of that environment can read.

## How is the routine set up on claude.ai?
- Admin: claude.ai/code/routines → New routine (Cloud), prompt copied from the "Claude in channels" card in Workspace settings, a model; environment with Custom network allowing the site's domain and the API credential (header `Authorization`, `Basic base64(key:secret)`); remove all connectors; Trigger → API → generate token, paste URL and token into the card. The owner enters every credential.
- App Access: a "Claude" application with Workspace + Tasks read/write and the record types to read.

## What was never tested with a real routine?
- On dev (owner + Claude): two requests in one run, a prompt-injection attempt, the private channel gate, and waiting when the daily allowance runs out (HTTP 429). On 2026-09-23 these were only simulated on localhost.
- A live Claude Code session as a second connection type is a shelved plan: docs/memory/plans/claude-code-channel.md
