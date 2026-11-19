---
name: agent-feedback
description: Right or Wrong on an agent's reply (AF5a #9814, AF5b #9850): the ws_agent_feedback table, who marks, the buttons under a reply, the counts on the cards, GET /workspace/agent-feedback
---

## What is it for?
- People tell an agent whether an answer was right, with a short reason on a wrong one; the agent reads its marks. Owner (2026-11-15, agent channel poll 49) chose it with a small table (`workflow/decisions-made-schema-21.md`); columns confirmed by the architect (channel message 5429).

## Where is it kept?
- `ws_agent_feedback`, step 11.0.218 (`install_create_table()`, in `get_tables()`): `message_id` (the agent's reply, primary key: one mark per reply), `request_id`, `channel_id`, `agent` (the request's `ws_ai_requests.agent`, `agent:<id>` / `model:<id>`), `verdict` ENUM right/wrong, `reason` VARCHAR(300), `marked_by`, `marked_at`; `KEY idx_agent (agent, marked_at)`.
- A site whose update has not run: `ws_agent_feedback_ready()` is false, the writer answers 503 and the route lists nothing; everything else works as before.

## Who may mark, and how?
- `ws_agent_feedback_mark()` in `includes/workspace/agent_feedback.php`, the one writer; `$verdict` null takes the mark back (deletes the row). The check is `ws_agent_feedback_check()` (pure).
- Only an answered request of an application or model agent whose `reply_message_id` is the message, the reply not deleted, else 404; the person must read the channel (404 otherwise). The person who asked (`requested_by`) or a manager of the channel (`ws_can_manage_channel()`), else 403.
- A new mark by either replaces verdict, reason, `marked_by` and `marked_at`: the last mark stands. The reason folds spaces and is at most 300 characters (422).
- The panel action `ws_agent_feedback` (`actions.php`, listed in `panel/actions.php`): `message_id`, `verdict` right / wrong / '' (takes it back), `reason`; it calls this writer and answers its refusal. A team devices session: `api/workspace-api-message-sender-feedback.md`.

## What does the panel show? (AF5b)
- Under an agent's reply, after its reactions: "Was this answer right?" with Correct and Wrong (thumbs up / down), only for the asker and the channel's managers: the message payload's `feedback` (`ws_agent_feedback_map()`, `ws_agent_feedback_offer()`) is null for anybody else. `feedbackNode()` in `workspace.js`.
- The chosen mark shows pressed in the answered (green) or failed (red) colour and the line reads "Marked"; a press on it takes it back. Wrong opens a reason box (optional, 300 characters) with Save and Cancel; the reason shows in italics under the line.
- The counts: "Right or Wrong: 7 days: n right · n wrong; 30 days: …" (`ws_agent_feedback_counts_text()`, one grouped query `ws_agent_feedback_counts_by_agent()`), in the Agents card row's facts line (`agent-activity.md`); on the agent card (`agent-card-look.md`) a "Right or Wrong" row with two stacked lines, 7 days and 30 days (`feedback`, `ws_agent_feedback_counts_lines()`); left out while nothing was marked in 30 days.
- Owner (2026-11-16, agent channel poll 104, message 7050): "Evet, böyle çıksın" on the sheet of message 7049 (`workflow/decisions-made-product-66.md`).

## What does the agent read?
- `GET /workspace/agent-feedback` (`workspace.agent_feedback.get`, `workspace:read`, `'mcp' => false`, in `api_agent_requests.php`): only an active app agent's own key (`ws_api_agent_caller()`, 403 otherwise).
- `marks`: the newest 100 on replies still there, in channels the key's owner reads (`message_id`, `request_id`, `channel_id`, `verdict`, `reason`, `marked_at`); who marked is not given. `counts`: `days_7` and `days_30`, right and wrong, by the time of the mark (`ws_agent_feedback_tally()`), of every reply still there.
- The instructions name the route (`ws_agent_instructions_request_lines()`).
- A Wrong only counts and wakes nobody. Left out, a later yes of the owner: a Wrong that wakes the agent to answer again.

## Tests?
- `tests/workspace_agent_feedback_test.php`.
