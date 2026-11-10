---
name: agent-channel-wake
description: How a session waits on the agent channel with tools/ws_wait.php (--from, --mention, --changed), the handles typed in it, and who it wakes
---

## How does a session wake on the channel? (architect, 2026-11-08)
- A waiter: `php tools/ws_wait.php --from=person --regex='/@(mimar|hepsi)\b/i' --timeout=7000`, run with Bash `run_in_background` (timeout 7200000). It exits on a match and so wakes the session, which answers and starts it again with `--after-id=<last id>`; exit 3 (timeout) starts it again; each check-in makes sure one runs. Waiting costs no tokens (a 304 every 5 s); each wake is one turn.
- The architect asks owner questions here as checklists (`workspace/agent-checklist-questions.md`) and keeps its window for the rest (`architect.md`).

## Which options does the waiter take?
- `--prefix`, `--contains`, `--regex`: on the message's `text`.
- `--from=person`: only a message with `sender.user_id` set, so an application's line (CI's `[ci]` / `[ready]` included) never wakes a session waiting for people; `--from=app`: only an application's.
- `--mention=<application id>`: only a message tagging that application (`<@app:N>` in `body`), as the @ picker writes it (`workspace/agent-shown.md`).
- `--changed=<id>[,<id>]`: wakes when one of those messages comes under sync `changed` (a tick, an edit) and prints it with its `checks`. With `--changed` alone new messages do not wake; with a rule as well either does.
- `tools/ws_post.php --parent=<id>` answers a message (Reply), e.g. the decision that closes a question.

## Which handles are typed?
- `@mimar`, `@birlestirici`, `@koordinator-<account>`, `@test`, `@hepsi` (all), matched by `--regex`. CI writes its lines into the same agent channel (owner, 2026-11-08); such a line wakes nobody until the merging session moves its cadence to the channel.

## What does a session need?
- The four `PG_WS_*` variables of its cloud environment (`agent-channel-experiment.md`), an application per account (`ADNAN Mimar` today).
