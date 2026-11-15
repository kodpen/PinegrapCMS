---
name: agent-door-knock-trial
description: Push door trial (dev, 2026-11-13): the site's knock reached a signed receiver in the same second; what a session on a personal computer still needs
---

## What was tried?
- On dev the application agent `@mimar`, a session of the desktop app on the machine that also serves dev, got door `push` with an address on the site's own host and a door secret. The receiver was a file outside the software: it takes a POST, checks `X-Pinegrap-Timestamp` (within five minutes) and `X-Pinegrap-Signature` (`sha256=` and the HMAC of `<timestamp>.<body>` with the secret), notes the request id in a local file and answers 204.
- A person's message naming the agent made request 79. `ws_agent_knock()` knocked in the same second, the receiver answered 204, `door_last_knock` was set and `door_failures` stayed 0. The session read the request with `GET /workspace/agent-requests` and answered by the usual routes about 8 seconds after the message.
- The receiver refused a GET (405) and a POST with a wrong signature (403).

## What does a session on a personal computer still need?
- A local process. Nothing outside starts a turn in a desktop session: only a background process that ends, a schedule or the person's own message. The trial's listener sleeps on the file the receiver writes and asks the site nothing.
- An https address the site can reach (`ws_agent_door_problem()` refuses anything else). The trial had one only because dev is served from the same machine. An agent on a computer without a public address stays on the poll door (`tools/ws_wait.php --requests`, `workflow/agent-channel-request-wait.md`).
- A message to all (`@hepsi`) makes no request and so no knock: a text waiter still covers it.

## What would spare the receiver?
- A waiting read of the queue, the request held open until one is queued, would wake an agent on any computer at once with no address and no secret. An idea of the trial: not built, not planned.

Related: `agent-doors.md`, `agent-door-routine-trial.md`, `workflow/agent-channel-wake.md`.
