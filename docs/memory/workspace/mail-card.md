---
name: mail-card
description: How a mail is drawn in a Workspace channel (ws_mail_card_html(), sender like a guest) and the Mailboxes card of Workspace settings with the .eml upload (mail_settings.php)
---

## How is a mail message drawn?
- `ws_message_payloads()` reads the page's mails once (`ws_mail_items_map()`: items with their conversation) into `$extra['mail']`; `ws_message_payload()` gives a `sender_kind 'mail'` message `ws_mail_sender()` (name from the mail, else the address; `id` 0, `title` the address, letters avatar, `mail: true`, like `ws_guest_sender()`) and `html` from `ws_mail_card_html()`, never `ws_render_body()`: mail text is untrusted and is not Workspace markup.
- The card: badge "E-mail" (`auth` pass) or "Sender not verified" (fail / none) with the address; the subject only on the conversation's card (`conversation.message_id` = the message); the message text escaped with `nl2br`; `<details>` "Show all" with `body_text` (first 100,000 characters) when it is longer than the message; "Not taken in: …" from `refused_files`. An attachment's own message (file, no text) draws only its file; a missing item says "This e-mail is no longer available."
- No JavaScript change: the screen draws `sender` and `html` as for any message. Mail messages cannot be edited, marked or deleted (those checks know `user` / `guest` only).

## Where are mailboxes added?
- Workspace settings (`workspace_settings.php`, holders of the settings right), card `#ws-mail` from `ws_mail_settings_card()`; posts `mail_box_save` and `mail_eml` go to `ws_mail_settings_post()` (`includes/workspace/mail_settings.php`).
- A box: name (≤ 100), address, channel (a public or private channel the editor reads, `ws_mail_box_channels()`; a box keeps a channel its editor cannot see until another is picked), checking server (`settings.auth_server`, a host name; `mail-ingest.md`), on/off. Checked by the pure `ws_mail_box_input()`. Added or changed, with a Site Log line; no delete yet.
- Reading (G2b, PR #2788): mail service (`ws_mail_providers()`: Gmail/Google Workspace, Yandex, iCloud, Zoho, Yahoo, another server; none = .eml only), which fills an empty IMAP server, port and checking server (only `mx.google.com` and `mx.zohomail.com` are fixed names; the others write under each host's own name). `settings`: `provider`, `host`, `port`, `user`, `move_folder`, `secret` (`pg_connect_seal(['password' => …])`); columns `folder`, `after_fetch`. No Microsoft: it takes no password for IMAP (G4).
- The password is drawn empty with "Stored. Type a new one…"; an empty box keeps it, but a new server or user needs it typed again; no key (`pg_connect_can_seal()` false) refuses to save one.
- "Test connection" (`mail_test`, `ws_mail_box_test()`): connect, sign in, EXAMINE the folder, read the newest mail's topmost `Authentication-Results` (`ws_mail_authserv_id()`) and EXAMINE the move folder; changes nothing in the mailbox. It writes `checked_at`, `ok_at`, `last_error` (`code|server words`, `ws_mail_last_error()`), and an empty checking server takes the name found. `ws_mail_error_text()` words each code (Gmail's app-password hint); `ws_mail_box_check_line()` shows the last check under the box.
- "Bring in an .eml file" per box: the uploaded file goes to `ws_mail_ingest()`; `ws_mail_ingest_message()` turns each status / reason into a notice or an error. This is how a box without a server is fed; one with a server is read by `workspace_mail_job.php` (`mail-fetch.md`).
- `ws_mail_ready()`: the three tables exist; the card is hidden before the upgrade.

## Tests?
- `tests/workspace_mail_card_test.php`.
