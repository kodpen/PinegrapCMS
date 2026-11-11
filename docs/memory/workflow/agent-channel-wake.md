---
name: agent-channel-wake
description: How a session waits on the agent channel: on its request queue (tools/ws_wait.php --requests; a person's message makes the request, an agent's not yet), on text and changes, the handles typed in it
---

## How does a session wake on the channel? (architect, 2026-11-11)
- On its request queue. When a person writes, every wake rule makes a request for the agent (`workspace/agent-doors.md`): a mention, an answer to one of its messages (rule "Replies to its messages"), a scheduled wake. Only a mention carries the `@handle`, so a waiter on the message text misses the rest: on 2026-11-11 the owner's reply to the architect was queued and shown as "Waiting for `@mimar`" while the session slept.
- `php tools/ws_wait.php --requests --timeout=7000` waits on that queue: exit 0 on the oldest queued request, printed as one line; what it prints, its exits and its restart: [[agent-channel-request-wait]].
- A message to all (`@hepsi`) is no agent's handle and makes no request: for it a waiter on the text, `php tools/ws_wait.php --from=person --regex='/@hepsi\b/i' --timeout=7000`.
- Another agent's message makes no request yet, whatever it names or answers: on 2026-11-11 the architect's message naming `@prmerge` and the merging session's reply were each seen only because someone looked. Step A14 of `plans/agent-channel-steps-4.md` changes it; until then a session that expects an agent's words also runs `php tools/ws_wait.php --from=app --regex='/@<handle>\b/i' --timeout=7000`.
- A waiter, the request waiter and a text waiter alike, runs with Bash `run_in_background` (timeout 7200000). It exits on a match and so wakes the session, which answers and starts it again (the request waiter once the request is claimed, the text waiter with `--after-id=<last id>`); exit 3 (timeout) starts it again; each check-in makes sure they run. Waiting costs no tokens; each wake is one turn.
- A request is answered through its own routes (brief, claim, answer), never as a plain message. The architect asks owner questions here as one-choice polls (`workspace/agent-questions.md`) and keeps its window for the rest (`architect.md`).

## Which options does the waiter take?
- `--prefix`, `--contains`, `--regex`: on the message's `text`.
- `--from=person`: only a message with `sender.user_id` set, so an application's line (CI's `[ci]` / `[ready]` included) never wakes a session waiting for people; `--from=app`: only an application's.
- `--mention=<application id>`: only a message tagging that application (`<@app:N>` in `body`), as the @ picker writes it (`workspace/agent-shown.md`).
- `--changed=<id>[,<id>]`: wakes when one of those messages comes under sync `changed` (a tick, a vote, an edit) and prints it with its `checks` and `poll`. With `--changed` alone new messages do not wake; with a rule as well either does.
- `tools/ws_post.php --parent=<id>` answers a message (Reply), e.g. the decision that closes a question.
- `tools/ws_poll.php ask` / `close <poll id>` asks a one-answer question as a poll and locks it once the vote is read; wait on its message with `--changed=<message id>` (`workspace/agent-questions.md`, #8564).

## Which handles are typed?
- `@mimar`, `@prmerge` (the merging session), `@koordinator-<account>`, `@test`, `@hepsi` (all); a text rule matches them by `--regex`. CI writes its lines into the same agent channel (owner, 2026-11-08); such a line wakes nobody until the merging session moves its cadence to the channel.

## What does a session need?
- The four `PG_WS_*` variables of its cloud environment (`agent-channel-experiment.md`), an application per session that joins (`ADNAN Mimar`, `PR Merge` today).
