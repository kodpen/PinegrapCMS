---
name: mail-send-outcome
description: How the mail queue's outcome reaches a Workspace mail (ws_mail_send_outcome(), #8646): sent is final, only the current attempt fails it, the race of an unwritten row id, idempotence
---

## What does ws_mail_send_outcome() do?
- `ws_mail_send_outcome()` (registered in `pg_mail_outcome_handlers()`; loads the Workspace bootstrap itself; nothing before 11.0.185 has run). Rules in the pure `ws_mail_send_outcome_change()`:
  - `sent` from any row of the item is final: state, `sent_at`, the box's sent-folder copy. An older row's sent counts (the mail did reach them).
  - `failed`/`suppressed` only for the current attempt: the row the item names, or while `queued` the row queued after the one it named. That closes the race of a row finished before its id is written; an older row retried on the Mail queue screen leaves the item alone.
  - The same outcome twice changes nothing; the UPDATE is guarded by the state and row it read.
- Failed: `send_error` (the queue's reason), inbox `mail_unsent` to the writer; the card is touched so open screens redraw.

## Why these rules?
- The queue may send a row and call the handler before `ws_mail_send_dispatch()` writes the row's id on the item (`pg_mail_enqueue()` writes `send_after = 0`), so the reference carries the row the item named before (`<item>:<previous row>`).
- Retry on the Mail queue screen can bring `sent` after `failed`, and an old row after Send again: `core/mail-queue-outcome.md`. The send path: `mail-send.md`.
