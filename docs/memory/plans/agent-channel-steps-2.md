---
name: agent-channel-steps-2
description: Steps A5-A8 of plans/agent-channel.md: channel memory and decision marks, model assistants, risk tiers, the further doors (Claude Code plugin, per-person sessions, the chat box)
---

## Which steps are there?
- Sizes and rules as in `agent-channel-steps.md`.

- A5 (M, schema) Channel memory and decision marks. After A3. Entries (title, text, tags, author, pinned, expiry), a Memory tab, the digest in every brief; a decision mark on a message by any member and its decision line in the brief; approvals and polls opened to agent keys as the owner's questions, the outcome as a decision line. From here a message of the owner's account marked as a decision is an owner decision (`workflow/architect.md`).
- A6 (M) Model assistants. After A3. A card each: handle (`@chatgpt`), name, avatar, sealed key, model, test; OpenAI-compatible chat APIs (OpenAI, Gemini, Mistral, DeepSeek, OpenRouter, Azure OpenAI, a custom address such as Ollama) answered by `ws_ai_step()` with that address, key and model: the same tools, proposals and approvals; `ws_ai_requests.agent` `model:<id>`; each provider's API and data terms rechecked first; the question names the provider.
- A7 (M) Risk tiers. After A4. Tiers on `ws_change_types()` (1 reading and messages, 2 a reversible record change, 3 irreversible), the execution policy from the channel-agent row, an undo journal for agent changes; then flows: an "ask an agent" box and agent-started runs by permission (`plans/flows-next.md` shape).
- A8 (M) Further doors. After A4. The Claude Code channel plugin in a separate repository (`kodpen/pinegrap-claude-channel`: poll, claim, push the event, tools reply, fail, context, record, react, post), applied to Anthropic's channel list, under the shelved plan's security rules; per-person sessions over OAuth with the "via" badge; a chat box on the dashboard on the same catalogue.

## Which run side by side?
- A5 and A6 beside A4 after A3; A7 and A8 together after A4.

## What did the plan take over? (2026-11-06)
- `workspace-agent-handles` (slices 1a, 1b, 5a, 5b landed): the one registry of askable agents (kind, handle, name, avatar, application id, provider, model, sealed key, status; keys `app:<id>`, `model:<id>`) is step A3, the model assistants (`@chatgpt`) step A6; a handle equal to a user name is refused (#2418). `claude-code-channel` (shelved): its security rules hold for A8 (no secrets in the folder, default permission mode, approvals only to the connection's owner, channel text as plain text).
