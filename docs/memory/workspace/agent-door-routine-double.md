---
name: agent-door-routine-double
description: A routine's address as push door starts a new run per knock, never an open session: the address's help says so; the card warns while a poll also holds the key (#9595)
---

## What does a knock on a routine's address do?
- A routine fired through its API address with its token starts a new cloud session on every knock. A session that is already open is never reached.
- An agent that keeps one session open and waits on its queue uses the poll door (`GET /workspace/agent-requests`), not a routine as its push door.
- Symptom that led here (#9595): `@prmerge` and `@mimar` kept an open polling session and also had a routine's address as push door. Each request was answered by the open session and also started a second session that raced it for the claim. Every knock got a 2xx, so the card showed nothing.

## Where is it said?
- In the agent form, under Push address: while the address is a routine's (`ws_claude_url_valid()`), a help line says every knock starts a new run of that routine, never wakes an open session, and an agent keeping one session open uses the poll door. The form's script shows and hides it as the address is typed (`data-ws-agent-routine-help`).
- The API describes no door field, so no route text carries it.

## When does the card warn?
- `ws_agent_door_double($agent)` (`includes/workspace/agents.php`, pure): an active `app` agent, door `push`, address a routine's, and its key seen (`seen_at`) more than `WS_AGENT_DOOR_DOUBLE_AFTER` (600 s) after `door_last_knock`, or seen with no knock yet. A run started by a knock calls only in the minutes after it; a later call means another session polls.
- While true, the warning (`ws_agent_door_double_words()`) stands where the door's error stands: under the agent's row on the Agents card (`small fg-danger`) and at the top of its drawer (`alert theme-danger`). It clears by itself once the next knock is newer than the last call, or the door becomes poll.
- "Agents in this channel" in Channel settings shows no push door state (only a poll agent's last seen, `agent-last-seen.md`), so it carries no mark.

## What is not done?
- The knock is not stopped while a polling session holds the key: that changes when a door is knocked and is the owner's call (#9595, "Not in this issue").

Related: `agent-doors.md`, `agent-door-knock-trial.md`, `agent-last-seen.md`.
