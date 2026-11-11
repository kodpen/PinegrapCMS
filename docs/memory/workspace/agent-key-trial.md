---
name: agent-key-trial
description: The agent's own side tried live on dev (2026-11-10) with an application agent's key: the request list, the brief of a mention and of a wake, claim, answer, no_answer, fail, agent-wake
---

## What was tried, and how?
- dev.pinegrap.com at `development` 1058f58b, over HTTP with the key of application 13 (`@mimar`, registry agent 2, poll door, channel 25, mention, tier 1), Basic auth. Five requests waited: 37 and 41 from mentions (messages 696, 702), 38-40 from wakes. No call failed on the server; the rate limit header said 120 a minute.

## What did the list and the brief give?
- `GET /workspace/agent-requests`: 200, the five as `queued`, oldest first, in the shape of Claude's requests. A mention's row has its message (`text` with `@mimar`, `body` with `<@app:13>`) and `wake.kind` ''; a wake's row has message id 0, the channel, `requested_by` the agent's creator and `wake` {`kind` every or at, `note`, `due_at`}. `status=all`: the latest 50, newest first.
- `GET …/37/brief` (a mention): 200, `{request_id, text}`, 3.4 KB of plain text: who the agent is (name, @handle, the site), the time with its zone, who asks, the channel and `GET /workspace/channels/25/context` to read more, the message in a `<<<request>>>` block (the `@mimar` form), the channel's decisions in a `<<<channel memory>>>` block, the four loop-guard lines, the MCP address and the key's scopes, the answer and fail routes with the id, "Answer in Turkish", the rules.
- `GET …/38/brief` (a wake): the same, with "The request comes from your schedule", "Your schedule woke you (a wake every few minutes); no message asked", the channel the answer goes into with its id, and the note in the request block. It does not give the due time.

## What did claim, answer and fail do?
- `POST …/37/claim`: 200, `running`, `claimed_at` set; message 696 got 👀 from the application.
- `POST …/37/answer` with a text: 200, `answered`, `reply_message_id` 707: the application's message under 696 (`parent_id`), starting with the asker's tag; 👀 gave way to ✅.
- `POST …/answer` with `no_answer` on 38, 39 and 40, not claimed first: 200 each, `answered`, no reply, `claimed_at` stamped with the answer; nothing was written in the channel.
- `POST …/41/fail` with a reason, not claimed first: 200, `failed`, `error` the reason, written under message 702 with the asker's tag (`reply_message_id` 708); no mark on 702, `claimed_at` empty.
- Then the open list was empty, and a second claim of 37 and a second answer to 38 got 409 `closed`.
- `GET /workspace/agent-wake`: 200, `{handle, every: 0, next_at: null, note: ""}`; the schedule was off.

## What could the key not see or do?
- The line under the asking message: `GET /workspace/sync` gives a message's reactions, not its request's state; the panel draws the line (`agent-shown.md`).
- A proposal: a dry run of `POST /workspace/channels/25/changes` got 403, "An assistant proposes changes in its answer to the request it works on."; now let through to the tier (`agent-risk-tiers.md`).
- Tried the next day (`agent-key-trial-2.md`): a text answer to a wake, `POST agent-wake`, the agent's edit of its own message. Not tried: `no_answer` on a mention, a knock.
