---
name: order-returns-stale-moves
description: A staff move posts the status its page showed (status_was) and is refused under the return lock if it moved (#1611): the refused-refund close, why the lock alone failed, who passes it
---

## Why does a staff move carry the status its page showed?
- Before (issue #1611): `view_return.php` read the row again and checked only `pg_return_staff_actions(<status now>)`, so a move from a page that showed another status went through. A driver refund in flight shows `refunded` with a Close button; the service refused, the return went back to `received`, and Close closed it. Closed with `received_at > 0` still holds the goods (`pg_return_holds_goods()`), the reminder job reads only shipped/received: the money never went back and nobody was told.
- Only close (from 5 statuses) and received (from approved or shipped) could act on an unseen state; every other move has one starting status, so `pg_return_can_move()` and the conditional UPDATE already refused it.

## Where is it checked, and why twice?
- Every form posts `status_was` (`$form_keys`: token, id, status). The screen compares it with the row before any action, so every stale page gets one sentence.
- The guard is `pg_return_move()`'s `status_was` option (`pg_return_status_seen()`), compared with the row it reads, before the move is judged; `pg_return_staff_move()` passes its options through under the order's return lock. The lock waits up to 5 s: a Close clicked during a refund that is then refused gets the lock once the return is back to received, which a check on the screen alone (row read before the lock) misses. The conditional UPDATE stays for the lock-free customer moves.
- Sentence: "The return was changed in the meantime. Open it again.", the one the conditional UPDATE already gave; one sentence for one situation (a clearer one is possible: the page is already open again).

## Why is the status enough?
- `received` and `refunded` can each be reached again (a refused refund goes back to received; the next refund is refunded again), each time with the same meaning, so no counter or schema step is needed.

## Who passes it, and who does not?
- Only `view_return.php`. A caller that leaves the key out compares nothing (`array_key_exists`); `null` or '' never matches.
- The refund: `pg_return_refund()` re-reads under the same lock and goes on only from `received`, the one status a refund starts from.
- Bulk approve (`view_returns.php`): only `requested` rows, and approved starts only from requested.
- The customer's moves have one starting status each; no API or Workspace route moves a return. A future one passes `status_was` to `pg_return_staff_move()`.

## What does a form drawn before the field get?
- Refused once, works after the reload: a tab opened before the deploy is the stalest page there is (unlike `balance_was`, gift-card-balance-edit.md, where a form without the field writes as before).
- The field is printed from the row with `h()`, never through the liveform: a refused post keeps its fields in the session, and a filled-back status would refuse every later click.

## Tests and sandbox?
- `tests/returns_stale_move_test.php`: the comparison, its place in `pg_return_move()` and under the lock, every form's field, the refund's received check.
- Sandbox (driver stand-in): Close clicked during a refund refused after 3 s waited 2 s on the lock and was refused, the return stays received (development closed it); a tab opened during a 10 s refusal, the same; Close on a requested page after another tab approved, Received on an approved page after the customer marked it sent, a form without the field: refused once, then go through.
