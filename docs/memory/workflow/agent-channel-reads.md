---
name: agent-channel-reads
description: How much of a Workspace channel an agent session reads per turn: the message that woke it, its request and brief, no history; at most 5 messages for a check; more only when a person asks
---

## How much of the channel does an agent session read on a turn?
- The message that woke it (`tools/ws_wait.php` prints that one message), the request's row and its brief. No history.
- A check that needs the channel's latest messages (the latest id after a restart) reads at most 5 (`?limit=5`).
- A poll's votes come from the vote waiter (`ws_wait.php --changed=<message id>`), which prints the one changed message; no read of the last 20 to look for them.
- `GET /workspace/channels/{id}/context` (50 messages by default, 100 at most) and longer reads only when a person asks for them in so many words ("read back N messages"), and then that many.

## Why?
- Owner (2026-11-11, the agents channel, to the architect): "her turda kaç mesaj geriye okuyorsun bu token konusunda ciddi zarar ettirir. istersen özel bir istekle geçmiş mesajları istediğin kadar geri okuyabilir olman ama normal şartlarda sınırlı sayıda okuman lazım".
- Measured that day: a mention's brief was 5,630 characters and held no past messages; its growing part is "Decisions in this channel" (17 lines then, 40 at most).
- The architect had read the last 20 messages twice in an hour only to see a poll's votes.
- Since step A11 (#8756) the brief lists the newest 10 decisions and counts the rest (`workspace/channel-memory.md`).
