---
name: channel-info-agents-true
description: Plan channel-info-agents, step CI1: the read's new fields, the new texts, what must be true with a test for each line, what does not change, what is looked at before ready
priority: 5
---

## What does the read give more?
- `ws_channel_member_agent()`: `seen_rest` (the last seen in running words: "last knock 3 h ago", "not knocked yet", "always ready") and `wake_fact` (the wake rule with no verb, beside `ws_channel_agent_wake_words()`).
- Each agent of `ws_channel_activity()`: from its member row `wake_fact`, `seen`, `seen_state`, `seen_rest`, `waiting`, `active`; and `reported_ago`, the report's age alone. No query more for an agent.
- `ws_channel_activity_summary()` counts only working, waiting and not seen; '' when none.
- The API schema declares the new fields (`php tools/check_api_schema.php`).
- The block's rows are `channelAgents(channel)` joined by id with the read's agents, the read's row winning. The read runs on either tab while the panel is open in a channel with a registry agent, on today's brakes.

## Which texts are new? (`php tools/tr_add.php`, each with its Turkish)
- "Agents", "Manage agents", "no report yet", "always ready", "last knock {var:1}", "not knocked yet", "{var:1} requests in its queue", "1 request in its queue", "for {var:1}, {var:2}".
- Labels: "Doing", "Asked by", "Queue", "Handle", "Wakes", "Model", "Context", "Branch", "Seen", "Reported", "Not yet".
- "When mentioned", "On every message", "On {var:1}", "On replies to it"; "{var:1} of {var:2} · {var:3}%"; "Context {var:1} ({var:2}%)".
- "{var:1} more" exists (`act_more`). A text only the old lists used is taken out of the strings.

## What must be true? (one test each; the PR body names the test beside the line)
1. Line 2 of each case of `channel-info-agents-row.md` (working with a note, with an asker only, with neither; waiting 2 and 1; not seen; ready with a model, stale, no report; paused; an assistant), from a pure function.
2. The facts of an opened row, in order: a working agent with everything has 9, with a queue 10; an agent with no report has Handle, Wakes, Seen and Reported "Not yet"; an assistant has 3. From a pure function.
3. Nine rows show six and "3 more"; six rows show six and no such row; the order is working, waiting, not seen, ready, paused, assistants.
4. The ring: 41% draws 41% of the circle, 93% is high, no context gives no ring.
5. The summary of working, waiting, ready is "1 working · 1 waiting"; of three ready ''.
6. The read's agent carries the seven new fields; the member row the two.
7. The source of the block has no `badge`, `pg-pill` or `pg-button` in a row, and a row's button has `aria-expanded`.
8. `agentInfoRow()` has one caller left (the people panel); `drawSummary()` and `drawAgents()` are gone; `backend.src.css` has no `.ws-act-agent`, `.ws-act-summary-line`, `.ws-act-state` or `.ws-act-context` rule and keeps `.ws-info-agents`.
9. The tests are in `tests/workspace_channel_activity_test.php`, `…_activity_js_test.php` and `workspace_channel_info_test.php`.

## What does not change?
- `activityTabsShow()`, the tab kept in the browser, the route and the answer's other parts, the brakes, `.ws-agent-dot`, the state keys and their words, the focus kept by `data-info-key`.
- `refreshInfo()` draws the panel again: the open rows and "n more" are kept across it by the agent's key, and dropped when the panel closes.

## What is looked at before the PR is ready?
- No unit test reaches the look, so the sandbox (`tools/setup_sandbox.sh`): both themes, the 360 px panel and 390 px, boards B1, S1, O1 and M1 each set beside the screen; a row opened, the tab changed with it open, nine agents.
- The PR body's board table says for each what was seen. A state that could not be made there is named.
