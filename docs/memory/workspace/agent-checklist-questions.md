---
name: agent-checklist-questions
description: How an agent asks people with a checklist in a channel, reads the ticks over the API (checks), is woken by a tick, and closes the question with a decision; who the question rings (question_asked)
---

## How does an agent ask?
- A message whose body is `::: <question>` then `- [ ] option` lines (`tools/ws_post.php "…"`, or `POST /workspace/channels/{id}/messages`). It shows as a titled checklist; whoever may write in the channel ticks it, an application's message too. Put the recommended option first. A people-only question is a poll (`agent-questions.md`).
- Owner decision (2026-11-08, `workflow/decisions-made-workspace-8.md`): a checklist, never a numbered list answered by typing. The owner account's tick is an owner decision; anybody else's is a channel decision.

## Who is rung?
- An application's checklist question (at least one item written unticked, `ws_checklist_asks()`) or poll rings the people it mentions (`<@user:N>`, a department), whatever their notify level, and the channel's owners (`ws_channel_members.role = 'owner'`; a discussion's: its channel's) when it mentions nobody: `ws_question_ring()`, inbox kind `question_asked`, "{app} asks you in #channel". Somebody already holding an unread `mention` line of the message is not rung twice. People's own checklists and polls ring as before (mentions only).
- Why: a member on "mentions only" never saw an agent's question in the bell (owner 2026-11-09, `workflow/decisions-made-workspace-10.md`). `ws_inbox.kind` is a VARCHAR: no schema.

## How does the agent read the ticks?
- Every API message (sync `messages` and `changed`, a channel's messages, one message) carries `checks`: one `{item, text, checked, by: {user_id, name} | null, at}` per checklist line, in order, the tick given since over the state as written, as the panel draws it (`ws_api_message_checks()`, schema `WorkspaceMessageCheck`). `by`/`at` are null for an item nobody touched; a guest has `user_id` null. A deleted message has none. `text`/`body` stay as written.
- A tick touches the message, so it comes under sync `changed`: `php tools/ws_wait.php --changed=<id>` wakes on it and prints the message with its `checks`.

## How is the question closed?
- The asking agent writes a `decision` answering the question: `php tools/ws_post.php --kind=decision --parent=<question id> "<option> — ticked by <name>"`. One box expected and several ticked: it asks again. The architect records an owner answer in `workflow/decisions-made-*.md`.
