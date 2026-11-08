---
name: agent-channel-steps-2
description: Steps A5-A9 of plans/agent-channel.md: channel memory and decision marks, model assistants, risk tiers, further doors (Claude Code plugin, per-person sessions, chat box), the canvas as an agent tool
---

## Which steps are there?
- Sizes and rules as in `agent-channel-steps.md`.

- A5 (M, schema) done in #8187. Channel memory and decision marks: entries (title, text, tags, author, pinned, expiry) in every channel (#8188), the index in the brief and `workspace.memory.get` for the texts; a Memory tab where the channel has an agent, else a button in the channel's settings (owner 2026-11-08, `workflow/decisions-made-workspace-9.md`); marks by members and agents (`ws_messages.marked_by_kind`) with their decision line in the brief; routes `workspace.messages.mark` and `workspace.memory.list|get|create|update|delete`, MCP tools too (`workspace/channel-memory.md`). From here a message of the owner's account marked as a decision is an owner decision (`workflow/architect.md`).
- A5b (S) done in #8227. Agents ask the people. After A5. Approvals and polls opened to agent keys as the owner's questions (an agent asks the people with a poll or an approval, the recommended option first), the outcome as a decision line in the brief. Start from `ws_api_person_only()` / `ws_api_own_words_only()` on the poll and approval routes (`workspace/workspace-api-callers.md`): an agent opens one, only people answer.
- A6 (M) Model assistants. After A3. `done` (#8191): a card each (handle such as `@chatgpt`, name, avatar, sealed key, model); OpenAI-compatible chat APIs answered by `ws_ai_step()` with that address, key and model, the same tools, proposals and approvals; `ws_ai_requests.agent` `model:<id>` (`model_agents.php`, `workspace/model-assistants.md`).
  - A6b (S) after A3b and A6: on the Agents card the provider select of `ws_model_providers()`, the address field (azure, custom), the terms line, Try (`ws_model_agent_test()`).
- A7 (M) Risk tiers. After A4. Tiers on `ws_change_types()` (1 reading and messages, 2 a reversible record change, 3 irreversible), the execution policy from the channel-agent row, an undo journal for agent changes; then flows: an "ask an agent" box and agent-started runs by permission (`plans/flows-next.md` shape).
- A8 (M) Further doors. After A4. The Claude Code channel plugin in a separate repository (`kodpen/pinegrap-claude-channel`: poll, claim, push the event, tools reply, fail, context, record, react, post), applied to Anthropic's channel list, under the shelved plan's security rules; per-person sessions over OAuth with the "via" badge; a chat box on the dashboard on the same catalogue.
- A9 (S) The canvas as an agent tool. After A4. `workspace.canvas.create` and `workspace.canvas.update`: the image editor's canvas document (`pg_image_editor_canvas_is_doc()`) with the PNG the agent rendered, kept as `workspace_file_save.php` keeps a canvas (the PNG as the message's or note's file, the document beside it in `file_versions`), so people open its layers in the editor; over MCP under the 1 MB rule (`api/workspace-mcp-small-file.md`); the PNG rendered by the server instead once `plans/image-editor-server.md` lands. Workflow tables need nothing: their routes are complete (`workspace/workflow-tables-api.md`).

## Which run side by side?
- A5 and A6 beside A4 after A3; A5b after A5; A7, A8 and A9 together after A4.

## What did the plan take over? (2026-11-06)
- `workspace-agent-handles` (slices 1a, 1b, 5a, 5b landed): the one registry of askable agents (kind, handle, name, avatar, application id, provider, model, sealed key, status; keys `app:<id>`, `model:<id>`) is step A3, the model assistants (`@chatgpt`) step A6; a handle equal to a user name is refused (#2418). `claude-code-channel` (shelved): its security rules hold for A8 (no secrets in the folder, default permission mode, approvals only to the connection's owner, channel text as plain text).
