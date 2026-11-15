---
name: mail-send-later
description: Send later (IB6): a time on the New e-mail form and the reply drawer, send_state later and ws_mail_items.send_at, the job that hands due mail to the queue, Send now and Cancel, the step 11.0.207
---

## What does the person see?
- "Send later" (Sonra gönder) on the New e-mail form and in the Reply by e-mail drawer opens "Send at" on the panel's date-and-time control (`h.dateField`), read in the person's own time zone (`USER_TIMEZONE`, else the site's); "Remove the time" (Zamanı kaldır) closes and clears it. While a time is set, Send reads "Send later" with the clock (architect's look, 3499).
- A time must be at least 5 minutes ahead (`WS_MAIL_LATER_MIN`) and at most 60 days (`WS_MAIL_LATER_MAX_DAYS`); a date or an hour the zone skips is refused, never moved (`ws_mail_later_parse()`, pure).
- The mail is written at once as any mail (conversation, card, files) but waits: badge "Sends at …" (teal, the site's date order and clock through `get_absolute_time()`) in the pane and the card, "Sends later" on the list row, listed under Not sent. Under it, for whoever may answer there: "Send now" and "Cancel sending".
- Cancel leaves it unsent (`failed`, "Cancelled before its time; not sent.") under Not sent, where Send again sends it.

## How is it kept and sent?
- `send_state` `later` (`WS_MAIL_LATER_STATE`; `ws_mail_send_state()` gives `later`, never `sent`) and `ws_mail_items.send_at` (Unix time). `sent_at` keeps one meaning: the time a mail left.
- `ws_mail_send()` writes the item `later` when `send_at` is given (`ws_mail_send_first_state()`) and `ws_mail_send_hand_on()` keeps it from the queue; it never uses `mail_outbox.send_after`, which the queue keeps for retries and the Mail queue screen's Retry resets.
- `ws_mail_later_run()` in `job.php`, beside `ws_mail_send_orphans_run()`, reads due rows by `idx_later` (50 a tick) and `ws_mail_later_release()` claims each (later to queued, one runner wins) before `ws_mail_send_dispatch()`. Send now uses the same release.

## What if its writer may no longer answer there? (#9676)
- Owner (2026-11-15, channel message 4238, his vote; outcome 4243): it is not sent; it turns back into its draft, and its writer and whoever answers for that inbox are told (`workflow/decisions-made-product-55.md`).
- Before the release the job asks `ws_mail_later_fate()` (pure): it leaves only while its writer (`user_id`) passes `ws_mail_later_may_answer()` (read the channel, `ws_mail_can_answer()`, the test of the buttons' `ws_mail_later_reached()`) with `ws_rights_for_id()` as now; a deleted or off-team account fails. A mail in its ten seconds for Undo is the same row and passes the same test.
- `ws_mail_later_hold()` turns it into its draft with Undo's code, `ws_mail_later_to_draft($item, true)`: the same claim (one runner wins), card or answer out of the channel, the conversation put back; but its files stay, named in `draft_files`, and the deleted file lines keep their file, which `ws_mail_send_file_reach()` reaches as the draft is reached (a reply's by the channel's readers, a new mail's by its writer).
- Then inbox kind `mail_held` (`mail-notices-and-event.md`). "Send now" by somebody who may answer there still sends it, whoever wrote it; "Cancel sending" is unchanged.

## Which schema, and who decided?
- Step `upgrade_2026_11_0_ws_mail_send_later()` (11.0.207): `send_at INT UNSIGNED NOT NULL DEFAULT 0`, `INDEX idx_later (send_state, send_at)`, no backfill. Owner (2026-11-14, poll 3416, outcome 3418): yes, the column; shape by the architect (3419). Readiness `ws_mail_later_ready()`.
- After running the step by hand outside the upgrade screen, `pg_schema_cache_clear()` is needed, or the readiness probe keeps answering no.

## Tests?
- `tests/workspace_mail_later_test.php`: the parse, the step run twice in memory, the state, badge and buttons, the wiring, the held mail's fate, who is told and its wiring (#9676). Sandbox: the step twice on MariaDB, a reply set for later, the pane, the job, Cancel.
