---
name: mail-microsoft
description: A Workspace mailbox on Microsoft 365 or Outlook.com (G4): purpose mail_read, IMAP sign-in with XOAUTH2 through a connected account, answers through Graph, the box form's account, what stays unverified
---

## Why a connected account?
- Microsoft takes no password for IMAP or SMTP; owner answer 2 (#2444) chose connected-account reading for Microsoft 365 only. The box still reads over IMAP (`outlook.office365.com:993`), so fetching, the UID position, moving, limits and the sent folder stay the same code as for other services (`mail-fetch.md`). Transport stays `imap`; `graph` in the column stays unused.

## What signs in?
- Purpose `includes/connect/purposes/mail_read.php` (owner site): scopes `https://outlook.office.com/IMAP.AccessAsUser.All` and `https://graph.microsoft.com/Mail.Send`; `imap_scopes` names the IMAP resource, since Microsoft gives one token per resource.
- Box `settings.connection_id`. `ws_mail_box_credentials($box)` gives a token (`pg_connect_access_token()` with the IMAP scopes) or the kept password, error `no_account` / `no_password`; `ws_mail_box_login()` uses `pg_imap_login_xoauth2()` (AUTHENTICATE XOAUTH2, SASL-IR when offered; a refusal's challenge is answered with an empty line, the token never reaches the detail) or `pg_imap_login()`. Fetch, "Test connection" and the sent copy all go through them.

## How do answers leave?
- `reply_via = 'connection'` (the column already allowed it): `ws_mail_reply_route()` gives `connection` = the account; the answer and the automatic answer pass `email()` the property `connection`, which comes before SMTP Settings' account, must serve `mail_read` (else a permanent failure, never another address) and is delivered as the site's mail is (`pg_mail_connection_deliver()`, Graph `sendMail`, 4 MB). It survives the mail queue.
- From: `pg_mail_connection_from()` keeps the box address when it is the account's own, else the account sends with Reply-To the box. No sent copy is added (`ws_mail_reply_copy_plan()` reason `service`): Graph files it.

## What does the box form show?
- Mail service "Microsoft 365 or Outlook.com" (`connection` => true) fills the server; a "Connected account" select lists `pg_connect_accounts(purpose mail_read)` and keeps a gone one named; the user defaults to the box address when an account is chosen; no password is asked (`$signs_in` false). The saved account must serve `mail_read`.
- A "Connect a Microsoft account for mailboxes" button posts to `connect.php` (`ws_mail_connect_form()`) for a manager and above, the right connect.php asks for site accounts; others read who can.

## What stays unverified?
- No Microsoft tenant in the cloud session: the real XOAUTH2 exchange, the token's audience and Graph sending were never run. Exchange Online writes Authentication-Results without an authserv-id, so such a box's senders stay unverified until a checking server is set by hand (`mail-ingest.md`).

## Tests?
- `tests/imap_client_test.php` (XOAUTH2 with and without SASL-IR, refusal, not offered), `tests/workspace_mail_connection_test.php`, `tests/workspace_mail_card_test.php`.
