---
name: agent-shown
description: How a registry agent shows (A4b2): the line under its waking message, its tag and @ picker entry, kept off tasks, its own edits, two applications' lines kept apart
---

## Where is it?
- `includes/workspace/agent_shown.php` (loaded from ai.php), tests in `tests/workspace_agent_shown_test.php`.

## What does the message that woke it show?
- The line under a message names the agent (`ws_claude_request_state()` hands an `app:`/`model:` row to `ws_agent_request_state()`: waiting, the next try of a failed push door or model, working, answered, could not). One line per message: the last request on it.

## When do two lines read as one author's group?
- `sameSender()` in `assets/js/workspace.js` (the `follow` rule, pure, `tests/workspace_message_follow_test.php`): same `sender_kind` and `sender.id`, and as every application, guest and outside mail sender has `id` 0, also the same `app_id`, `guest_id`, name (an OAuth client's proposal is drawn under the client's name) and, for a mail, address.
- The symptom it fixed (owner, 2026-11-11; #8812): the architect's line stood under the merging session's message as its continuation, and CI's lines the same way.

## How is it tagged and picked?
- Its tag `<@app:N>` reads as `@handle` with its name (`ws_agent_tags()` into `ws_app_tag()`); the channel's @ picker offers the agents the channel allows first, as that tag (`ws_agent_picker_entries()` in `ws_channel_app_picker()`), so the pick wakes it under any rule.

## Is it an assistant a task goes to?
- `ws_assistants()` lists active app and model agents with an application (keys `agent:<id>`, `model:<id>`) for their name and picture, `delegate` false: the task drawer and `ws_task_delegate()` leave them out (`ws_assistant_takes_tasks()`); an agent is asked in its channels. Its key counts as an assistant's (`ws_api_is_assistant()`), so it hands no task over.

## May it change its own messages?
- Own messages: `workspace.messages.update` and `.delete` take an active app agent's own key (no device, `ws_api_agent_own_app()`) for messages its application wrote (`ws_message_is_own()`, `$as_app` on `ws_message_edit*()` / `ws_message_delete*()`); the edit sets `edited_at`, so the edited mark shows. Any other application key stays refused.

## What is not done?
- Seen live on dev (2026-11-10): the waiting line under the message, the tag as `@mimar` with the robot mark, the @ list's entry (after an MCP alias, before the people). Typed as text `@mimar` wakes too, drawn as plain text. The marks its claim and answer leave on the message: `agent-key-trial.md`. An own-message edit with a real agent key: `agent-key-trial-2.md`.
