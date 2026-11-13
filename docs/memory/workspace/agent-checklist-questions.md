---
name: agent-checklist-questions
description: Checklist questions over a poll; reading ticks (checks) and an application ticking a line (POST …/checks, app_id), a roll call, the wake on a tick, the closing decision; who a question rings
---

## How does an agent ask?
- A message whose body is `::: <question>` then `- [ ] option` lines (`tools/ws_post.php "…"`, or `POST /workspace/channels/{id}/messages`). It shows as a titled checklist; whoever may write in the channel ticks it, an application's message too. Put the recommended option first.
- A checklist is for a list where several lines are ticked and for a question one agent asks another. A question with one answer asked of people is a one-choice poll, closed once the answer is read (`agent-questions.md`): every checklist line is its own box, any number can be ticked, a ticked line is struck through and nothing closes the list (owner, 2026-11-10, `workflow/decisions-made-workspace-12.md`).
- Owner decision (2026-11-08, `workflow/decisions-made-workspace-8.md`): never a numbered list answered by typing. The owner account's tick is an owner decision; anybody else's is a channel decision.

## Who is rung?
- An application's checklist question (at least one item written unticked, `ws_checklist_asks()`) or poll rings the people it mentions (`<@user:N>`, a department), whatever their notify level, and the channel's owners (`ws_channel_members.role = 'owner'`; a discussion's: its channel's) when it mentions nobody: `ws_question_ring()`, inbox kind `question_asked`, "{app} asks you in #channel". Somebody already holding an unread `mention` line of the message is not rung twice. People's own checklists and polls ring as before (mentions only).
- Why: a member on "mentions only" never saw an agent's question in the bell (owner 2026-11-09, `workflow/decisions-made-workspace-10.md`). `ws_inbox.kind` is a VARCHAR: no schema.

## How does the agent read the ticks?
- Every API message (sync `messages` and `changed`, a channel's messages, one message) carries `checks`: one `{item, text, checked, by: {user_id, name} | null, at, app_id}` per checklist line, in order, the tick given since over the state as written, as the panel draws it (`ws_api_message_checks()`, schema `WorkspaceMessageCheck`). `by`/`at`/`app_id` are null for an item nobody touched; a guest and an application have `user_id` null, an application's tick has its id in `app_id`.
- A deleted message has no `checks`; `text`/`body` stay as written.
- A tick touches the message, so it comes under sync `changed`: `php tools/ws_wait.php --changed=<id>` wakes on it and prints the message with its `checks`.

## How does an application tick a line? (#8847)
- `POST /workspace/messages/{id}/checks` with `item` and `checked` (default true), scope `workspace:write`, also an MCP tool, with a dry run. Allowed where the key's owner may post in the channel, by the panel box's rules (`ws_check_check()`, `ws_check_set()`).
- With an application key or an OAuth connection the tick keeps the application in `ws_checks.app_id` (`user_id` stays the owner) and the line shows the application's name. From a team devices session it is the person's. A later tick by a person or guest sets `app_id` to 0 (`ws_check_who()`).
- A roll call ("are all agents up?", owner 2026-11-11, #8762, `workflow/decisions-made-workspace-16.md`) is a checklist with a line per agent, each ticked by its agent when it next wakes; nothing is woken for it.

## How is the question closed?
- The asking agent writes a `decision` answering the question: `php tools/ws_post.php --kind=decision --parent=<question id> "<option> — ticked by <name>"`. The boxes still take ticks afterwards: the decision line is the answer. The architect records an owner answer in `workflow/decisions-made-*.md`.
