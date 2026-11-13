---
name: agent-emoji
description: The one fitting emoji an agent leaves on the message that asked it (#8932): the brief, the answer route's emoji, a model assistant's answer tool, what is refused
---

## What does an agent leave, and where?
- One fitting emoji on the message that asked, beside its text or in place of it: a celebration for good news, a heart for thanks, a thumbs up for "done". At most one per request, never on the agent's own message. The brief says so (`ws_agent_loop_guard_lines()`, `ws_agent_brief_tool_lines()`, `agent_queue.php`).
- Built in #8932 (issue #8873, `workflow/decisions-made-workspace-19.md`). Before it an application could react only through `POST /workspace/messages/{id}/reactions`, which the brief never named, and a model assistant could not at all.

## How does an application agent leave it?
- `POST /workspace/agent-requests/{id}/answer` takes an optional `emoji`: with `text` both are left, with `no_answer` the request closes with the emoji alone.
- It is checked before the dry run: `ws_agent_emoji_problem()` (pure: one emoji by `ws_reaction_valid()`, an asking message, not the agent's own application's) and `ws_reaction_check()` (rights, the 20-emoji limit), then left with `ws_reaction_set()` as the application's, as the reaction route writes it. A wake asked by no message takes none (422 on `emoji`).

## How does a model assistant leave it?
- Its `answer` tool (`ws_ai_tools()`, `ai.php`) takes an optional `emoji` when a channel message asked it (`ws_ai_answer_takes_emoji()`, `model_agents.php`), and `text` is then optional. Pinegrap AI, a note, a task, the Page Design Studio and an MCP alias keep the old tool.
- Words with an emoji: the answer is written, then the emoji left (`ws_ai_react()`, the assistant's application). An emoji alone with nothing proposed closes the request with no reply (`ws_model_close_with_emoji()`). An emoji it may not leave is dropped (`ws_model_answer_emoji()`), never a failure.

## What stays?
- The automatic eye and tick (`agent-shown.md`): an emoji alone reads "saw it · no answer needed" with the eye kept and the emoji on the message.
- Tests: `tests/workspace_agent_emoji_test.php`. No database or provider here: a real answer with an emoji was not tried.
