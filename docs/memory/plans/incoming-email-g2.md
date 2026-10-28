---
name: incoming-email-g2
description: G2 of incoming e-mail (IMAP read) cut into three PRs: G2a client (pure, tested), G2b box card and Test connection, G2c fetch run, inline job, System status row; the read rules decided for G2c
---

## Which steps, and where do they stand?
- G2a: the socket IMAP client `pg_imap_*()` (`workspace/mail-imap-client.md`). Done, PR #2764.
- G2b: the box card's server fields and "Test connection" (`workspace/mail-card.md`). PR #PRNUM.
- G2c: `ws_mail_fetch($box)` and its inline pass in `job.php` (a `pg_cron_jobs()` entry `workspace_mail_job`, inline like `push_job`, own script for a dedicated cron line), `checked_at` / `ok_at` / `last_error` written as the test writes them (`ws_mail_last_error()`), a System status row (a box whose `ok_at` is over an hour behind `checked_at`, or `last_error` set), and the Mailboxes card text that still says reading comes later.

## Which read rules did G2c decide?
- Lock per box (`GET_LOCK('pg_ws_mail_<id>', 0)`); per run 15 s, 25 mails; Spam / Junk never read (only the box's folder).
- Position: `uidvalidity` / `last_uid`. First run, or UIDVALIDITY changed: `UID SEARCH SINCE <day before created_at, or before ok_at on a change>`, so a box does not bring in years of old mail; else `UID SEARCH UID <last_uid+1>:*`, all mail, not only unseen (people reading the box in a mail program must not hide mail from the channel).
- Per run: up to 100 UIDs `UID FETCH (UID RFC822.SIZE BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])`; a Message-ID already in `ws_mail_items` is skipped without a download; over 25 MB stays in the box with a `last_error` note; the rest `BODY.PEEK[]` into `ws_mail_ingest()`.
- `last_uid` moves only over finished UIDs (stored, duplicate, loop, refused, too large): a `held` mail stops it, so the next run reads it again (cheap: headers first, duplicates skipped).
- After a mail is taken: `seen` marks `\Seen`, `move` moves it (`pg_imap_uid_move()`); held mail is left untouched.

## Where to start?
- G2c: `includes/workspace/mail_fetch.php` (`ws_mail_fetch()`; the box's settings and sealed password as `ws_mail_box_test()` reads them, `includes/workspace/mail_settings.php`), its pass in `job.php` beside the push pass, the `pg_cron_jobs()` entry, `includes/fn/system_status.php`.
