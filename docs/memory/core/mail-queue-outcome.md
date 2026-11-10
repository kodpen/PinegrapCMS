---
name: mail-queue-outcome
description: The mail queue's outcome hook (PR #8607): a queued row names a handler (on_outcome) and a reference (outcome_ref); after sent, given up or blocked the queue calls it; where it does not
module: mail_queue
---

## What does a row carry?
- Two keys in `mail_outbox.properties` (no schema): `on_outcome`, a handler name, and `outcome_ref`, a string the handler reads back (for example the Workspace mail item). `pg_mail_properties_encode()` drops only `queue`, so both survive, as does `keep_mime`.
- A row never carries a function name. `pg_mail_outcome_handlers()` maps the name to `array('function' => …, 'file' => …)`, the file a path under `includes/` relative to `PG_FUNCTIONS_DIR`. It ships empty: the Workspace entry comes with its handler `ws_mail_send_outcome()` in the send path (plan step WM3). tests/mail_queue_outcome_test.php checks that every entry's file and function exist.
- Loading as the carrier label drivers do: `is_file`, `require_once`, `function_exists`. An unknown name, a file outside `includes/` (or with `..`), a missing file or function is skipped silently.
- The handler's file loads what it needs itself: the queue runs in job.php, mail_job.php and the Mail queue screen's Run now and Retry, with no person behind the request.

## When is it called, and with what?
- `pg_mail_outcome_call($properties, $id, $outcome, $error, $mime)` in `pg_mail_queue_run()`, always after the row's status is written, so the handler sees the final state and its failure cannot leave the row in `sending`:
  - `sent`: after the UPDATE; the sent text is taken from `pg_mail_last_sent_mime()` right after email() returns true, before anything else can call email() and clear it.
  - `failed`, given up on the last attempt or a permanent error: after the UPDATE and its Site Log line.
  - `failed`, a stale `sending` row given up: its properties are read only for that row.
  - `suppressed`, every recipient blocked by the bounce gate.
- The outcome names are the row's statuses (`sent`, `failed`, `suppressed`). Arguments: row id, outcome, reference ('' when none), error ('' on sent), sent text (only when the row asked `keep_mime`, else '').
- A handler's error is caught (`\Throwable`) and written to the Site Log (with a database); the row and the run's counts do not change.

## Where is it not called?
- A row whose stored properties cannot be read (no handler can be known), a retry (`queued` again: not an outcome), the sweep of old rows, and Delete on the Mail queue screen. A queued row deleted there is never heard of: the send path must allow an item that waits for ever.
- email() with `queue` sends at once when no job is alive (`pg_mail_job_alive()`); that send calls no handler. A caller that needs the outcome writes the row with `pg_mail_enqueue()` and handles a 0 itself.

## What must a handler bear?
- Retry on the Mail queue screen turns a `failed` row back to `queued`, so `sent` can follow `failed`: the last outcome counts.
- The same outcome may come twice (a stale row sent before its process died is sent again): a handler is idempotent.

## Why?
- The queue told nobody how a message ended; the outcome stayed in `mail_outbox`. The Workspace mail screen's send path needs it to mark an item sent or failed and to show Not sent and Send again (`plans/workspace-mail-screen-sending.md`).
- The Mail queue screen lists type `workspace` as "Workspace" (`pg_mail_queue_list_types()`).
