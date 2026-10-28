---
name: mail-fetch
description: Reading Workspace mailboxes over IMAP (ws_mail_fetch(), inline in job.php): what is searched, the UID position, held mail, limits, after-fetch, what the box records, the System status tile
---

## Who reads a mailbox, and when?
- `job.php` on every tick (G2c2, no switch, like the scheduled actions): while the module is on and a box `enabled`, `transport = 'imap'` has a `host`, it loads `mail_fetch.php` and calls `ws_mail_fetch_run(20)`, then `pg_cron_ran('workspace_mail_job')`. A site with no such box costs one small read.
- Catalogue `workspace_mail_job`: `inline => true`, `dispatch => false` (no switch on the scheduled jobs screen, not in the rotation; Operations alerts count it as always expected). `workspace_mail_job.php` (`ws_mail_fetch_run(40)`) stays for a dedicated cron line.
- `ws_mail_fetch_run()` reads boxes not read in the last `WS_MAIL_FETCH_EVERY` (60) s, longest waiting first, while a whole box budget (15 s) still fits.
- `includes/workspace/mail_fetch.php` is loaded only by those two callers (`require_once`), with the IMAP client (`mail-imap-client.md`).

## What does System status show?
- Tile "Incoming E-mail" (reported, not scored; only while a box with a server is on), from `ws_mail_fetch_health()` in `mail_settings.php`: red when a box's `last_error` is set (not `too_large`); yellow for `too_large` or no good read (`ok_at`, else `created_at`) for over an hour; detail rows per box with the error text from `ws_mail_error_text()`. Links to `workspace_settings.php#ws-mail`.

## What does one read do?
- `GET_LOCK('pg_ws_mail_<id>', 0)`: a second reader answers `locked` and skips the box.
- Signs in with the sealed password (`settings.secret`), SELECTs the box's folder (only it: Spam/Junk are never read).
- `ws_mail_fetch_plan()`: a first read, or UIDVALIDITY changed, searches `SINCE` the day before `created_at` (before `ok_at` after a renumbering); else `UID <last_uid+1>:*`, read or not, so mail opened in a mail program still arrives. `ws_mail_fetch_candidates()` drops what is not past the position ("UID n:*" also answers the newest mail) and keeps 100.
- Headers first: `UID FETCH (UID RFC822.SIZE BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])`; a Message-ID whose `ws_mail_hash()` is already in `ws_mail_items` is not downloaded (it is still marked or moved). Over 25 MB stays in the box, untouched, and the box notes `too_large`. Up to 25 mails a run are downloaded with `BODY.PEEK[]` into `ws_mail_ingest()`.
- `ws_mail_fetch_outcome()`: stored, duplicate, loop and other refusals are taken; `held` is left as it is; `no_channel` / `not_saved` end the run with that error (the box itself is wrong).
- Taken mail is marked `\Seen` or moved to `settings.move_folder` (`after_fetch`), in one command at the end.

## Where does the position stop?
- `ws_mail_fetch_cursor()`: `last_uid` moves over a row of finished UIDs only; a held or not reached mail keeps it before that mail, so the next run reads it again (cheap: headers, and what is known is skipped). A fresh position starts before the first candidate, or at UIDNEXT − 1 when the date search found nothing.
- `uidvalidity` and `last_uid` are written even when the run fails after some mail was taken.

## What does the box record?
- `checked_at` every run; `ok_at` and an empty `last_error` on success; else `last_error` = `code|server words` (`ws_mail_last_error()`), drawn on the Mailboxes card by `ws_mail_box_check_line()` (`mail-card.md`). A run that left a too-large mail succeeds with `too_large|`.

## Tests?
- `tests/workspace_mail_fetch_test.php`: plan, candidates, cursor, Message-ID, outcomes, the catalogue entry and the job.php pass, the System status health. The read itself was not run against a server (no port 993 in the cloud sessions).
