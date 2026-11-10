---
name: agent-channel-questions
description: Plan (owner 2026-11-08): agents ask people with a checklist in an agent channel, read the ticks over the API, wake on a tick or a person's message; one step AQ
---

## What is it for?
- An agent asks the owner and the team in an agent channel and gets the answer there: a checklist the people tick, not a numbered list answered by typing (owner 2026-11-08, `workflow/decisions-made-workspace-8.md`). The owner account's tick is an owner decision. Polls and approvals for agent keys: `workspace/agent-questions.md` (A5b, #8227).

## What exists already?
- A message whose body is `::: question` then `- [ ] option` lines shows as a titled checklist; whoever may write in the channel ticks it, an application's message too (`ws_render_body()`, ticks in `ws_checks`, `ws_checks_map()`). `GET /workspace/sync` lists a ticked message under `changed`. The API's message carries no ticks: `text` and `body` are as written (`ws_api_message_present()`).
- An application writes such a message today (`tools/ws_post.php`), and a `decision` message with `parent_id`.

## Step AQ (S, no schema) — in progress
- Claim as `[plan:agent-channel-questions] AQ — checklist questions`.
- API: the message (sync `messages` and `changed`, a channel's messages, one message) gets `checks`, one entry per checklist item: `{item, text, checked, by: {user_id, name} | null, at}`, the tick over the written state as the panel shows it; a pure helper beside `ws_api_message_present()`, its schema in `ws_api_message_schema()`, a test. Who may tick is unchanged.
- Bell (owner, 2026-11-09, `workflow/decisions-made-workspace-10.md`): a checklist question or a poll that an application posts rings the people it mentions, whatever their notify level, and the channel's owners (`ws_channel_members.role`) when it mentions nobody, as `approval_requested` rings approvers. A kind of its own through `ws_notify()` and `ws_inbox_describe()`; `ws_inbox.kind` is a VARCHAR, so no schema. People's own polls and checklists stay as they are.
- `tools/ws_wait.php`: `--from=person|app` (person: `sender.user_id` set, so an application's line never wakes a session waiting for people) and `--changed=<id>[,<id>]` (wake when one of those messages comes under `changed`; print it with its `checks`). `tools/ws_post.php`: `--parent=<id>`. Tests in `tests/ws_channel_tools_test.php`.
- Memory: `workspace/agent-checklist-questions.md`: how to ask, read and close (the asking agent writes a `decision` message answering the question with the ticked option and who ticked it; one box expected, several ticked: it asks again) and `workflow/agent-channel-wake.md` (the waiter below).

## How does a session wake on the channel with the waiter? (architect, 2026-11-08)
- A waiter: `php tools/ws_wait.php --from=person --regex='/@(mimar|hepsi)\b/i' --timeout=7000`, run with Bash `run_in_background` (timeout 7200000). It exits on a match and so wakes the session, which answers and starts it again with `--after-id`; exit 3 (timeout) starts it again; each check-in makes sure one runs. Waiting costs no tokens (a 304 every 5 s); each wake is one turn.
- Handles typed in the text: `@mimar`, `@birlestirici`, `@koordinator-<account>`, `@test`, `@hepsi` (all). An agent picked from the @ picker is its application's tag `<@app:N>` in `body` (`workspace/agent-shown.md`); the waiter matches it too with `--mention=<application id>` (part of AQ). CI writes its `[ci]` / `[ready]` lines into the same agent channel (owner, 2026-11-08, in the channel); such an application's line wakes nobody until the merging session moves its cadence to the channel.
- Settings: the four `PG_WS_*` variables of the session's cloud environment, an application per account (`ADNAN Mimar` today).

## After AQ
- The architect asks owner questions in the agent channel as checklists and keeps its window for the rest (`workflow/architect.md`).
