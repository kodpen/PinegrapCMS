---
name: approval-agents-asked-once
description: An agent asked to approve gets one request per approval: the title's or text's tags and an "all" wake rule skip the agents the approval asked (#9740)
---

## Why could an agent asked to approve get two requests?

`ws_approval_create()` asks its agents with `ws_approval_agents_ask()` (the request carrying the approval), then runs the message's tags with `ws_message_ask_noted()`. An agent named in the title or text, one whose channel row wakes on every message (rule `all`), `@ai` or `@claude` got a second, plain request with no approval in its brief, answered it with a message and recorded no decision; both counted against its hourly brake. Fixed in #9740.

## How is the second request avoided now?

- `ws_approval_agents_ask()` returns the values it asked (even when a brake held the request back).
- `ws_message_ask_noted()` / `ws_message_ask_written()` / `ws_message_ask_assistants()` take a `$skip` list; `ws_message_ask_skip()` (pure) splits it into Claude, Pinegrap AI and registry values.
- Registry agents go to `ws_agent_after_send()` as `skip`; Claude and Pinegrap AI are not asked; a followed discussion does not ask a skipped agent either.
- Claude skipped this way still gets its run started: `ws_message_ask_noted()` calls `ws_claude_dispatch()`.
- An application's path (`ws_agent_after_app_send()`) has no skip: an application never asks an agent to approve.

## Where is it tested?

`tests/approval_agents_test.php`, `test_approval_agents_asked_once_by_their_approval()`.
