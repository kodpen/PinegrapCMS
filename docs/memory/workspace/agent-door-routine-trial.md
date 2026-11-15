---
name: agent-door-routine-trial
description: Push door on a routine's fire address, tried on dev (2026-11-13, 2026-11-15): every knock starts a new cloud session, never wakes an open one, and the new run cannot pass the knock on either
---

## What did a routine's fire address answer first? (2026-11-13)
- Tried with `@prmerge`, a cloud session: it made a routine bound to itself with no schedule; the owner generated the routine's API token and entered the fire address and the token as the agent's push door.
- The site knocked in the same second as the request. The address answered HTTP 400 (`door_failures` 1, "The push door answered HTTP 400."). Not 401, so the token is not what was refused; the site keeps no answer body. The request stayed queued for the poll route: nothing was lost.
- Why, read from the code: the knock was the webhook form; a routine takes the form `ws_claude_fire()` sends (`anthropic-beta`, `anthropic-version`, body `{"text": …}`). Since #9273 a routine's address is knocked in that form (`agent-doors.md`): a door is knocked in the form its address takes (architect, 2026-11-13).

## What did the knock in the routine's form do? (2026-11-15)
- `@prmerge` and `@mimar`, both cloud sessions, each had a routine bound to its own session, the owner's API token and the fire address as push door. Every knock answered 2xx and `door_failures` stayed 0.
- No knock woke the session. A fire with the API token starts a new cloud session each time, which claude.ai lists as an "API" run of the routine; the session the routine is bound to gets no turn (`workflow/agent-channel-experiment.md`).
- So each request was read twice: by the new session, with the agent's key and no memory of the work, and by the open session's poll. Request 383 was answered by a session nobody watched; `@prmerge`'s routine listed nine such sessions from 2026-11-14 16:15 to 2026-11-15 03:54, each a whole session's quota. The card showed nothing: every knock was a 2xx.
- These runs show only on the routine's page of claude.ai, not in the routine's last run nor in the account's session list as a session's tools read them.
- Since then both agents wait on the poll door. A routine's address fits an agent that keeps no session open: each knock is one run that reads the list, answers and ends, counted against the account's daily run allowance. The card's warning for the double is #9595's fix.
- Open, for a trial with the owner: the first knocks of that night (requests 372 to 378) answered 2xx and left no session anyone found.

## Can the routine's run pass the knock on to the open session? (2026-11-15)
- No. Tried with the test agent on the owner's account and with the architect's own session: the routine's text told its new run only to wake the open session and end.
- A run a routine starts has no tool that reaches another cloud session. The cloud sessions' `send_message` and `fire_trigger` tools are not in its tool list and its tool search finds neither; its `SendMessage` reaches only agents on the same machine and answers a session id with "No agent named … is reachable". A routine made by a session is given no connectors in this organization.
- So nothing outside wakes an open cloud session at once. It waits on the poll door (`tools/ws_wait.php --requests` in the background spends nothing while it waits) and keeps an hourly scheduled task bound to itself, which wakes it after its container has slept.
- The push door stays for an agent with an address of its own (a webhook on its own server) and for an agent that keeps no session open.

Related: `agent-door-knock-trial.md`, `agent-doors.md`.
