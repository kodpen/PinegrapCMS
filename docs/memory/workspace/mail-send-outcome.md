---
name: mail-send-outcome
description: How the queue's outcome reaches a Workspace mail (ws_mail_send_outcome(), #8646): sent is final, only the current attempt fails it, the unwritten row id race, a deleted queue row
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

## And a mail whose queue row is gone?
- Delete on the Mail queue screen tells the handler `failed` (`core/mail-queue-outcome.md`), so the card reads Not sent with "It was deleted from the mail queue before it was sent.", Send again and the writer's `mail_unsent`.
- `ws_mail_send_orphans_run()`, on every tick of job.php: an out item `queued` with `outbox_id` > 0 whose `mail_outbox` row is missing gets the same outcome (a privacy erase, a delete before this fix). Only items of the last `WS_MAIL_SEND_ORPHAN_DAYS` (6) days: the queue keeps a sent row seven days, so a newer missing row never left; read on `idx_created`.
- Why: an answer whose row was deleted showed "Waiting to be sent" for ever (dev, 2026-11-12); the owner asked that it not stay waiting.
