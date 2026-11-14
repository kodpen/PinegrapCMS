---
name: mail-send-later
description: Send later (IB6): a time on the New e-mail form and the reply drawer, send_state later and ws_mail_items.send_at, the job that hands due mail to the queue, Send now and Cancel, the step 11.0.207
---

## What does the person see?
- "Send later" (Sonra gönder) on the New e-mail form and in the Reply by e-mail drawer opens "Send at", a `datetime-local` field read in the person's own time zone (`USER_TIMEZONE`, else the site's); "Send at once" closes and clears it.
- A time must be at least 5 minutes ahead (`WS_MAIL_LATER_MIN`) and at most 60 days (`WS_MAIL_LATER_MAX_DAYS`); a date or an hour the zone skips is refused, never moved (`ws_mail_later_parse()`, pure).
- The mail is written at once as any mail (conversation, card, files) but waits: badge "Sends at …" in the pane and the card, "Sends later" on the list row, listed under Not sent. Under it, for whoever may answer there: "Send now" and "Cancel sending".
- Cancel leaves it unsent (`failed`, "Cancelled before its time; not sent.") under Not sent, where Send again sends it.

## How is it kept and sent?
- `send_state` `later` (`WS_MAIL_LATER_STATE`; `ws_mail_send_state()` gives `later`, never `sent`) and `ws_mail_items.send_at` (Unix time). `sent_at` keeps one meaning: the time a mail left.
- `ws_mail_send()` writes the item `later` when `send_at` is given (`ws_mail_send_first_state()`) and `ws_mail_send_hand_on()` keeps it from the queue; it never uses `mail_outbox.send_after`, which the queue keeps for retries and the Mail queue screen's Retry resets.
- `ws_mail_later_run()` in `job.php`, beside `ws_mail_send_orphans_run()`, reads due rows by `idx_later` (50 a tick) and `ws_mail_later_release()` claims each (later to queued, one runner wins) before `ws_mail_send_dispatch()`. Send now uses the same release.

## Which schema, and who decided?
- Step `upgrade_2026_11_0_ws_mail_send_later()` (11.0.207): `send_at INT UNSIGNED NOT NULL DEFAULT 0`, `INDEX idx_later (send_state, send_at)`, no backfill. Owner (2026-11-14, poll 3416, outcome 3418): yes, the column; shape by the architect (3419). Readiness `ws_mail_later_ready()`.
- After running the step by hand outside the upgrade screen, `pg_schema_cache_clear()` is needed, or the readiness probe keeps answering no.

## Tests?
- `tests/workspace_mail_later_test.php`: the parse, the step run twice in memory, the state, badge and buttons, the wiring. Sandbox: the step twice on MariaDB, a reply set for later, the pane, the job, Cancel.
