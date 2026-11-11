---
name: agent-key-trial-2
description: The agent's own side tried live on dev (2026-11-11) with an application agent's key: POST agent-wake and its dry run, a wake's request, brief and text answer, the agent's edit of its own message
---

## What was tried, and how?
- dev.pinegrap.com on 2026-11-11, over HTTP with the key of application 13 (`@mimar`, registry agent 2, poll door, channel 25, mention, tier 1), Basic auth: what the first day left open (`agent-key-trial.md`). No call failed on the server.

## What did the agent-wake routes do?
- `GET /workspace/agent-wake`: 200, `{handle, every: 0, next_at: null, note: ""}` for an agent without a schedule.
- `POST /workspace/agent-wake?dry_run=true` with `at` and `note`: 200, `{dry_run: true, would: "set", resource: "workspace_agent_wake", detail}`; the schedule stayed unchanged.
- The same `POST` without the dry run (`at` a few minutes ahead, a note with Turkish letters): 200, the note's letters kept.
- `every` together with `off` (dry run): 422 `validation_failed` on the field `every`: the body takes exactly one of `every`, `at`, `off`.

## What did the wake give, and what did a text answer do?
- The request was made 50 seconds after the due minute (the site's cron tick): channel 25, message id 0, `wake` {`kind` at, `note`, `due_at`}, `requested_by` the agent's creator. The one-time schedule cleared itself.
- The brief: "The request comes from your schedule. Your one-time scheduled wake has come; no message asked.", the channel the answer goes into with its id, the note in the `<<<request>>>` block, the channel's decisions in the `<<<channel memory>>>` block.
- `POST …/claim`: 200, `running`. `POST …/answer` with `{"text": …}`: 200, `answered`, with `reply_message_id`: the application's message in channel 25, without a parent and without a mention. The open list was empty afterwards.

## What did the agent's edit of its own message do?
- `POST /workspace/messages/{id}?dry_run=true` on that answer, with the agent's key: 200, `{dry_run: true, would: "updated", resource: "workspace_message", detail: {id, channel_id}}`. Without the dry run: 200, the message with `edited` true.
- The same dry run on another sender's message: 403 `forbidden`.
- The route's comment in `api.php` names only a team device's session; the code takes an active app agent's own key too, for the messages its application wrote (`agent-shown.md`).

## What is still not tried?
- A knock, from a message or from a wake: a push door needs a receiver.
- An agent allowed in several channels: a wake with channel 0, where the answer route refuses a text (`agent-wakes.md`).
- The Agents card's schedule field, `no_answer` on a mention, the agent's delete of its own message.
