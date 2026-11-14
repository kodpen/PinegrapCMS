---
name: poll-agent-questions-census-wake
description: AQ1 census for the agent question: an agent changing its poll, the answer's request row and brief, the memory index, the ring; open behaviour questions
---

## What keeps an agent from changing or taking back its poll today?
- No column: `ws_poll_edit_check()` requires `sender_kind = user`, and `workspace.polls.update` is own-words-only with `'mcp' => false` (`workspace-api-callers.md`). AQ2 lets the asking application change, take back and ask again its own poll (`ws_api_poll_is_callers()`), as the close does, and says which of these become MCP tools.

## How does the answer wake the asking agent?
- No column. `ws_agent_after_send($viewer, $channel, $message_id, $body, $followed, true, array('state' => …))` (`agent_queue.php`) already makes one `ws_ai_requests` row for a named agent (`$followed` = `agent:<id>` / `model:<id>`), with the brakes, and knocks a push door, as a tag outside a message does (`ws_item_ask()`, `messages.php`).
- The asking agent is the poll message's application (`ws_message_app_id()`) matched to its `ws_agents` row in the channel (`ws_agent_reach_rows()`); `requested_by` is the person who answered; `message_id` the poll's message.
- The brief carries the question and the answer through `ai_state` JSON (like `ws_item_request_state()` / `ws_item_of()`): question, chosen labels, "Other" words, who answered. `ws_agent_brief()` adds the lines; "your answer goes under the poll".
- Claude's routine asks with Claude's application, but `ws_agent_request_value()` gives '' for a `kind = claude` row, so `ws_agent_after_send()` never wakes it: its answer needs the `@Claude` ask path of `claude.php` (an `agent = 'claude'` request), still no column. Pinegrap AI and a model assistant have no poll tool today (`poll-agent-questions-census-today.md`): AQ4 either gives them one or leaves them out.

## How does the memory index read an answered agent question?
- No column. Decisions are read from `ws_messages.kind = 'decision'` (`ws_memory_brief()` in `channel_memory.php`, and `briefing.php`, `channel_board.php`, `palette.php`, `status.php`, the Decisions tab). With no outcome line, `ws_memory_brief()` also reads polls with `agent_question = 1`, `closed_at > 0`, `withdrawn_at = 0`, as "question — answer (who)", in time order with the decisions.
- Whether the Decisions tab and the other decision readers list it too is not in the plan: a question for the owner.

## How is the asked person rung?
- `ws_question_ring()` already rings an application's poll's mentioned people, else the channel's owners, whatever their notify level (`agent-checklist-questions.md`). AQ5 keeps it for an agent question and adds the Today and dashboard Assistant card lines. No column.

## Which behaviour questions does the census leave?
- Does asking again take the old poll back by itself?
- Does an answered agent question show on the Decisions tab, or only in the memory index?
- Can several be asked (a department mention): does the first answer settle it?
