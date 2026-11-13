---
name: agent-request-lines
description: Plan (owner 2026-11-13, #9188): the line under a Workspace message shows every agent the message woke, not only the last one asked; one step, RL1
---

## What is asked?
- Owner (2026-11-13, #9188), with a picture of a channel: "bakarmısın 2 kişiden bahsettim biri bekleniyor yazıyor". His message named two agents; the one line under it read "Bekliyor: `@prmerge`", and the other agent, which had answered, was not on it.
- Asked what the line should show, he chose "Satır her ajanı göstersin": the state of every agent the message woke (`workflow/decisions-made-workspace-21.md`).

## Why does it show one agent today?
- A message that names two agents opens two `ws_ai_requests` rows (`workspace/agent-doors.md`), but `ws_claude_requests_map()` (`includes/workspace/claude.php`) keeps the newest row of each message. The message carries that one as its `claude` state (`includes/workspace/messages.php`) and `claudeStateNode()` (`assets/js/workspace.js`) draws it.
- Three more places in `workspace.js` read that one state to ask "is a request open": the faster poll with its Pinegrap AI and MCP agent kicks, and `editHoldStart()`. With two agents they see the last one only.

## Which step?
- RL1 (S) Every agent's state — done, #9237. The message carries a list of states, one per agent it woke, in the order they were asked; where one agent has more than one request on the message, its newest. Each state is what `ws_claude_request_state()` gives today (label, icon, Stop link, the edit hold's wording).
- Under the message one state per agent, each on its own line with its own Stop link; they stack on a phone, nothing scrolls sideways. A message that woke one agent looks as it does today.
- The poll, the two kicks and the edit hold look at every state: a request that still waits keeps the screen looking and its assistant carried on, also when a later request on the same message is closed.
- The grouping (rows to message, newest per agent, asked order) is a pure function with a test in `tests/workspace_agent_shown_test.php`, red first on a message with two agents. `php tools/build_js.php` after `workspace.js`.
- `workspace/agent-shown.md` is corrected: its "One line per message: the last request on it." goes.
- The PR shows one picture of a message that woke two agents, one answered and one waiting.

## What stays?
- The request rows, the wake rules and brakes, the labels and icons of a state, the agent-requests routes and the API's objects. No table, no column; a new text only if the step cannot do without one (`php tools/tr_add.php`).
- The settings card's request list and the agent activity list: each request has its own row there already.

## How is it claimed?
- `[plan:agent-request-lines] RL1 — Every agent's state`. When it lands the plan is deleted; the behaviour stands in `workspace/agent-shown.md`.
