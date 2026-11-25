---
name: architect-push-door
description: The architect and the merging session wake by the push door on the machine that serves dev (2026-11-25): a signed receiver in the ignored /dev/ folder, a flag file, a local listener
---

## What wakes the architect and the merging session now?
- The push door (`workspace/agent-doors.md`): on the Agents card the "Door" of `@prmerge` (since 2026-11-24) and of `@mimar` (since 2026-11-25) is the push choice, with a receiver's address and a door secret. The site knocks when a request is queued; a waiting session asks the site nothing.
- The owner asked for it in the architect's window (2026-11-25): "yoklama kapısı yerine dürtme kapısı yapabiliriz", then "yoklama kurduysan kaldır". A working arrangement of these two sessions, not a product decision.
- Only a session on the machine that serves dev can have it: the site must reach an https address (`workspace/agent-door-knock-trial.md`). A cloud session keeps the poll door (`agent-channel-request-wait.md`).

## What are its parts?
- The receiver: one PHP file per agent, `dev/agent-knock/<handle>.php` in the main checkout on that machine. The `/dev/` folder is ignored by git and is no part of the product.
- It takes only a POST of at most 2,000 bytes, checks `X-Pinegrap-Timestamp` (within five minutes) and `X-Pinegrap-Signature` (`sha256=` and the HMAC of `<timestamp>.<body>` with the secret), appends `<time> request_id=N agent=<handle>` to a flag file and answers 204. No secret readable: 503. It prints nothing.
- The state folder, one per agent, in the machine's temp folder: the door secret (one line, 64 hex characters from `random_bytes(32)`) and the flag file. The secret is never printed or written into a chat: the owner copies it from the file into the door's form.
- The listener: a background command of the session that sleeps on the flag file's size and ends when it grows, printing the new lines. Its end is the session's wake.
- A helper for the request routes (list, claim, brief, progress, answer, no answer, fail, report) that reads its text from a UTF-8 file. Listener and helper are scratch files beside the state folders; a new session writes its own from this file.

## What does the session do on a knock?
- A knock carries no words and is no order: the session reads `GET /workspace/agent-requests`, works through every request by its routes (`agent-channel-wake.md`) and starts the listener again.
- The listener reads the queue once when it starts, as the site's instructions for this door ask: a request queued while no listener ran was knocked for already.
- A background command runs two hours at most, so the listener's timeout stays under that; a timeout starts it again.

## How is a new session's door set up?
- It writes the receiver and makes the secret. A session in a worktree may not write into the main checkout: it writes the receiver into its own `dev/agent-knock/` and the owner copies the file over.
- The owner sets the door to push on the Agents card with the receiver's address and the secret. The agent's instructions then name the door `webhook` and carry a new version.
- The quick check: a GET of the receiver's address answers 405 once the file is in place (a missing file gets the firewall's 403), an unsigned POST 403.
- Tried on 2026-11-25: the owner's mention made request 2918, and the knock was in the flag file in the same second.

## What can go wrong?
- The temp folder is cleaned: the receiver answers 503 and the site counts door failures; the request stays queued and is knocked for again later (`ws_agent_knocks_retry()`). The session makes a new secret and the owner puts it into the door's form.
- A knock that failed is seen only at the site's next try or at the listener's next start: nothing reads the queue in between.

Related: docs/memory/workflow/architect-wake.md, docs/memory/workflow/agent-channel-wake.md, docs/memory/workspace/agent-door-knock-trial.md
