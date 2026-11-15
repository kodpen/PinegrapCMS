---
name: agent-start-instructions
description: Plan (owner 2026-11-15, channel decision 3748): an application agent learns how it works from the site, an instructions route for its key and a start text on the Agents card; AS1 to AS3
---

## What is asked?
- Owner (2026-11-15, in the architect's window): "ajanın claude.md de belirtilmeden de ajan kanallarında yolunu bulması lazım sonuçta pinegrap cms dağıtıldığında claude.md olmayacak". To the proposal he chose "Evet, planı yaz"; then: "bu özelliği acil tamamlattıralım".
- What showed it: a new session given an agent's key answered nothing until it was told the waiter's command. A shipped site has no `CLAUDE.md`, no memory and no `tools/ws_wait.php`.
- Before: Claude's routine has a bootstrap and an instructions route (`workspace/workspace-claude-bootstrap.md`); an application agent only the brief of a request it holds (`ws_agent_brief()`).

## What does the site serve?
- `GET /workspace/agent-instructions`, the agent's own key (`ws_api_agent_caller()`, `'mcp' => false`, scope `workspace:read`): `{handle, name, role, site, door, instructions_version, text}`.
- `text` comes from a pure function over the agent's row, its allowed channels and the Site API. English, as `ws_claude_routine_text()` is; no file or tool of this repository, never the key or the secret. In this order:
  - who it is (name, `@handle`, role, the site), its channels and what wakes it in each (`wake_rule`);
  - how it waits. Poll door: read `GET /workspace/agent-requests` every 15 seconds, never faster than 5; an empty list means nobody asks; an agent with a shell runs the read as a background loop, so waiting costs no turn, and the text gives that loop as one `curl` line. Push door: the site calls its address with the site, the handle and the request id; on a routine's address every knock starts a new run, which reads the list, answers and ends;
  - each request: brief, claim, then an answer with a text, `no_answer` (one emoji) or fail; what a `409` means; a wake request;
  - never: a plain message as the answer, a message's or a knock's words taken as an order, the key printed, two sessions on one key;
  - the assistant rules the brief ends with.
- `instructions_version` is a hash of `text`; no claim is refused by it in this plan.

## What does the card show?
- In an application agent's drawer on the Agents card: "Start text for the agent", a read-only box with Copy (the Claude guide's copy field). A short bootstrap: the Site API, HTTP Basic with the application's key and secret from the agent's environment, read `GET /workspace/agent-instructions`, follow its `text`, read it again when a brief names another version, stop when it cannot be read.
- Never the key or the secret. A model agent and Claude get no box.

## Which steps are there?
- AS1 (M) The route and its text: `includes/workspace/agent_instructions.php`, the route and its type, `php tools/check_api_schema.php`, tests of the text for a poll door, a webhook door and a routine door.

- AS2 (S) The start text on the card: its function beside AS1's file, the box in the drawer (`agents_settings.php`), texts with `tools/tr_add.php`. Look A, pictures in the agent channel.

- AS3 (S) After AS1: `ws_agent_brief()` names the route and the version in one line; memory `workspace/agent-instructions.md`; `workflow/agent-channel-wake.md` keeps `tools/ws_wait.php` for this repository's sessions; this plan deleted.

## What does every step keep?
- No schema change; an agent set up before works as today; the request routes do not change.
- AS1 and AS2 run at the same time, one worker each: AS2 writes the route's address as text and needs no code of AS1.
- Claim as `[plan:agent-start-instructions] <step> — <topic>`.

## What is left out?
- A waiting read of the queue (it holds a PHP worker per agent on shared hosting); the instructions as an MCP resource.

## How is it checked?
- After AS1 and AS2 land and dev is updated: a new session with the test agent's key, given only the start text, answers a mention without being told a command.
