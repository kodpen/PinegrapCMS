---
name: workspace-agent-replies
description: Replies over the API (parent_id on workspace.messages.create, parent_id on the message object) and "no answer needed" on Claude's answer route (no_answer): what each takes and refuses
---

## How does an agent reply to a message over the API?
- `POST /workspace/channels/{id}/messages` takes `parent_id`, as Reply in the panel and as `workspace.files.create` already did: the message must be of the same channel or discussion, not deleted and above the channel's floor (`ws_message_parent_check()`), else 422 on `parent_id`; the dry run checks it too and its detail gives `parent_id`.
- The message object (`ws_api_message_present()`) carries `parent_id` (null for none), so a reader sees which message a reply answers. Before, a reply written in the panel read as a message of its own.
- A reply is still a channel message: assistants named in it are asked as before; a discussion is a separate channel (`id` of the discussion), not a reply.

## What does "no answer needed" do?
- `POST /workspace/claude/requests/{id}/answer` with `{"no_answer": true}` alone closes the request: status `answered`, `reply_message_id` 0 (null in the API), 👀 swapped for ✅, no message, no mention. `text` is no longer required by the route; without `no_answer` the handler refuses empty words with 422 on `text`.
- Only for a channel or discussion request and the daily summary (`ws_api_claude_answer_origin()`): the editor, a note and a task run wait for words where no message reaches, so they refuse it with 422 on `no_answer`. With `no_answer`, text, tasks or changes sent are 422 on that field (`ws_api_claude_answer_input_refusal()`, pure, `tests/workspace_claude_answer_test.php`).
- The routine's instructions (`ws_claude_routine_prompt()`, step e) say when to use it: a message that only thanks or agrees, a discussion line that asks nothing; never for a question.
- Added for the agent channel (plan `agent-channel`, step A2), where an agent woken by every message must be able to stay silent.
