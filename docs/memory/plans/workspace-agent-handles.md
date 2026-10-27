---
name: workspace-agent-handles
description: Plan (2026-10-21, step 1 done, answers open): each askable agent gets its own @handle, name, avatar and channel permission: outside apps, MCP agents, own-key model assistants such as @chatgpt
---

## What is asked? (owner, 2026-10-21)
- Third-party agents such as ChatGPT can be connected, but the Workspace side is unfinished: a connected one is called with its own tag, such as `@chatgpt`.

## What is there today?
- Asked by name: `@Claude` (routine), `@ai` (Pinegrap AI), MCP aliases (`ws_mcp_boot()`; an agent answers on its own).
- An outside application tagged `<@app:N>` hears it on its webhook (#809), reads with its own name and is offered by the picker (step 1), but reads with its owner's reach and no channel can allow or refuse it.
- An AI app connected as an MCP client (ChatGPT, Claude, Cursor: connected_apps.php) cannot be called from the site: it acts only while somebody talks to it there. A real `@chatgpt` needs the site to call the model with a key, or a service that answers mentions.

## What is proposed? (not approved yet)
- `ws_assistants()` (delegation.php, today for handing tasks over) grows into the one registry of askable agents: kind (`claude`, `ai`, `mcp`, `app`, `model`), handle, name, avatar, application id, ready, per-channel permission, where it answers (site or webhook). The picker, tags, `ws_message_ask_assistants()`, the discussion follow (plans/workspace-agent-discussion-follow.md) and the settings read it.
- Being asked is the invitation for every kind (PR #1085). A private channel only where its manager allowed that agent; the question names the provider ("…will be sent to OpenAI").
- Prompts, briefing: plans/ai-agent-prompts.md.

## Slices
1. No schema: an application's tag reads with a handle from its own name; the picker lists applications a mention reaches. Reach stays the owner's until 2.
   - 1a done (#1885, workspace-app-handles.md); 1b (picker) done (#1975).
2. Registry and permission (schema, ask first): a table of agents an administrator adds (kind, handle, name, app id, provider, model, sealed key, status; keys `app:<id>`, `model:<id>`) and each channel's choice per agent; then an outside app hears a mention by invitation where the channel allows it (`ws_app_reads_channel()`). `claude_access`, `ai_access` stay.
3. Model assistants with the site's own key: OpenAI-compatible chat APIs (OpenAI, Gemini, Mistral, DeepSeek, OpenRouter with PKCE sign-in (plans/reverse-oauth-leftovers.md), Azure OpenAI, a custom address such as Ollama) answered by the Pinegrap AI engine (`ws_ai_step()` with that address, key and model): the same tools, proposals and approvals. A card each: handle (`@chatgpt`), name, avatar, sealed key, model, test. `ws_ai_requests.agent` `model:<id>`.
4. Application membership endpoints (item 1 of plans/workspace-phase3-leftovers.md): add or remove an application in a channel; only a member adds one to a private channel.
5. MCP agents get an avatar and their name in tags. Each slice writes its memory.
   - 5a done (#2112, workspace-mcp-agent-identity.md): one avatar and icon per `mcp:<id>`, server.
   - 5b done (#PRNUM): the @ picker draws the server's avatar/icon, the task's delegate tag its icon (workspace-mcp-agent-identity.md).

## Open questions (recommendation)
1. Which first: (a) model assistants, `@chatgpt` answers inside the site, (b) outside apps on the webhook, (c) both → step 1, then a.
2. Licence: (a) model assistants on every site, (b) Premium only, like Pinegrap AI → owner.
3. A handle equal to a person's name: (a) handles win, the picker shows the kind, (b) refused on save → b.
4. Default in public channels for a new provider: (a) allowed, like Claude and Pinegrap AI, (b) off until a manager allows → a.
5. An OAuth-connected app's messages as "ChatGPT (via Ayşe)": plans/claude-code-channel.md C5.

## Where to start?
- Step 2 needs the schema and the owner's answers. Before step 3 recheck each provider's API, tool-call format and data terms.
