---
name: incoming-email-g2
description: G2 of incoming e-mail (IMAP read) cut into three PRs: G2a client (pure, tested), G2b box card and Test connection, G2c fetch run, inline job, System status row; the read rules decided for G2c
---

## Which steps, and where do they stand?
- G2a: the socket IMAP client `pg_imap_*()` (`workspace/mail-imap-client.md`). PR #PRNUM.
- G2b: the box card in `mail_settings.php`: provider (Gmail, Outlook.com, Yandex, iCloud, Zoho, other) filling IMAP host/port and `settings.auth_server` (`mx.google.com` for Gmail and Google Workspace…), user, password sealed with `pg_connect_seal()` (drawn empty, an empty box keeps it; no box saves a password while `pg_connect_can_seal()` is false), folder, after-fetch (seen | move + folder), "Test connection" (connect, sign in, EXAMINE the folder; says the error code in words, Gmail's app-password hint). No schema change: all in `settings` and the G1 columns.
- G2c: `ws_mail_fetch($box)` and its inline pass in `job.php` (a `pg_cron_jobs()` entry `workspace_mail_job`, inline like `push_job`, own script for a dedicated cron line), `checked_at` / `ok_at` / `last_error` written, a System status row (a box whose `ok_at` is over an hour behind `checked_at`, or `last_error` set), the card's "Last checked" line and the Mailboxes card text that still says reading comes later.

## Which read rules did G2c decide?
- Lock per box (`GET_LOCK('pg_ws_mail_<id>', 0)`); per run 15 s, 25 mails; Spam / Junk never read (only the box's folder).
- Position: `uidvalidity` / `last_uid`. First run, or UIDVALIDITY changed: `UID SEARCH SINCE <day before created_at, or before ok_at on a change>`, so a box does not bring in years of old mail; else `UID SEARCH UID <last_uid+1>:*`, all mail, not only unseen (people reading the box in a mail program must not hide mail from the channel).
- Per run: up to 100 UIDs `UID FETCH (UID RFC822.SIZE BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])`; a Message-ID already in `ws_mail_items` is skipped without a download; over 25 MB stays in the box with a `last_error` note; the rest `BODY.PEEK[]` into `ws_mail_ingest()`.
- `last_uid` moves only over finished UIDs (stored, duplicate, loop, refused, too large): a `held` mail stops it, so the next run reads it again (cheap: headers first, duplicates skipped).
- After a mail is taken: `seen` marks `\Seen`, `move` moves it (`pg_imap_uid_move()`); held mail is left untouched.

## Where to start?
- G2b: `includes/workspace/mail_settings.php` (`ws_mail_box_input()` grows the fields; pure, add to `tests/workspace_mail_card_test.php`), a provider table as a pure function, the test as a panel post on the card.
