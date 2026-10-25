---
name: workspace-change-proposal-display
description: How an application's proposal is shown: its name on cards, bells, push, Site Log (every bulk slice) and decisions, the task drawer's "Proposed for" line, the agent GET /workspace/decisions gives
---

## Which name is shown, and where?
- `ws_proposal_name()`: the client's or application's name marked "(application)" (`ws_proposal_name_shown()`), so a client called Claude is never read as Claude: cards, waiting and stale lines, task runs, timeline, decisions, bulk decision, refusals, the proposal's message (`ws_proposal_sender()`; other OAuth messages keep the shared OAuth application, plans/workspace-agent-handles.md question 5).
- Bell and push: the task bell names it (`ws_assistant_name()`); a mention in a channel is renamed in `ws_inbox_describe()` through `ws_proposal_sender()`, as the screen does. The title a bell row keeps for after its inbox row is gone (`ws_bell_add()`) is written while the message is sent, before the proposal is linked to it, so it keeps the shared OAuth application's name.
- Site Log: `ws_ai_voice()` turns "on Claude's proposal" into the application's name from `ws_ai_voice_change()`, set when the change is applied and by every slice of a bulk change (`ws_bulk_step()`; later slices run from the screen's step, the tick or the job). Before #1564 later slices said Claude, for Pinegrap AI's bulk changes too.
- A proposed task note is written as the application (`ws_proposal_app_id()`: the key's, or the shared OAuth application). A record-side sentence (an order's cancellation reason) names the person who applied it (`ws_change_sentence()`).

## How does a task's drawer draw it?
- As a run with `proposal` set (`ws_task_run_is_proposal()`): "Proposed for <person>, <time>" (`dg_proposed`), the person its cards wait for, and no instruction line. Not "<person> asked" with "No instruction: the task itself.": nobody asked, and that read as if the person had handed the task over.

## Which agent does the API give?
- GET /workspace/changes: `agent` by `ws_change_agent_key()` (claude, ai, mcp, oauth:<id>, app:<id>). GET /workspace/decisions: `claude_change.agent`, the same value (agent_key of `ws_timeline_claude_changes()`). Before #1564 a decision gave no agent and the route said Claude proposed every change.
