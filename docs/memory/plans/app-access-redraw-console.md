---
name: app-access-redraw-console
description: Plan app-access-redraw, step AA7 (U15 to U18): a Claude Console agent as a channel agent: the key as one token, its own routes as MCP tools, the header form, waking on a schedule
---

## What did the reading say?
- The research agent read Anthropic's pages on 2026-11-20 (#10731): the product is "Claude Managed Agents", in beta. An MCP server is named by name and address; its credential comes from a vault as one bearer token or a supplied OAuth access token. No header name is chosen there, no HTTP Basic, and no sign-in run by Anthropic was found.
- The sandbox has a shell and curl; its network reaches only the hosts allowed for it. A vault's secret is put into a request only when sent word for word, so a Basic header built from it is not known to work; the two headers `X-Pinegrap-Key` and `X-Pinegrap-Secret` are.
- A session is started or woken only through Anthropic's API with an Anthropic API key; nothing calls in. A scheduled deployment runs the agent at set times.

## What is built?
- (U15) The key as one token (step AA4) is what a vault's bearer entry carries. The application's own key acts as the application, so the agent's call reads as the agent, never as a person.
- (U16) The agent's own routes are MCP tools only for an active application agent's own key: the instructions, the request list, claim, brief, answer, fail, progress, report and the wake schedule. An OAuth connection, a device session and a key with no active agent never see them; a test holds each. They carry `'mcp' => false` today because a person's connection must not answer as the agent.
- (U17) The agent's instructions and the start text name a second sending form beside `-u`: the two headers, or the single token, for an environment where a secret is put in only when sent word for word. `-u` stays the default. The instructions' version changes.
- (U18) The guide "Claude Console" in `api_app_guides()`: the vault entry, the MCP server's address, the site's host among the allowed hosts, the start text as the agent's system prompt, the scheduled run as how it wakes. Its first line says it follows Anthropic's pages as read on that date and that the product is in beta. An OAuth connection is not offered there: it acts as the person.

## How does a Console agent wake?
- Owner (2026-11-20, poll 182): on a schedule (`workflow/decisions-made-workspace-43.md`). Each run reads the request list, takes what waits, answers and ends. The site keeps no Anthropic key and calls no Anthropic address; no third door form is added.
- The guide says the delay plainly: a call waits in the queue until the next run.

## What binds the step?
- `includes/mcp/tools.php` is entered by AG7 and AG9 of `plans/ai-guide.md`, `includes/workspace/agent_instructions.php` by AW1 of `plans/agent-writing-rules.md`: AA7 enters neither while such a PR is open.
- Nobody but the owner can try it with a Console agent: the PR says so under "Could not verify", and AA8 closes the plan only after his try on dev.
